<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Headline totals. Admins are staff, not members, so the user figure excludes them.
$total_users = $db->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$total_jobs = $db->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$total_apps = $db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$active_companies = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employer' AND status = 'active'")->fetchColumn();

// Last six month buckets, oldest first, as YYYY-MM keys.
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("first day of -$i month"));
}
$month_labels = [];
foreach ($months as $m) {
    $month_labels[] = date('M y', strtotime($m . '-01'));
}
$from = $months[0] . '-01 00:00:00';
$to = date('Y-m-t 23:59:59', strtotime($months[count($months) - 1] . '-01'));

// User growth: applicants and employers registered each month.
$growth_rows = [];
foreach ($db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket, role AS suffix, COUNT(*) AS n
     FROM users
     WHERE role IN ('applicant', 'employer') AND created_at BETWEEN '$from' AND '$to'
     GROUP BY bucket, role"
)->fetchAll() as $r) {
    $growth_rows[$r['bucket'] . '|' . $r['suffix']] = (int)$r['n'];
}
$applicant_growth = [];
$employer_growth = [];
foreach ($months as $m) {
    $applicant_growth[] = $growth_rows[$m . '|applicant'] ?? 0;
    $employer_growth[] = $growth_rows[$m . '|employer'] ?? 0;
}

// Job postings: posted versus closed each month.
$job_rows = [];
foreach ($db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket,
            COUNT(*) AS posted,
            SUM(status = 'closed') AS closed
     FROM jobs
     WHERE created_at BETWEEN '$from' AND '$to'
     GROUP BY bucket"
)->fetchAll() as $r) {
    $job_rows[$r['bucket'] . '|posted'] = (int)$r['posted'];
    $job_rows[$r['bucket'] . '|closed'] = (int)$r['closed'];
}
$job_posted = [];
$job_closed = [];
foreach ($months as $m) {
    $job_posted[] = $job_rows[$m . '|posted'] ?? 0;
    $job_closed[] = $job_rows[$m . '|closed'] ?? 0;
}

render('admin/reports.html', [
    'total_users' => number_format($total_users),
    'job_postings' => number_format($total_jobs),
    'total_applications' => number_format($total_apps),
    'active_companies' => number_format($active_companies),
    'period' => e(date('M y', strtotime($months[0] . '-01')) . ' – ' . date('M y', strtotime($months[count($months) - 1] . '-01'))),
    'growth_chart' => new RawHtml(report_bar_chart($month_labels, [
        ['name' => 'Applicants', 'desc' => 'Job seekers who signed up', 'color' => '#2563eb', 'values' => $applicant_growth],
        ['name' => 'Employers', 'desc' => 'Companies that posted jobs', 'color' => '#38bdf8', 'values' => $employer_growth],
    ], 'No registrations in the last six months')),
    'jobs_chart' => new RawHtml(report_bar_chart($month_labels, [
        ['name' => 'Posted', 'desc' => 'New jobs created', 'color' => '#0f766e', 'values' => $job_posted],
        ['name' => 'Closed', 'desc' => 'Jobs no longer open', 'color' => '#fbbf24', 'values' => $job_closed],
    ], 'No jobs posted in the last six months')),
]);