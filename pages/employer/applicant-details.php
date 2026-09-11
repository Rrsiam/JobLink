<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$app_id = $_GET['id'] ?? 0;
$user_id = get_user_id();
$db = getDB();

$app = $db->prepare("
    SELECT a.*, u.name as applicant_name, u.email, u.phone, 
           j.title as job_title, j.location, ep.*
    FROM applications a 
    JOIN users u ON a.applicant_id = u.id 
    JOIN jobs j ON a.job_id = j.id 
    JOIN applicant_profiles ep ON u.id = ep.user_id 
    WHERE a.id = ? AND j.employer_id = ?
")->execute([$app_id, $user_id])->fetch();

if (!$app) {
    header('Location: ?role=employer&page=applicants');
    exit;
}

// Update status if action is sent
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $allowed = ['shortlisted', 'interview', 'accepted', 'rejected', 'hired'];
    if (in_array($action, $allowed)) {
        $db->prepare("UPDATE applications SET status = ? WHERE id = ?")->execute([$action, $app_id]);
        header('Location: ?role=employer&page=applicant-details&id=' . $app_id . '&success=Status updated');
        exit;
    }
}

render('employer/applicant-details.html', [
    'company_name' => 'My Company',
    'applicant_name' => $app['applicant_name'],
    'job_title' => $app['job_title'],
    'location' => $app['location'],
    'email' => $app['email'],
    'phone' => $app['phone'] ?: 'Not provided',
    'applied_date' => date('M d, Y', strtotime($app['created_at'])),
    'status' => ucfirst($app['status']),
    'status_class' => $app['status'],
    'objective' => $app['career_objective'] ?: 'No career objective provided.',
    'work_experience' => $app['experience'] ?: 'No experience provided.',
    'education' => $app['education'] ?: 'No education provided.',
    'skills' => $app['skills'] ?: 'No skills provided.',
    'cover_letter' => $app['cover_letter'] ?: 'No cover letter provided.'
]);