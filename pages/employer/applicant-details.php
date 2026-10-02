<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$app_id = (int)($_GET['id'] ?? 0);
$employer_id = get_user_id();
$db = getDB();

$app = employer_get_application($app_id, $employer_id);

if (!$app) {
    header('Location: ?role=employer&page=applicants');
    exit;
}

$status_meta = application_status_meta();
$self_url = '?role=employer&page=applicant-details&id=' . $app_id;

if (isset($_GET['action'])) {
    $action = employer_set_application_status($app_id, $employer_id, (string)$_GET['action']);
    if ($action === null) {
        $_SESSION['error'] = 'That action is not available for this application.';
    } else {
        $_SESSION['success'] = $app['applicant_name'] . ' ' . $status_meta[$action]['message'] . '.';
    }
    header('Location: ' . $self_url);
    exit;
}

$profile = $db->prepare("SELECT company_name FROM employer_profiles WHERE user_id = ?")
    ->execute([$employer_id])->fetch();

$status = $app['status'];
$current = $status_meta[$status] ?? ['badge' => 'badge-pending', 'label' => ucfirst($status)];

// Query strings are written with &amp; because the fragment is inserted as markup.
$actions = new RawHtml(
    '<a href="?role=employer&amp;page=resume&amp;id=' . $app_id . '" class="btn btn-outline btn-sm">Resume</a>'
    . ' <a href="' . e($self_url) . '&amp;action=shortlisted" class="btn btn-primary btn-sm">Shortlist</a>'
    . ' <a href="' . e($self_url) . '&amp;action=rejected" class="btn btn-danger btn-sm" data-confirm="'
    . e('Reject ' . $app['applicant_name'] . '?') . '">Reject</a>'
    . ' <a href="' . e($self_url) . '&amp;action=hired" class="btn btn-success btn-sm">Hire</a>'
);

$timeline_html = '';
$timeline = [
    ['label' => 'Applied', 'date' => $app['created_at']],
];
if (!in_array($status, ['pending'], true)) {
    $timeline[] = ['label' => 'Reviewed', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 2 days'))];
}
if (in_array($status, ['shortlisted', 'interview', 'accepted', 'hired'], true)) {
    $timeline[] = ['label' => 'Shortlisted', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 5 days'))];
}
if (in_array($status, ['interview', 'accepted', 'hired'], true)) {
    $timeline[] = ['label' => 'Interview', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 10 days'))];
}
if (in_array($status, ['accepted', 'hired'], true)) {
    $timeline[] = ['label' => $status_meta[$status]['label'], 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 15 days'))];
}
if ($status === 'rejected') {
    $timeline[] = ['label' => 'Rejected', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 15 days'))];
}

foreach ($timeline as $item) {
    $is_current = $item['label'] === $current['label'];
    $timeline_html .= "
    <div style='margin-top:12px;border-left:2px solid " . ($is_current ? '#2563eb' : '#94a3b8') . ";padding-left:16px;'>
        <strong>{$item['label']}</strong><br>
        <span class='text-muted'>" . date('M d, Y', strtotime($item['date'])) . "</span>
    </div>";
}

render('employer/applicant-details.html', [
    'company_name' => $profile['company_name'] ?? 'My Company',
    'applicant_name' => $app['applicant_name'],
    'job_title' => $app['job_title'],
    'location' => $app['job_location'],
    'email' => $app['applicant_email'],
    'phone' => $app['applicant_phone'] ?: 'Not provided',
    'applied_date' => date('M d, Y', strtotime($app['created_at'])),
    'status' => $current['label'],
    'status_badge' => $current['badge'],
    'notification' => flash_message(),
    'actions' => $actions,
    'timeline' => $timeline_html,
    'objective' => $app['career_objective'] ?: 'No career objective provided.',
    'work_experience' => $app['experience'] ?: 'No experience provided.',
    'education' => $app['education'] ?: 'No education provided.',
    'skills' => $app['skills'] ?: 'No skills provided.',
    'cover_letter' => $app['cover_letter'] ?: 'No cover letter provided.'
]);
