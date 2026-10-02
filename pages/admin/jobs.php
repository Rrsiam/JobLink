<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Filter
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$params = [];
$where = [];

$sql = "SELECT j.*, u.name as employer_name, c.name as category_name 
        FROM jobs j 
        JOIN users u ON j.employer_id = u.id 
        LEFT JOIN categories c ON j.category_id = c.id";

if ($status_filter && $status_filter !== 'all') {
    $where[] = "j.status = ?";
    $params[] = $status_filter;
}
if ($search !== '') {
    $where[] = "(j.title LIKE ? OR u.name LIKE ? OR c.name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY j.created_at DESC";

// Count every job so the "Showing X of Y" line reports the real total rather
// than the size of the current filter.
$total_jobs = (int)$db->query("SELECT COUNT(*) FROM jobs")->fetchColumn();

$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$statuses = ['all' => 'All', 'active' => 'Active', 'draft' => 'Pending', 'closed' => 'Closed', 'expired' => 'Expired'];
$tabs = '';
foreach ($statuses as $value => $label) {
    $current = ($status_filter === '' ? 'all' : $status_filter) === $value;
    $css = $current ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    $tabs .= "<a href='?role=admin&page=jobs&status=$value" . ($search !== '' ? "&amp;search=" . urlencode($search) : '') . "' class='$css'>$label</a>";
}

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
        <td>" . htmlspecialchars($j['title']) . "</td>
        <td>" . htmlspecialchars($j['employer_name']) . "</td>
        <td>" . htmlspecialchars((string)$j['category_name']) . "</td>
        <td>" . date('M d, Y', strtotime($j['created_at'])) . "</td>
        <td>" . date('M d, Y', strtotime($j['deadline'])) . "</td>
        <td><span class='badge $badge'>" . ucfirst($j['status']) . "</span></td>
    </tr>";
}
if (!$jobs) {
    $rows = "<tr><td colspan='6' class='text-muted'>No jobs match this filter.</td></tr>";
}

render('admin/jobs.html', [
    'total_jobs' => $total_jobs,
    'job_rows' => new RawHtml($rows),
    'status_tabs' => new RawHtml($tabs),
    'status_filter' => e($status_filter),
    'search' => e($search),
    'showing' => count($jobs),
    'notification' => flash_message()
]);