<?php
// Streams the resume of an applicant who applied to one of this employer's jobs.
// The parsed document on the resume page is the main view; the file itself is
// served here for the download button and for the embedded fallback. Ownership
// is checked before anything is read from disk.
require_once __DIR__ . '/../../config.php';
require_role('employer');

$app = employer_get_application($_GET['id'] ?? 0, get_user_id());

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
