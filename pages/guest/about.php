<?php
require_once __DIR__ . '/../../config.php';

$db = getDB();

$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'applicant' AND status = 'active') as applicants,
        (SELECT COUNT(*) FROM users WHERE role = 'employer' AND status = 'active') as employers,
        (SELECT COUNT(*) FROM jobs WHERE status = 'active') as jobs,
        (SELECT COUNT(*) FROM applications WHERE status IN ('accepted', 'hired')) as hires
")->fetch();

render('guest/about.html', [
    'total_jobs' => number_format($stats['jobs'] ?? 0),
    'registered_applicants' => number_format($stats['applicants'] ?? 0),
    'verified_employers' => number_format($stats['employers'] ?? 0),
    'successful_hires' => number_format($stats['hires'] ?? 0)
]);