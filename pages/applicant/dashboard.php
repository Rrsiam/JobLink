<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

$user = get_user_data();

// Get profile
$profile = $db->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

// Profile completion
$profile_complete = 0;
$fields = ['professional_title', 'address', 'career_objective', 'education', 'experience', 'skills'];
foreach ($fields as $field) {
    if (!empty($profile[$field])) $profile_complete += 100 / count($fields);
}

// Get applications
$applications = $db->prepare("
    SELECT a.*, j.title as job_title, j.location, u.name as company_name 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users u ON j.employer_id = u.id 
    WHERE a.applicant_id = ? 
    ORDER BY a.created_at DESC
")->execute([$user_id])->fetchAll();

// Saved jobs count
$saved_count = $db->prepare("SELECT COUNT(*) FROM saved_jobs WHERE applicant_id = ?")->execute([$user_id])->fetchColumn();

// Recommendations
$recommended = $db->query("
    SELECT j.*, u.name as employer_name 
    FROM jobs j 
    JOIN users u ON j.employer_id = u.id 
    WHERE j.status = 'active' 
    ORDER BY RAND() 
    LIMIT 4
")->fetchAll();

$app_rows = '';
$badges = [
    'pending' => 'badge-pending',
    'under review' => 'badge-under-review',
    'shortlisted' => 'badge-shortlisted',
    'accepted' => 'badge-hired',
    'rejected' => 'badge-rejected',
    'hired' => 'badge-hired'
];
foreach ($applications as $app) {
    $badge = $badges[$app['status']] ?? 'badge-pending';
    $app_rows .= "<tr>
        <td>" . e($app['job_title']) . "</td>
        <td>" . e($app['company_name']) . "</td>
        <td>" . date('M d, Y', strtotime($app['created_at'])) . "</td>
        <td><span class='badge $badge'>" . e(ucfirst($app['status'])) . "</span></td>
        <td><a href='?role=applicant&page=application-details&id=" . (int)$app['id'] . "' class='btn btn-outline btn-sm'>View</a></td>
    </tr>";
}

$recommended_html = '';
foreach ($recommended as $job) {
    $salary = $job['salary_min']
        ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max'])
        : 'Negotiable';
    $recommended_html .= "
    <div class='job-card'>
        <div class='title'>" . e($job['title']) . "</div>
        <div class='company'>" . e($job['employer_name']) . "</div>
        <div class='meta'>
            <span>📍 " . e($job['location']) . "</span>
            <span>💰 " . e($salary) . "</span>
        </div>
        <a href='?role=applicant&page=job-details&id=" . (int)$job['id'] . "' class='btn btn-primary btn-sm'>View</a>
    </div>";
}

$shortlisted_count = count(array_filter($applications, function($a) {
    return $a['status'] === 'shortlisted';
}));

render('applicant/dashboard.html', [
    'name' => $user['name'],
    'notification' => count($applications) > 0 ? 'You have ' . count($applications) . ' active applications.' : 'Start applying for jobs today!',
    'profile_complete' => round($profile_complete),
    'total_apps' => count($applications),
    'shortlisted' => $shortlisted_count,
      'recommended' => new RawHtml($recommended_html),
      'application_rows' => new RawHtml($app_rows)
]);