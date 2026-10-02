<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

$profile = $db->prepare("SELECT * FROM employer_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

// Stats
$active_jobs = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'active'")->execute([$user_id])->fetchColumn();

$total_applicants = $db->prepare("
    SELECT COUNT(*) FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.employer_id = ?
")->execute([$user_id])->fetchColumn();

$hires = $db->prepare("
    SELECT COUNT(*) FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.employer_id = ? AND a.status = 'hired'
")->execute([$user_id])->fetchColumn();

// Recent applicants
$applicants = $db->prepare("
    SELECT a.*, u.name as applicant_name, u.phone, j.title as job_title 
    FROM applications a 
    JOIN users u ON a.applicant_id = u.id 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.employer_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 5
")->execute([$user_id])->fetchAll();

$recent_html = '<ul style="list-style:none;">';
foreach ($applicants as $app) {
    $recent_html .= "<li style='padding:8px 0;border-bottom:1px solid #f1f5f9;'>
        <a href='?role=employer&page=applicant-details&id=" . (int)$app['id'] . "'>" . e($app['applicant_name']) . "</a>
        - " . e($app['job_title']) . "
        <span class='badge badge-pending'>" . e(time_ago($app['created_at'])) . "</span>
    </li>";
}
$recent_html .= '</ul>';

// Active jobs
$jobs = $db->prepare("SELECT * FROM jobs WHERE employer_id = ? AND status = 'active' LIMIT 5")->execute([$user_id])->fetchAll();
$job_list_html = '<ul style="list-style:none;">';
foreach ($jobs as $job) {
    $apps = $db->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?")->execute([$job['id']])->fetchColumn();
    $job_list_html .= "<li style='padding:8px 0;border-bottom:1px solid #f1f5f9;'>
        <a href='?role=employer&page=job-details&id=" . (int)$job['id'] . "'>" . e($job['title']) . "</a>
        - " . (int)$apps . " apps
        <span class='badge badge-active'>Active</span>
    </li>";
}
$job_list_html .= '</ul>';

render('employer/dashboard.html', [
    'company_name' => $profile['company_name'] ?? 'My Company',
    'active_jobs' => $active_jobs,
    'total_applicants' => $total_applicants,
    'hires_this_year' => $hires,
    'recent_applicants' => new RawHtml($recent_html),
    'active_jobs_list' => new RawHtml($job_list_html),
    'notification' => new RawHtml(flash_message())
]);

function time_ago($timestamp) {
    $diff = time() - strtotime($timestamp);
    // A timestamp can land slightly ahead of "now" when the stored value was
    // written under a different timezone, which used to render as "-12408s ago".
    if ($diff < 60) return $diff < 0 ? 'just now' : $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}