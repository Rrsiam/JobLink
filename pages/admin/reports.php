<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_jobs = $db->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$total_apps = $db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$active_companies = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employer' AND status = 'active'")->fetchColumn();
$hires = $db->query("SELECT COUNT(*) FROM applications WHERE status IN ('accepted', 'hired')")->fetchColumn();

render('admin/reports.html', [
    'total_users' => number_format($total_users),
    'job_postings' => number_format($total_jobs),
    'total_applications' => number_format($total_apps),
    'active_companies' => number_format($active_companies)
]);