<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

$user = get_user_data();
$profile = $db->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

$name = trim($user['name'] ?? '');
$title = trim($profile['professional_title'] ?? '');
$location = trim($profile['address'] ?? '');
$about = trim($profile['career_objective'] ?? '');
$education = trim($profile['education'] ?? '');
$experience = trim($profile['experience'] ?? '');
$skills_raw = trim($profile['skills'] ?? '');
$certifications = trim($profile['certifications'] ?? '');
$languages_raw = trim($profile['languages'] ?? '');

// A single text column is reused for education / experience / certifications,
// where users often type one entry per line. Render each line as its own
// list item. Defined as closures rather than functions because page
// controllers are included (not include_once) and a repeated include would
// fatal on a redeclared function.
$lines_to_list = function ($text) {
    if ($text === '') {
        return '';
    }
    $out = '';
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $out .= '<li>' . htmlspecialchars($line) . '</li>';
    }
    return $out;
};

$education_html = $lines_to_list($education);
$experience_html = $lines_to_list($experience);
$certifications_html = $lines_to_list($certifications);
$about_html = $about === '' ? '' : nl2br(htmlspecialchars($about));

// Skills and languages are comma separated. Show them as tags.
$csv_to_tags = function ($text, $empty_text) {
    $tags = '';
    foreach (preg_split('/\s*,\s*/', (string)$text) as $tag) {
        $tag = trim($tag);
        if ($tag === '') {
            continue;
        }
        $tags .= '<span class="pf-tag">' . htmlspecialchars($tag) . '</span>';
    }
    return $tags !== '' ? $tags : '<span class="pf-empty">' . htmlspecialchars($empty_text) . '</span>';
};

$skills_html = $csv_to_tags($skills_raw, 'No skills listed yet');
$languages_html = $csv_to_tags($languages_raw, 'Not specified');

// Contact details render as a row of labelled items; empty ones are dropped
// so the row never shows placeholders.
$contacts = '';
$contact_items = [
    ['📧', $user['email'] ?? '', 'Email'],
    ['📱', $user['phone'] ?? '', 'Phone'],
    ['📍', $location, 'Location'],
    ['🎂', $profile['dob'] ?? '', 'Date of birth'],
    ['⚧', trim($profile['gender'] ?? ''), 'Gender'],
];
foreach ($contact_items as [$icon, $value, $label]) {
    $value = trim((string)$value);
    if ($value === '') {
        continue;
    }
    if ($label === 'Date of birth') {
        $value = date('M d, Y', strtotime($value));
    }
    $contacts .= '<div class="pf-contact">'
        . '<span class="pf-contact-icon">' . $icon . '</span>'
        . '<span class="pf-contact-body">'
        . '<span class="pf-contact-label">' . htmlspecialchars($label) . '</span>'
        . '<span class="pf-contact-value">' . htmlspecialchars($value) . '</span>'
        . '</span></div>';
}

// Profile photo when uploaded, otherwise initials.
$initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
$photo = trim($profile['photo'] ?? '');
$avatar = ($photo !== '' && is_file(__DIR__ . '/../../uploads/photos/' . $photo))
    ? '<img src="uploads/photos/' . htmlspecialchars($photo) . '" alt="' . htmlspecialchars($name) . '">'
    : htmlspecialchars($initial);

// Headline: professional title, falling back to the name so the hero is
// never blank.
$headline = $title !== '' ? $title : 'Job Seeker';

// Completion ring, matching the calculation used on the dashboard so the
// two pages never disagree.
$completion = 0;
$required = [
    'professional_title' => $title,
    'address'            => $location,
    'career_objective'   => $about,
    'education'          => $education,
    'experience'         => $experience,
    'skills'             => $skills_raw,
];
foreach ($required as $value) {
    if ($value !== '') {
        $completion += 100 / count($required);
    }
}
$completion = (int)round($completion);

// Snap to the nearest level the stylesheet has a rule for, so the ring always
// matches a .pf-ring-fill[data-percent] selector rather than needing an inline
// stroke-dashoffset baked into the markup.
$levels = [0, 17, 33, 50, 67, 83, 100];
$snapped = $levels[0];
$smallest_gap = abs($snapped - $completion);
foreach ($levels as $level) {
    $gap = abs($level - $completion);
    if ($gap < $smallest_gap) {
        $snapped = $level;
        $smallest_gap = $gap;
    }
}
$completion = $snapped;

// Resume summary for the Resume card. render() escapes plain scalars, so these
// are passed as normal values rather than RawHtml.
$resume_name = trim($profile['resume'] ?? '');
$resume_path = $resume_name !== ''
    ? __DIR__ . '/../../uploads/resumes/' . basename($resume_name)
    : '';
$resume_exists = $resume_name !== '' && is_file($resume_path);

if ($resume_exists) {
    $bytes = filesize($resume_path);
    $size = $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : max(1, (int)round($bytes / 1024)) . ' KB';
    // Entities are baked in here and the whole fragment is marked as RawHtml,
    // otherwise render() would escape '&' and print "&middot;" literally.
    $resume_meta = new RawHtml(
        strtoupper(pathinfo($resume_path, PATHINFO_EXTENSION))
        . ' &middot; ' . e($size)
        . ' &middot; Updated ' . e(date('M j, Y', filemtime($resume_path)))
    );
    $resume_file = $resume_name;
} else {
    $resume_meta = new RawHtml('');
    $resume_file = '';
}

// Text values are escaped here and wrapped in RawHtml so render() cannot
// re-escape them or treat user input containing '<' as markup. The fragments
// below are built with escaped values already.
render('applicant/profile.html', [
    'notification' => new RawHtml(flash_message()),
    'resume_file' => $resume_exists ? $resume_file : '',
    'resume_meta' => $resume_exists ? $resume_meta : '',
    'resume_show' => $resume_exists ? 'flex' : 'none',
    'resume_empty_show' => $resume_exists ? 'none' : 'flex',
    'name' => new RawHtml(e($name)),
    'avatar' => new RawHtml($avatar),
    'headline' => new RawHtml(e($headline)),
    'contacts' => new RawHtml($contacts),
    'completion' => $completion,
    'about_html' => new RawHtml($about_html),
    'education_html' => new RawHtml($education_html),
    'experience_html' => new RawHtml($experience_html),
    'skills_html' => new RawHtml($skills_html),
    'languages_html' => new RawHtml($languages_html),
    'certifications_html' => new RawHtml($certifications_html),
]);