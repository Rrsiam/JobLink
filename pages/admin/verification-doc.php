<?php
// Streams the identity document an applicant uploaded at registration. These are
// sensitive, and uploads/documents sits under the document root, so the file is
// only served here: the admin role is checked first, the profile row must exist,
// and the stored reference is reduced to a basename. Everything the dashboard and
// the applicants list link to goes through this page instead of the raw path.
require_once __DIR__ . '/../../config.php';
require_role('admin');

$user_id = (int)($_GET['user_id'] ?? 0);
$reference = '';

if ($user_id > 0) {
    $reference = (string)getDB()
        ->prepare("SELECT verification_doc FROM applicant_profiles WHERE user_id = ?")
        ->execute([$user_id])->fetchColumn();
}

$path = '';
if ($reference !== '') {
    $candidate = __DIR__ . '/../../uploads/documents/' . basename(str_replace('\\', '/', $reference));
    if (is_file($candidate)) {
        $path = $candidate;
    }
}

if ($path === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Document not found.';
    exit;
}

// Only the three types auth.php accepts at upload time are served.
$mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="verification_document.' . ($mimes[$ext] ? $ext : 'bin') . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
