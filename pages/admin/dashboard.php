<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Stats
$total_applicants = $db->query("SELECT COUNT(*) FROM users WHERE role = 'applicant'")->fetchColumn();
$total_employers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employer'")->fetchColumn();
$total_admins = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$total_jobs = $db->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$total_apps = $db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$hires = $db->query("SELECT COUNT(*) FROM applications WHERE status IN ('accepted', 'hired')")->fetchColumn();

// Recent registrations
$recent = $db->query("
    SELECT * FROM users 
    WHERE role != 'admin' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

$recent_html = '<ul style="list-style:none;margin-top:12px;">';
foreach ($recent as $u) {
    $recent_html .= "<li style='padding:6px 0;border-bottom:1px solid #f1f5f9;'>
        {$u['name']} ({$u['role']}) - " . date('M d, Y', strtotime($u['created_at'])) . "
    </li>";
}
$recent_html .= '</ul>';

// Pending employers
$pending = $db->query("
    SELECT u.*, ep.company_name 
    FROM users u 
    JOIN employer_profiles ep ON u.id = ep.user_id 
    WHERE u.role = 'employer' AND u.status = 'pending'
")->fetchAll();

$pending_html = '<ul style="list-style:none;margin-top:12px;">';
foreach ($pending as $p) {
    $pending_html .= "<li style='padding:6px 0;border-bottom:1px solid #f1f5f9;'>
        {$p['company_name']} - {$p['email']}
        <a href='?role=admin&page=employers&approve={$p['id']}' class='btn btn-success btn-sm'>Approve</a>
    </li>";
}
$pending_html .= '</ul>';

render('admin/dashboard.html', [
    'total_applicants' => number_format($total_applicants),
    'total_employers' => number_format($total_employers),
    'total_admins' => number_format($total_admins),
    'job_postings' => number_format($total_jobs),
    'total_applications' => number_format($total_apps),
    'successful_hires' => number_format($hires),
    'recent_registrations' => $recent_html,
    'pending_approvals' => $pending_html
]);