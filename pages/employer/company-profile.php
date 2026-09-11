<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

$profile = $db->prepare("
    SELECT u.*, ep.* 
    FROM users u 
    JOIN employer_profiles ep ON u.id = ep.user_id 
    WHERE u.id = ?
")->execute([$user_id])->fetch();

if (!$profile) {
    header('Location: ?role=employer&page=dashboard');
    exit;
}

// Get jobs
$jobs = $db->prepare("SELECT * FROM jobs WHERE employer_id = ? ORDER BY created_at DESC")->execute([$user_id])->fetchAll();

$open_jobs = '';
foreach ($jobs as $job) {
    $apps = $db->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?")->execute([$job['id']])->fetchColumn();
    $open_jobs .= "
    <div class='job-card'>
        <div class='title'>{$job['title']}</div>
        <div class='meta'>
            <span>📌 {$job['type']}</span>
            <span>📍 {$job['location']}</span>
            <span>👥 {$apps} applicants</span>
        </div>
        <span class='badge badge-" . ($job['status'] === 'active' ? 'active' : 'closed') . "'>" . ucfirst($job['status']) . "</span>
        <a href='?role=employer&page=job-details&id={$job['id']}' class='btn btn-primary btn-sm'>View</a>
    </div>";
}

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

$profile_logo_html = $profile['logo']
    ? "<img src='uploads/logos/{$profile['logo']}' alt='{$profile['company_name']} logo' style='width:72px;height:72px;object-fit:contain;border-radius:12px;background:#fff;'>"
    : "<div style='width:72px;height:72px;background:#e2e8f0;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:24px;color:#475569;'>" . substr($profile['company_name'], 0, 2) . "</div>";

render('employer/company-profile.html', [
    'company_name' => $profile['company_name'],
    'initials' => substr($profile['company_name'], 0, 2),
    'profile_logo' => $profile_logo_html,
    'industry' => $profile['industry'],
    'location' => $profile['address'],
    'size' => $profile['company_size'] ?: 'Not specified',
    'website' => $profile['website'] ?: '#',
    'phone' => $profile['phone'] ?? '',
    'email' => $profile['email'],
    'about' => nl2br(htmlspecialchars($profile['description'] ?? '')),
    'benefits' => $profile['benefits'] ? str_replace("\n", '<br>', htmlspecialchars($profile['benefits'])) : 'Not specified',
    'founded' => '2018',
    'openings' => count($jobs),
    'total_applicants' => $total_applicants,
    'hires' => $hires,
    'open_jobs' => $open_jobs
]);