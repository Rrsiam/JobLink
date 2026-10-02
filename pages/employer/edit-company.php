<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

$profile = $db->prepare("
    SELECT u.*, ep.*
    FROM users u
    JOIN employer_profiles ep ON u.id = ep.user_id
    WHERE u.id = ?
")->execute([$user_id])->fetch();

if (!$profile) {
    header('Location: ?role=employer&page=dashboard');
    exit;
}

// The values the form is rendered from. On a validation failure the submitted
// values are put back here rather than thrown away by the redirect, so a typo
// in one field does not cost the employer the whole form.
$form = [
    'company_name' => (string)($profile['company_name'] ?? ''),
    'industry'     => (string)($profile['industry'] ?? ''),
    'size'         => (string)($profile['company_size'] ?? ''),
    'location'     => (string)($profile['address'] ?? ''),
    'website'      => (string)($profile['website'] ?? ''),
    'phone'        => (string)($profile['phone'] ?? ''),
    'email'        => (string)($profile['email'] ?? ''),
    'about'        => (string)($profile['description'] ?? ''),
    'benefits'     => (string)($profile['benefits'] ?? ''),
];

$logo = trim((string)($profile['logo'] ?? ''));
$logo_exists = $logo !== '' && is_file(__DIR__ . '/../../uploads/logos/' . $logo);
$initials = mb_strtoupper(mb_substr((string)preg_replace('/[^\p{L}\p{N}]+/u', '', $form['company_name']), 0, 2));

// Reject and go back to the form with the message and the typed values.
$reject = function ($message) use (&$form) {
    $_SESSION['error'] = $message;
    $_SESSION['company_form'] = $form;
    header('Location: ?role=employer&page=edit-company');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string)($_POST[$key] ?? ''));
    }

    // Lengths mirror the employer_profiles / users columns so a long value is
    // reported here rather than being silently truncated by MySQL.
    $max = [
        'company_name' => 160, 'industry' => 120, 'size' => 80,
        'location' => 255, 'website' => 180, 'phone' => 30, 'email' => 150,
    ];
    foreach ($max as $key => $limit) {
        if (mb_strlen($form[$key]) > $limit) {
            $reject(ucfirst(str_replace('_', ' ', $key)) . ' must be ' . $limit . ' characters or fewer.');
        }
    }

    if ($form['company_name'] === '') {
        $reject('Company name is required.');
    }
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $reject('Enter a valid HR email address.');
    }
    // users.email is UNIQUE, so a clash has to be caught before the UPDATE or it
    // surfaces as an uncaught PDOException.
    $email_taken = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?")->execute([$form['email'], $user_id])->fetchColumn();
    if ($email_taken) {
        $reject('That email address is already in use.');
    }
    if ($form['website'] !== '' && !filter_var(preg_match('#^https?://#i', $form['website']) ? $form['website'] : 'http://' . $form['website'], FILTER_VALIDATE_URL)) {
        $reject('Enter a valid website address, for example https://example.com');
    }

    // Logo upload. The browser-supplied type is only a hint, so the real type
    // is read from the file itself. SVG is deliberately not accepted: it is
    // served from this origin and can carry script.
    $logo_file = '';
    $mime = '';
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    if (!empty($_FILES['logo']['name'])) {
        $upload = $_FILES['logo'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $reject('Logo upload failed (error code ' . (int)$upload['error'] . ').');
        }
        if ($upload['size'] > 2 * 1024 * 1024) {
            $reject('Logo must be 2 MB or smaller.');
        }
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $upload['tmp_name']);
                finfo_close($finfo);
            }
        }
        if ($mime === '') {
            $reject('Could not read the logo file. Please try again.');
        }
        if (!isset($allowed[$mime])) {
            $reject('Logo must be a PNG or JPG image.');
        }

        $uploads_dir = __DIR__ . '/../../uploads/logos';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }
        // The stored name is generated here, so a crafted filename cannot
        // escape uploads/logos.
        $logo_file = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($upload['tmp_name'], $uploads_dir . '/' . $logo_file)) {
            $reject('Failed to save the uploaded logo.');
        }
    }

    $db->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?")
        ->execute([$form['email'], $form['phone'], $user_id]);

    $sql = "UPDATE employer_profiles SET
                company_name = ?, industry = ?, address = ?,
                website = ?, company_size = ?, description = ?, benefits = ?";
    $params = [
        $form['company_name'], $form['industry'], $form['location'],
        $form['website'], $form['size'], $form['about'], $form['benefits'],
    ];
    if ($logo_file !== '') {
        $sql .= ', logo = ?';
        $params[] = $logo_file;
    }
    $db->prepare($sql . ' WHERE user_id = ?')->execute(array_merge($params, [$user_id]));

    // The previous logo is only removed once the new one is safely in place and
    // the row points at it.
    if ($logo_file !== '' && $logo_exists) {
        @unlink(__DIR__ . '/../../uploads/logos/' . $logo);
    }

    unset($_SESSION['company_form']);
    $_SESSION['success'] = 'Company profile updated.';
    header('Location: ?role=employer&page=company-profile');
    exit;
}

// Put back whatever the employer typed before the last validation failure.
if (isset($_SESSION['company_form']) && is_array($_SESSION['company_form'])) {
    $form = array_merge($form, $_SESSION['company_form']);
    unset($_SESSION['company_form']);
}

$logo_html = $logo_exists
    ? '<img src="uploads/logos/' . e($logo) . '" alt="Current logo">'
    : '<span class="cp-void">None</span>';

// Every value is escaped here and marked RawHtml. render() treats a plain
// scalar containing '<' as pre-built markup, so passing a stored value
// straight through would let a company name break out of its attribute.
$field = function ($value) {
    return new RawHtml(e($value));
};

render('employer/edit-company.html', [
    'company_name' => $field($form['company_name']),
    'industry'     => $field($form['industry']),
    'size'         => $field($form['size']),
    'location'     => $field($form['location']),
    'website'      => $field($form['website']),
    'phone'        => $field($form['phone']),
    'email'        => $field($form['email']),
    'about'        => $field($form['about']),
    'benefits'     => $field($form['benefits']),
    'initials'     => $field($initials !== '' ? $initials : 'CO'),
    'logo'         => new RawHtml($logo_html),
    'logo_note'    => new RawHtml($logo_exists
        ? 'A logo is already set. Uploading a new one replaces it.'
        : 'No logo uploaded yet.'),
    'flash'        => new RawHtml(flash_message()),
]);
