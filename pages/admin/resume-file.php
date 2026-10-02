<?php
// Streams the resume attached to an application for the moderation team.
// Applicants keep ownership of their file, but an admin reviewing an
// application needs the same read access the employer has. The file is only
// served after the application row has been found, and the reference is
// reduced to a basename so a stored value cannot escape uploads/resumes.
require_once __DIR__ . '/../../config.php';
require_role('admin');

$app_id = (int)($_GET['application_id'] ?? 0);
$app = $app_id > 0 ? admin_get_application($app_id) : null;

if (!$app) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Application not found.';
    exit;
}

$path = resume_resolve_path($app['resume_path']);
if ($path === '') {
    $path = resume_resolve_path($app['profile_resume']);
}

if ($path === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Resume not found.';
    exit;
}

$disposition = isset($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: ' . resume_mime_type($path));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . resume_stream_filename($path) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
