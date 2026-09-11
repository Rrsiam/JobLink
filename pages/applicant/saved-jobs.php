<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

// Handle remove saved job
if (isset($_GET['remove'])) {
    $db->prepare("DELETE FROM saved_jobs WHERE applicant_id = ? AND job_id = ?")->execute([$user_id, $_GET['remove']]);
    header('Location: ?role=applicant&page=saved-jobs&success=Job removed from saved list');
    exit;
}

$saved = $db->prepare("
    SELECT j.*, u.name as employer_name 
    FROM saved_jobs sj 
    JOIN jobs j ON sj.job_id = j.id 
    JOIN users u ON j.employer_id = u.id 
    WHERE sj.applicant_id = ? 
    ORDER BY sj.created_at DESC
")->execute([$user_id])->fetchAll();

$html = '';
foreach ($saved as $job) {
    $is_expired = strtotime($job['deadline']) < time();
    $status_badge = $is_expired ? '<span class="badge badge-rejected">Expired</span>' : '<span class="badge badge-active">Active</span>';
    $html .= "
    <div class='job-card'>
        <div class='title'>{$job['title']}</div>
        <div class='company'>{$job['employer_name']}</div>
        <div class='meta'>
            <span>📍 {$job['location']}</span>
            <span>💰 " . ($job['salary_min'] ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']) : 'Negotiable') . "</span>
            <span>⏳ {$job['type']}</span>
        </div>
        <div style='font-size:13px;color:#64748b;margin:8px 0;'>
            Deadline: " . date('M d, Y', strtotime($job['deadline'])) . "
        </div>
        {$status_badge}
        <div style='margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;'>
            <a href='?role=applicant&page=job-details&id={$job['id']}' class='btn btn-primary btn-sm'>View Details</a>
            <a href='?role=applicant&page=job-details&id={$job['id']}&apply=1' class='btn btn-success btn-sm'>Apply Now</a>
            <a href='?role=applicant&page=saved-jobs&remove={$job['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Remove this job from saved list?\")'>Remove</a>
        </div>
    </div>";
}

render('applicant/saved-jobs.html', [
    'name' => $_SESSION['name'],
    'saved_count' => count($saved),
    'matching_count' => count($saved),
    'saved_job_list' => $html
]);