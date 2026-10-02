<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$app_id = $_GET['id'] ?? 0;
$employer_id = get_user_id();
$db = getDB();

$app = employer_get_application($app_id, $employer_id);

if (!$app) {
    header('Location: ?role=employer&page=applicants');
    exit;
}

$profile = $db->prepare("SELECT company_name FROM employer_profiles WHERE user_id = ?")
    ->execute([$employer_id])->fetch();

// The file the applicant sent with the application wins; otherwise the latest one
// on their profile is used.
$path = resume_resolve_path($app['resume_path']);
if ($path === '') {
    $path = resume_resolve_path($app['profile_resume']);
}

$back_url = '?role=employer&page=applicant-details&id=' . (int)$app_id;
$file_url = '?role=employer&page=resume-file&id=' . (int)$app_id;
$download_url = $file_url . '&download=1';
$detail_url = '?role=employer&page=applicant-details&id=' . (int)$app_id;

if ($path === '') {
    render('employer/resume.html', [
        'company_name' => $profile['company_name'] ?? 'My Company',
        'applicant_name' => $app['applicant_name'],
        'job_title' => $app['job_title'],
        'resume_file' => 'No resume on file',
        'resume_meta' => '',
        'notification' => flash_message(),
        'back_url' => $back_url,
        'file_url' => $file_url,
        'download_url' => $download_url,
        'doc_html' => '',
        'note_html' => '',
        'viewer_html' => '',
        'empty_html' => '<div class="rv-empty"><div class="rv-empty-icon">&#128196;</div>'
            . '<h3>No resume uploaded</h3>'
            . '<p class="text-muted">This applicant has not attached a resume, so only the details on file are shown.</p>'
            . '<a class="btn btn-primary" href="' . e($detail_url) . '">Back to application</a></div>',
    ]);
    exit;
}

$contacts = array_filter([
    (string)$app['applicant_email'],
    (string)$app['applicant_phone'],
    (string)($app['address'] ?? ''),
]);

$parts = resume_preview_parts(
    $path,
    (string)$app['applicant_name'],
    (string)($app['professional_title'] ?? ''),
    $contacts,
    $file_url,
    $download_url
);

render('employer/resume.html', [
    'company_name' => $profile['company_name'] ?? 'My Company',
    'applicant_name' => $app['applicant_name'],
    'job_title' => $app['job_title'],
    'resume_file' => basename($path),
    'resume_meta' => $parts['meta'],
    'notification' => flash_message(),
    'back_url' => $back_url,
    'file_url' => $file_url,
    'download_url' => $download_url,
    'doc_html' => $parts['doc'],
    'note_html' => $parts['note'],
    'viewer_html' => $parts['viewer'],
    'empty_html' => $parts['empty'],
]);
