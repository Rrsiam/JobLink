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

$name = trim((string)($profile['company_name'] ?? ''));
$industry = trim((string)($profile['industry'] ?? ''));
$address = trim((string)($profile['address'] ?? ''));
$website = trim((string)($profile['website'] ?? ''));
$size = trim((string)($profile['company_size'] ?? ''));
$description = trim((string)($profile['description'] ?? ''));
$phone = trim((string)($profile['phone'] ?? ''));
$email = trim((string)($profile['email'] ?? ''));
$verified = !empty($profile['verified']);
if ($name === '') {
    $name = 'Your company';
}

$logo = trim((string)($profile['logo'] ?? ''));
$logo_exists = $logo !== '' && is_file(__DIR__ . '/../../uploads/logos/' . $logo);
// Initials come from the first two letters or digits, so a name that starts
// with punctuation still gets a sensible tile rather than a stray symbol.
$initials = mb_strtoupper(mb_substr((string)preg_replace('/[^\p{L}\p{N}]+/u', '', $name), 0, 2));
if ($initials === '') {
    $initials = 'CO';
}
$logo_html = $logo_exists
    ? '<img src="uploads/logos/' . e($logo) . '" alt="' . e($name) . ' logo">'
    : e($initials);

// Page controllers are included rather than include_once'd, so the small
// builders below are closures: a repeated include would fatal on a
// redeclared function.
$dash = function ($value) {
    $value = trim((string)$value);
    return $value !== '' ? e($value) : '<span class="cp-void">&mdash;</span>';
};

// Short factual line under the company name. The address is left out because
// it is usually a long geocoded string; it has its own field below. When
// nothing short is filled in it points at the gap rather than leaving an empty
// band.
$summary_bits = array_filter([$industry, $size], 'strlen');
if ($summary_bits === [] && $address !== '') {
    $summary_bits = [$address];
}
$summary_html = $summary_bits !== []
    ? e(implode(' &middot; ', $summary_bits))
    : '<span class="cp-void">Add an industry, location and company size so candidates know who you are</span>';

$about_html = $description !== ''
    ? nl2br(e($description))
    : '<span class="cp-empty">No company description yet.</span>';

// Company details card: one definition list, so the grid is real markup
// rather than two lists stacked to fake two columns.
$details_html = '';
$member_since = strtotime((string)($profile['created_at'] ?? ''));
$detail_fields = [
    'Industry'     => $industry,
    'Company Size' => $size,
    'Headquarters' => $address,
    'Member Since' => $member_since === false ? '' : date('M j, Y', $member_since),
];
foreach ($detail_fields as $label => $value) {
    $details_html .= '<div><dt>' . e($label) . '</dt><dd>' . $dash($value) . '</dd></div>';
}

// Contact rows: real links where a target exists, and blank entries are
// dropped so the card never shows placeholders.
$contacts_html = '';
$contact_rows = [
    ['&#127760;', 'Website', $website !== '' ? '<a class="cp-link" href="' . e(preg_match('#^https?://#i', $website) ? $website : 'http://' . $website) . '" rel="noopener noreferrer" target="_blank">' . e($website) . '</a>' : ''],
    ['&#128222;', 'Phone', $phone !== '' ? '<a class="cp-link" href="tel:' . e(preg_replace('/[^0-9+]/', '', $phone)) . '">' . e($phone) . '</a>' : ''],
    ['&#9993;', 'HR Email', $email !== '' ? '<a class="cp-link" href="mailto:' . e($email) . '">' . e($email) . '</a>' : ''],
];
foreach ($contact_rows as [$icon, $label, $value_html]) {
    if ($value_html === '') {
        continue;
    }
    $contacts_html .= '<div class="cp-contact">'
        . '<span class="cp-contact-icon">' . $icon . '</span>'
        . '<span class="cp-contact-body">'
        . '<span class="cp-contact-label">' . e($label) . '</span>'
        . '<span class="cp-contact-value">' . $value_html . '</span>'
        . '</span></div>';
}
if ($contacts_html === '') {
    $contacts_html = '<span class="cp-empty">No contact details added yet.</span>';
}

render('employer/company-profile.html', [
    'company_name' => new RawHtml(e($name)),
    'logo'         => new RawHtml($logo_html),
    'verified_badge' => new RawHtml($verified ? '<span class="cp-verified">&#10004; Verified</span>' : ''),
    'summary'      => new RawHtml($summary_html),
    'about'        => new RawHtml($about_html),
    'details'      => new RawHtml($details_html),
    'contacts'     => new RawHtml($contacts_html),
    'flash'        => new RawHtml(flash_message()),
]);
