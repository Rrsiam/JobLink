<?php
require_once __DIR__ . '/../../config.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

// Get job details
$stmt = $db->prepare("
    SELECT j.*, u.name as employer_name, u.id as employer_id, c.name as category_name 
    FROM jobs j 
    LEFT JOIN users u ON j.employer_id = u.id 
    LEFT JOIN categories c ON j.category_id = c.id 
    WHERE j.id = ?
");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: ?page=find-jobs');
    exit;
}

// Get similar jobs
$similar = $db->prepare("
    SELECT j.*, u.name as employer_name 
    FROM jobs j 
    LEFT JOIN users u ON j.employer_id = u.id 
    WHERE j.category_id = ? AND j.id != ? AND j.status = 'active' 
    LIMIT 3
")->execute([$job['category_id'], $id])->fetchAll();

$similar_html = '';
foreach ($similar as $sj) {
    $similar_html .= "
    <div class='job-card'>
        <div class='title'>{$sj['title']}</div>
        <div class='company'>{$sj['employer_name']}</div>
        <div class='meta'><span>📍 {$sj['location']}</span></div>
        <a href='?page=job-details&id={$sj['id']}' class='btn btn-primary btn-sm'>View</a>
    </div>";
}

$tags = '';
if ($job['skills']) {
    foreach (explode(',', $job['skills']) as $skill) {
        $tags .= "<span>" . trim($skill) . "</span>";
    }
}

$responsibilities = '';
if ($job['responsibilities']) {
    $lines = explode("\n", $job['responsibilities']);
    foreach ($lines as $line) {
        if (trim($line)) {
            $responsibilities .= "<li>" . htmlspecialchars(trim($line)) . "</li>";
        }
    }
}

$benefits_html = '';
if ($job['benefits']) {
    $lines = explode("\n", $job['benefits']);
    foreach ($lines as $line) {
        if (trim($line)) {
            $benefits_html .= "<li>" . htmlspecialchars(trim($line)) . "</li>";
        }
    }
}

// Check if user is logged in and has saved this job
$is_saved = false;
if (is_logged_in() && get_user_role() === 'applicant') {
    $stmt = $db->prepare("SELECT id FROM saved_jobs WHERE applicant_id = ? AND job_id = ?");
    $stmt->execute([get_user_id(), $id]);
    $is_saved = $stmt->fetch() ? true : false;
}

render('guest/job-details.html', [
    'job_title' => $job['title'],
    'company' => $job['employer_name'],
    'location' => $job['location'],
    'salary' => ($job['salary_min'] ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']) : 'Negotiable'),
    'job_type' => $job['type'],
    'experience' => $job['experience'] ?: 'Not specified',
    'posted' => date('M d, Y', strtotime($job['created_at'])),
    'deadline' => date('M d, Y', strtotime($job['deadline'])),
    'about_text' => nl2br(htmlspecialchars($job['description'])),
    'responsibilities' => $responsibilities,
    'tags' => $tags,
    'benefits' => $benefits_html,
    'similar_jobs' => $similar_html,
    'job_id' => $job['id'],
    'is_saved' => $is_saved ? 'true' : 'false'
]);