<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$user_id = get_user_id();
$db = getDB();

$user = get_user_data();
$profile = $db->prepare("SELECT * FROM admin_profiles WHERE user_id = ?")->execute([$user_id])->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = admin_profile_save($db, $user_id, $_POST, $_FILES['photo'] ?? [], trim($profile['photo'] ?? ''));
    if ($error !== null) {
        $_SESSION['error'] = $error;
    }
    header('Location: ?role=admin&page=profile');
    exit;
}

$name    = trim($user['name'] ?? '');
$title   = trim($profile['professional_title'] ?? '');
$dept    = trim($profile['department'] ?? '');
$address = trim($profile['address'] ?? '');

$headline = $title !== '' ? $title : ($dept !== '' ? $dept : 'Administrator');

$contacts = '';
$contact_items = [
    ['&#128231;', $user['email'] ?? '', 'Email'],
    ['&#128241;', $user['phone'] ?? '', 'Phone'],
    ['&#128205;', $address, 'Location'],
    ['&#127970;', $dept, 'Department'],
    ['&#128197;', date('M j, Y', strtotime($user['created_at'])), 'Joined'],
];
foreach ($contact_items as [$icon, $value, $label]) {
    $value = trim((string)$value);
    if ($value === '') {
        continue;
    }
    $contacts .= '<div class="pf-contact">'
        . '<span class="pf-contact-icon">' . $icon . '</span>'
        . '<span class="pf-contact-body">'
        . '<span class="pf-contact-label">' . htmlspecialchars($label) . '</span>'
        . '<span class="pf-contact-value">' . htmlspecialchars($value) . '</span>'
        . '</span></div>';
}

$initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
$photo = trim($profile['photo'] ?? '');
$avatar = ($photo !== '' && is_file(__DIR__ . '/../../uploads/photos/' . $photo))
    ? '<img src="uploads/photos/' . htmlspecialchars($photo) . '" alt="' . htmlspecialchars($name) . '">'
    : htmlspecialchars($initial);

// Account and Access cards. Values are escaped here and wrapped in RawHtml so
// render() cannot re-escape them or treat user input containing '<' as markup.
$account_html = '<li>' . htmlspecialchars((string)($user['email'] ?? '')) . '</li>'
    . '<li>' . htmlspecialchars((string)($user['phone'] ?? '')) . '</li>'
    . '<li>Joined ' . htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) . '</li>'
    . '<li>' . htmlspecialchars(ucfirst((string)$user['status'])) . ' account</li>';

$access_html = '<li>Full platform administration</li>'
    . '<li>Approve or suspend accounts</li>'
    . '<li>Manage jobs and applications</li>'
    . '<li>View reports and settings</li>';

render('admin/profile.html', [
    'name' => new RawHtml(e($name)),
    'avatar' => new RawHtml($avatar),
    'headline' => new RawHtml(e($headline)),
    'contacts' => new RawHtml($contacts),
    'notification' => new RawHtml(flash_message()),
    'account_html' => new RawHtml($account_html),
    'access_html' => new RawHtml($access_html),
]);