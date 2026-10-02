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
    $action = $_GET['action'];
    $back = '?role=employer&page=job-details&id=' . (int)$job_id;
    if ($action === 'close') {
        $db->prepare("UPDATE jobs SET status = 'closed' WHERE id = ? AND employer_id = ?")->execute([$job_id, $user_id]);
        redirect_with_flash($back, 'Job closed. It is no longer accepting applications.');
    }
    if ($action === 'reopen') {
        $db->prepare("UPDATE jobs SET status = 'active' WHERE id = ? AND employer_id = ?")->execute([$job_id, $user_id]);
        redirect_with_flash($back, 'Job reopened and accepting applications again.');
    }
    if ($action === 'delete') {
        $db->prepare("DELETE FROM applications WHERE job_id = ?")->execute([$job_id]);
        $db->prepare("DELETE FROM saved_jobs WHERE job_id = ?")->execute([$job_id]);
        $db->prepare("DELETE FROM jobs WHERE id = ? AND employer_id = ?")->execute([$job_id, $user_id]);
        redirect_with_flash('?role=employer&page=manage-jobs', 'Job deleted along with its applications.');
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

$id = (int)$job_id;
$is_open = $job['status'] === 'active';

$job_actions = "<a href='?role=employer&page=edit-job&id=$id' class='btn btn-outline btn-sm'>Edit Job</a>";
if ($is_open) {
    $job_actions .= " <a href='?role=employer&page=job-details&id=$id&action=close' class='btn btn-warning btn-sm' onclick=\"return confirm('Close this job? Applicants will no longer be able to apply.')\">Close Job</a>";
} else {
    $job_actions .= " <a href='?role=employer&page=job-details&id=$id&action=reopen' class='btn btn-success btn-sm' onclick=\"return confirm('Reopen this job?')\">Reopen Job</a>";
}
$job_actions .= " <a href='?role=employer&page=job-details&id=$id&action=delete' class='btn btn-danger btn-sm' onclick=\"return confirm('Delete this job? This also removes its applications.')\">Delete</a>";

render('employer/job-details.html', [
    'company_name' => 'My Company',
    'job_title' => $job['title'],
    'category' => $job['category_name'] ?: 'Uncategorized',
    'location' => $job['location'],
    'job_type' => $job['type'],
    'posted_date' => date('M d, Y', strtotime($job['created_at'])),
    'applicants' => $app_count,
    'status' => ucfirst($job['status']),
    'status_class' => strtolower(str_replace(' ', '-', $job['status'])),
    'description' => new RawHtml(nl2br(e($job['description']))),
    'responsibilities' => new RawHtml($responsibilities),
    'education' => $job['education'] ?: 'Not specified',
    'experience' => $job['experience'] ?: 'Not specified',
    'skills' => $job['skills'] ? str_replace(',', ', ', $job['skills']) : 'Not specified',
    'benefits' => new RawHtml($benefits_html),
    'deadline' => date('M d, Y', strtotime($job['deadline'])),
    'job_id' => $job['id'],
    'notification' => new RawHtml(flash_message()),
    'job_actions' => new RawHtml($job_actions)
]);