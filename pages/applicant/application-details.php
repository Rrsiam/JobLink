<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$app_id = $_GET['id'] ?? 0;
$user_id = get_user_id();
$db = getDB();

// Handle withdraw application (only allowed while not in a terminal state)
if (isset($_GET['withdraw'])) {
    $stmt = $db->prepare("SELECT status FROM applications WHERE id = ? AND applicant_id = ?");
    $stmt->execute([$app_id, $user_id]);
    $current = $stmt->fetch();
    if ($current && !in_array($current['status'], ['accepted', 'hired', 'rejected'])) {
        $db->prepare("DELETE FROM applications WHERE id = ? AND applicant_id = ?")->execute([$app_id, $user_id]);
        $_SESSION['success'] = 'Application withdrawn successfully.';
    } else {
        $_SESSION['error'] = 'This application can no longer be withdrawn.';
    }
    header('Location: ?role=applicant&page=applications');
    exit;
}

$app = $db->prepare("
    SELECT a.id, a.status, a.created_at, a.cover_letter, a.job_id, a.resume_path,
           j.title, j.location, j.salary_min, j.salary_max, j.type, j.deadline,
           u.name as company_name, u.id as employer_id
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users u ON j.employer_id = u.id 
    WHERE a.id = ? AND a.applicant_id = ?
")->execute([$app_id, $user_id])->fetch();

if (!$app) {
    header('Location: ?role=applicant&page=applications');
    exit;
}

$timeline_html = '';
$timeline = [
    ['status' => 'Applied', 'date' => $app['created_at'], 'description' => 'Application submitted successfully.'],
];
if ($app['status'] !== 'pending') {
    $timeline[] = ['status' => 'Under Review', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 2 days')), 'description' => 'Recruiter acknowledged your application.'];
}
if (in_array($app['status'], ['shortlisted', 'interview', 'accepted', 'hired'])) {
    $timeline[] = ['status' => 'Shortlisted', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 5 days')), 'description' => 'You have been shortlisted for further review.'];
}
if (in_array($app['status'], ['interview', 'accepted', 'hired'])) {
    $timeline[] = ['status' => 'Interview', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 10 days')), 'description' => 'Interview scheduled with the hiring team.'];
}
if (in_array($app['status'], ['accepted', 'hired'])) {
    $timeline[] = ['status' => 'Accepted', 'date' => date('Y-m-d H:i:s', strtotime($app['created_at'] . ' + 15 days')), 'description' => 'Congratulations! You have been selected for the position.'];
}

foreach ($timeline as $item) {
    $is_current = $item['status'] === 'Shortlisted' && $app['status'] === 'shortlisted';
    $timeline_html .= "
    <div style='margin-top:12px;border-left:2px solid " . ($is_current ? '#2563eb' : '#94a3b8') . ";padding-left:16px;'>
        <strong>{$item['status']}</strong> — {$item['description']}<br>
        <span class='text-muted'>" . date('M d, Y', strtotime($item['date'])) . "</span>
    </div>";
}

$user = get_user_data();
$profile = $db->prepare("SELECT resume FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

// Build resume download link
$resume_display = 'No resume on file';
$resume_button = "<a href='?role=applicant&page=resume' class='btn btn-outline btn-sm'>Upload Resume</a>";
$has_resume = false;
if (!empty($app['resume_path'])) {
    $resume_display = basename($app['resume_path']);
    $resume_button = "<a href='" . htmlspecialchars($app['resume_path']) . "' class='btn btn-outline btn-sm' download>Download Resume</a>";
    $has_resume = true;
} elseif (!empty($profile['resume'])) {
    $resume_display = $profile['resume'];
    $resume_button = "<a href='uploads/resumes/" . htmlspecialchars($profile['resume']) . "' class='btn btn-outline btn-sm' download>Download Resume</a>";
    $has_resume = true;
}

render('applicant/application-details.html', [
    'name' => $_SESSION['name'],
    'job_title' => $app['title'],
    'company' => $app['company_name'],
    'location' => $app['location'],
    'salary' => ($app['salary_min'] ? number_format($app['salary_min']) . ' - ' . number_format($app['salary_max']) : 'Negotiable'),
    'job_type' => $app['type'],
    'applied_date' => date('M d, Y', strtotime($app['created_at'])),
    'deadline' => date('M d, Y', strtotime($app['deadline'])),
    'status' => ucfirst($app['status']),
    'status_class' => $app['status'],
    'under_review_date' => date('M d, Y', strtotime($app['created_at'] . ' + 2 days')),
    'shortlisted_date' => date('M d, Y', strtotime($app['created_at'] . ' + 5 days')),
    'email' => $user['email'],
    'phone' => $user['phone'] ?: 'Not provided',
    'job_link' => '?role=applicant&page=job-details&id=' . $app['job_id'],
    'resume_button' => $resume_button,
    'has_resume' => $has_resume ? 'true' : 'false',
    'resume_file' => $resume_display,
    'withdraw_link' => '?role=applicant&page=application-details&id=' . $app_id . '&withdraw=1',
    'cover_letter' => $app['cover_letter'] ?: 'No cover letter provided.',
    'notes' => 'Your application is being reviewed.',
    'notification' => flash_message(),
    'timeline' => $timeline_html
]);