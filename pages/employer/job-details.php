<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$job_id = $_GET['id'] ?? 0;
$user_id = get_user_id();
$db = getDB();

$job = $db->prepare("
    SELECT j.*, c.name as category_name 
    FROM jobs j 
    LEFT JOIN categories c ON j.category_id = c.id 
    WHERE j.id = ? AND j.employer_id = ?
")->execute([$job_id, $user_id])->fetch();

if (!$job) {
    header('Location: ?role=employer&page=manage-jobs');
    exit;
}

// Handle actions
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'close') {
        $db->prepare("UPDATE jobs SET status = 'closed' WHERE id = ?")->execute([$job_id]);
        header('Location: ?role=employer&page=job-details&id=' . $job_id . '&success=Job closed');
        exit;
    }
    if ($_GET['action'] === 'delete') {
        $db->prepare("DELETE FROM jobs WHERE id = ?")->execute([$job_id]);
        header('Location: ?role=employer&page=manage-jobs&success=Job deleted');
        exit;
    }
}

$app_count = $db->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?")->execute([$job_id])->fetchColumn();

$responsibilities = '';
if ($job['responsibilities']) {
    foreach (explode("\n", $job['responsibilities']) as $line) {
        if (trim($line)) {
            $responsibilities .= "<li>" . htmlspecialchars(trim($line)) . "</li>";
        }
    }
}

$benefits_html = '';
if ($job['benefits']) {
    foreach (explode("\n", $job['benefits']) as $line) {
        if (trim($line)) {
            $benefits_html .= "<li>" . htmlspecialchars(trim($line)) . "</li>";
        }
    }
}

render('employer/job-details.html', [
    'company_name' => 'My Company',
    'job_title' => $job['title'],
    'category' => $job['category_name'] ?: 'Uncategorized',
    'location' => $job['location'],
    'job_type' => $job['type'],
    'posted_date' => date('M d, Y', strtotime($job['created_at'])),
    'applicants' => $app_count,
    'status' => ucfirst($job['status']),
    'status_class' => $job['status'],
    'description' => nl2br(htmlspecialchars($job['description'])),
    'responsibilities' => $responsibilities,
    'education' => $job['education'] ?: 'Not specified',
    'experience' => $job['experience'] ?: 'Not specified',
    'skills' => $job['skills'] ? str_replace(',', ', ', $job['skills']) : 'Not specified',
    'benefits' => $benefits_html,
    'deadline' => date('M d, Y', strtotime($job['deadline'])),
    'job_id' => $job['id']
]);