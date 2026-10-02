<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

// Without an application_id this serves the applicant's current resume. With one
// it serves the copy attached to that specific application, which may be older
// than the current upload. Either way the row is checked against the signed-in
// applicant so a crafted id cannot reach somebody else's file.
$application_id = (int)($_GET['application_id'] ?? 0);
$reference = '';

if ($application_id > 0) {
    $app = $db->prepare("SELECT resume_path FROM applications WHERE id = ? AND applicant_id = ?")
        ->execute([$application_id, $user_id])->fetch();
    if (!$app) {
        // Fail closed: never serve anything for an application this applicant
        // does not own.
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Application not found.';
        exit;
    }
    $reference = (string)$app['resume_path'];
}

if ($reference === '') {
    $reference = (string)$db->prepare("SELECT resume FROM applicant_profiles WHERE user_id = ?")
        ->execute([$user_id])->fetchColumn();
}

$path = resume_resolve_path($reference);

if ($path === '') {
    $_SESSION['error'] = 'No resume uploaded yet.';
    header('Location: ?role=applicant&page=resume');
    exit;
}

header('Content-Type: ' . resume_mime_type($path));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . resume_stream_filename($path) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;