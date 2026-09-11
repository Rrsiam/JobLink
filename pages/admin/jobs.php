<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Filter
$status_filter = $_GET['status'] ?? '';
$params = [];
$sql = "SELECT j.*, u.name as employer_name, c.name as category_name 
        FROM jobs j 
        JOIN users u ON j.employer_id = u.id 
        LEFT JOIN categories c ON j.category_id = c.id";

if ($status_filter && $status_filter !== 'all') {
    $sql .= " WHERE j.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY j.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$rows = '';
$status_badges = [
    'draft' => 'badge-pending',
    'active' => 'badge-active',
    'closed' => 'badge-closed',
    'expired' => 'badge-rejected'
];
foreach ($jobs as $j) {
    $badge = $status_badges[$j['status']] ?? 'badge-pending';
    $rows .= "<tr>
        <td>{$j['title']}</td>
        <td>{$j['employer_name']}</td>
        <td>{$j['category_name']}</td>
        <td>" . date('M d, Y', strtotime($j['created_at'])) . "</td>
        <td>" . date('M d, Y', strtotime($j['deadline'])) . "</td>
        <td><span class='badge $badge'>" . ucfirst($j['status']) . "</span></td>
    </tr>";
}

render('admin/jobs.html', [
    'total_jobs' => count($jobs),
    'job_rows' => $rows,
    'showing' => count($jobs)
]);