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
$current_show = $has_resume ? 'block' : 'none';
$current_resume = $has_resume ? htmlspecialchars($resume_name) : 'No resume uploaded yet.';
$file_hint = $has_resume ? 'Current file: ' . htmlspecialchars($resume_name) : 'No file selected.';

render('applicant/resume.html', [
    'name' => $_SESSION['name'],
    'has_resume' => $has_resume ? 'true' : 'false',
    'resume_file' => $resume_name,
    'current_show' => $current_show,
    'current_resume' => $current_resume,
    'file_hint' => $file_hint,
    'notification' => flash_message()
]);