<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

// Filter by status
$status_filter = $_GET['status'] ?? '';
$params = [$user_id];
$sql = "SELECT a.*, j.title as job_title, j.location, u.name as company_name 
        FROM applications a 
        JOIN jobs j ON a.job_id = j.id 
        JOIN users u ON j.employer_id = u.id 
        WHERE a.applicant_id = ?";

if ($status_filter && $status_filter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get counts for filters
$counts = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM applications 
    WHERE applicant_id = ? 
    GROUP BY status
")->execute([$user_id])->fetchAll();
$status_counts = [];
foreach ($counts as $c) {
    $status_counts[$c['status']] = $c['count'];
}

$rows = '';
$badges = [
    'pending' => 'badge-pending',
    'under review' => 'badge-under-review',
    'shortlisted' => 'badge-shortlisted',
    'accepted' => 'badge-hired',
    'rejected' => 'badge-rejected',
    'hired' => 'badge-hired'
];

$now = date('Y-m-d');

// Salary/deadline for table columns
$jbs = $db->query("SELECT id, salary_min, salary_max, deadline FROM jobs")->fetchAll();
$job_info = [];
foreach ($jbs as $j) {
    $job_info[$j['id']] = $j;
}

foreach ($applications as $app) {
    $badge = $badges[$app['status']] ?? 'badge-pending';
    $jf = $job_info[$app['job_id']] ?? [];
    $salary = (!empty($jf['salary_min']) && !empty($jf['salary_max']))
        ? '$' . number_format($jf['salary_min']) . ' - $' . number_format($jf['salary_max'])
        : 'Negotiable';
    $deadline = !empty($jf['deadline']) ? date('M d, Y', strtotime($jf['deadline'])) : '—';
    $rows .= "<tr>
        <td>{$app['job_title']}</td>
        <td>{$app['company_name']}</td>
        <td>{$app['location']}</td>
        <td>{$salary}</td>
        <td>" . date('M d, Y', strtotime($app['created_at'])) . "</td>
        <td>{$deadline}</td>
        <td><span class='badge $badge'>" . ucfirst($app['status']) . "</span></td>
        <td><a href='?role=applicant&page=application-details&id={$app['id']}' class='btn btn-outline btn-sm'>View</a></td>
    </tr>";
}

// Build filter buttons with counts + active state
$total = array_sum($status_counts);
$filters = [
    'all' => 'All',
    'pending' => 'Pending',
    'under review' => 'Under Review',
    'shortlisted' => 'Shortlisted',
    'accepted' => 'Accepted',
    'rejected' => 'Rejected'
];
$filter_buttons = '';
foreach ($filters as $key => $label) {
    $count = ($key === 'all') ? $total : ($status_counts[$key] ?? 0);
    $active = ($status_filter === $key) || ($status_filter === '' && $key === 'all');
    $cls = $active ? 'btn-primary' : 'btn-outline';
    $filter_buttons .= "<a href='?role=applicant&page=applications&status=$key' class='btn $cls btn-sm'>$label $count</a>";
}

render('applicant/applications.html', [
    'name' => $_SESSION['name'],
    'application_rows' => $rows,
    'showing' => count($applications),
    'total_apps' => $total,
    'filter_buttons' => $filter_buttons,
    'pending_count' => $status_counts['pending'] ?? 0,
    'review_count' => $status_counts['under review'] ?? 0,
    'shortlisted_count' => $status_counts['shortlisted'] ?? 0,
    'accepted_count' => $status_counts['accepted'] ?? 0,
    'rejected_count' => $status_counts['rejected'] ?? 0,
    'notification' => flash_message()
]);