<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

// Handle remove saved job
if (isset($_GET['remove'])) {
    $db->prepare("DELETE FROM saved_jobs WHERE applicant_id = ? AND job_id = ?")->execute([$user_id, (int)$_GET['remove']]);
    redirect_with_flash('?role=applicant&page=saved-jobs', 'Job removed from your saved list.');
}

$search = trim($_GET['search'] ?? '');
$saved = $db->prepare("
    SELECT j.*, u.name as employer_name 
    FROM saved_jobs sj 
    JOIN jobs j ON sj.job_id = j.id 
    JOIN users u ON j.employer_id = u.id 
    WHERE sj.applicant_id = ? 
    ORDER BY sj.created_at DESC
")->execute([$user_id])->fetchAll();

// The list is short enough to filter after the join, and it keeps the saved
// count and the filtered count consistent with each other.
$total_saved = count($saved);
if ($search !== '') {
    $needle = mb_strtolower($search);
    $saved = array_values(array_filter($saved, function ($job) use ($needle) {
        return mb_strpos(mb_strtolower($job['title']), $needle) !== false
            || mb_strpos(mb_strtolower((string)$job['employer_name']), $needle) !== false
            || mb_strpos(mb_strtolower((string)$job['location']), $needle) !== false;
    }));
}

$html = '';
foreach ($saved as $job) {
    $is_expired = strtotime($job['deadline']) < time();
    $status_badge = $is_expired ? '<span class="badge badge-rejected">Expired</span>' : '<span class="badge badge-active">Active</span>';
    $job_id = (int)$job['id'];
    $html .= "
    <div class='job-card'>
        <div class='title'>" . htmlspecialchars($job['title']) . "</div>
        <div class='company'>" . htmlspecialchars((string)$job['employer_name']) . "</div>
        <div class='meta'>
            <span>📍 " . htmlspecialchars((string)$job['location']) . "</span>
            <span>💰 " . ($job['salary_min'] ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']) : 'Negotiable') . "</span>
            <span>⏳ " . htmlspecialchars((string)$job['type']) . "</span>
        </div>
        <div style='font-size:13px;color:#64748b;margin:8px 0;'>
            Deadline: " . date('M d, Y', strtotime($job['deadline'])) . "
        </div>
        {$status_badge}
        <div style='margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;'>
            <a href='?role=applicant&page=job-details&id=$job_id' class='btn btn-primary btn-sm'>View Details</a>
            <a href='?role=applicant&page=job-details&id=$job_id&apply=1' class='btn btn-success btn-sm'>Apply Now</a>
            <a href='?role=applicant&page=saved-jobs&remove=$job_id' class='btn btn-danger btn-sm' onclick='return confirm(\"Remove this job from saved list?\")'>Remove</a>
        </div>
    </div>";
}
if (!$saved) {
    $html = $total_saved > 0
        ? "<div class='empty-state' style='grid-column:1/-1;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>"
            . "<div style='font-size:40px;margin-bottom:12px;'>🔍</div><h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Saved Jobs Match</h3>"
            . "<p style='font-size:14px;'>Try a different keyword, or <a href='?role=applicant&page=find-jobs' style='color:#2563eb;font-weight:600;'>browse more jobs</a>.</p></div>"
        : "<div class='empty-state' style='grid-column:1/-1;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>"
            . "<div style='font-size:40px;margin-bottom:12px;'>💾</div><h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Saved Jobs Yet</h3>"
            . "<p style='font-size:14px;'>Save jobs you are interested in and they will show up here. <a href='?role=applicant&page=find-jobs' style='color:#2563eb;font-weight:600;'>Find jobs →</a></p></div>";
}

render('applicant/saved-jobs.html', [
    'name' => $_SESSION['name'],
    'saved_count' => $total_saved,
    'matching_count' => count($saved),
    'saved_job_list' => new RawHtml($html),
    'search' => e($search),
    'notification' => flash_message()
]);