<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

// Handle resume upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
    $file = $_FILES['resume'];
    $allowed = ['pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
        $upload_dir = __DIR__ . '/../../uploads/resumes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = 'resume_' . $user_id . '_' . time() . '.' . $ext;
        $path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $path)) {
            $db->prepare("UPDATE applicant_profiles SET resume = ? WHERE user_id = ?")->execute([$filename, $user_id]);
            $_SESSION['success'] = 'Resume uploaded successfully';
        } else {
            $_SESSION['error'] = 'Failed to upload resume';
        }
    } else {
        $_SESSION['error'] = 'Invalid file format or size exceeds 5MB';
    }
    header('Location: ?role=applicant&page=resume');
    exit;
}

$profile = $db->prepare("SELECT resume FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

$resume_name = $profile['resume'] ?? '';
$has_resume = !empty($resume_name);
// The current-resume box only makes sense when the file is actually on disk,
// not merely when a filename is recorded in the row.
$current_show = 'none';
$file_hint = 'No file selected.';

$uploaded = __DIR__ . '/../../uploads/resumes/' . basename($resume_name);
$file_exists = $has_resume && is_file($uploaded);
$uploaded_at = '';
$file_size = '';
if ($file_exists) {
    $ext = strtolower(pathinfo($uploaded, PATHINFO_EXTENSION));
    $uploaded_at = date('M j, Y', filemtime($uploaded));
    $bytes = filesize($uploaded);
    $file_size = $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : max(1, (int)round($bytes / 1024)) . ' KB';
    $file_type = strtoupper($ext);
    $current_show = 'block';
    $file_hint = 'Current file: ' . e($resume_name);
} else {
    $file_type = '';
}

render('applicant/resume.html', [
    'name' => new RawHtml(e($_SESSION['name'])),
    'has_resume' => $file_exists ? 'true' : 'false',
    'file_exists' => $file_exists ? 'true' : 'false',
    'resume_file' => $has_resume ? e($resume_name) : '',
    'file_type' => e($file_type),
    'file_size' => e($file_size),
    'file_date' => e($uploaded_at),
    'current_show' => $current_show,
    'current_resume' => $has_resume ? e($resume_name) : 'No resume uploaded yet.',
    'file_hint' => new RawHtml($file_hint),
    'notification' => new RawHtml(flash_message())
]);