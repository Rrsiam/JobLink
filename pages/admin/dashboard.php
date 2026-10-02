<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Stats
$total_applicants = $db->query("SELECT COUNT(*) FROM users WHERE role = 'applicant'")->fetchColumn();
$total_employers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employer'")->fetchColumn();
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
        " . e($u['name']) . " (" . e($u['role']) . ") - " . date('M d, Y', strtotime($u['created_at'])) . "
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

// Pending applicants
$pending_apps = $db->query("
    SELECT u.*, ap.verification_doc 
    FROM users u 
    LEFT JOIN applicant_profiles ap ON u.id = ap.user_id 
    WHERE u.role = 'applicant' AND u.status = 'pending'
")->fetchAll();

$pending_html = '<ul style="list-style:none;margin-top:12px;">';
foreach ($pending as $p) {
    $pending_html .= "<li style='padding:6px 0;border-bottom:1px solid #f1f5f9;'>
        🏢 " . e($p['company_name']) . " - " . e($p['email']) . "
        <a href='?role=admin&page=employers&approve=" . (int)$p['id'] . "' class='btn btn-success btn-sm'>Approve</a>
    </li>";
}
foreach ($pending_apps as $pa) {
    $doc_link = $pa['verification_doc']
        ? "<a href='?role=admin&page=verification-doc&amp;user_id=" . (int)$pa['id'] . "' class='btn btn-outline btn-sm'>View Doc</a>"
        : '';
    $pending_html .= "<li style='padding:6px 0;border-bottom:1px solid #f1f5f9;'>
        👤 " . e($pa['name']) . " - " . e($pa['email']) . " {$doc_link}
        <a href='?role=admin&page=applicants&approve=" . (int)$pa['id'] . "' class='btn btn-success btn-sm'>Approve</a>
    </li>";
}
if (empty($pending) && empty($pending_apps)) {
    $pending_html .= "<li style='padding:6px 0;color:#64748b;'>No pending approvals. 🎉</li>";
}
$pending_html .= '</ul>';

render('admin/dashboard.html', [
    'total_applicants' => number_format($total_applicants),
    'total_employers' => number_format($total_employers),
    'job_postings' => number_format($total_jobs),
    'total_applications' => number_format($total_apps),
    'successful_hires' => number_format($hires),
    'recent_registrations' => new RawHtml($recent_html),
    'pending_approvals' => new RawHtml($pending_html)
]);