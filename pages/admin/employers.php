<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Handle actions
if (isset($_GET['approve'])) {
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'employer'")->execute([$_GET['approve']]);
    $db->prepare("UPDATE employer_profiles SET verified = 1 WHERE user_id = ?")->execute([$_GET['approve']]);
    redirect_with_flash('?role=admin&page=employers', 'Employer approved and verified.');
}
if (isset($_GET['suspend'])) {
    $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'employer'")->execute([$_GET['suspend']]);
    redirect_with_flash('?role=admin&page=employers', 'Employer suspended.');
}
if (isset($_GET['activate'])) {
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'employer'")->execute([$_GET['activate']]);
    redirect_with_flash('?role=admin&page=employers', 'Employer reactivated.');
}
if (isset($_GET['delete'])) {
    delete_user_account((int)$_GET['delete']);
    redirect_with_flash('?role=admin&page=employers', 'Employer account deleted.');
}

$search = trim($_GET['search'] ?? '');
$params = [];
$sql = "
    SELECT u.*, ep.company_name, ep.industry, ep.verified 
    FROM users u 
    JOIN employer_profiles ep ON u.id = ep.user_id 
    WHERE u.role = 'employer' ";
if ($search !== '') {
    $sql .= " AND (ep.company_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= " ORDER BY u.created_at DESC";

$total_employers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'employer'")->fetchColumn();

$stmt = $db->prepare($sql);
$stmt->execute($params);
$employers = $stmt->fetchAll();

$rows = '';
foreach ($employers as $e) {
    $status_badge = $e['status'] === 'active' ? ($e['verified'] ? 'badge-verified' : 'badge-active') : ($e['status'] === 'pending' ? 'badge-pending' : 'badge-suspended');
    $id = (int)$e['id'];
    if ($e['status'] === 'pending') {
        $toggle = "<a href='?role=admin&page=employers&amp;approve=$id' class='btn btn-success btn-sm'>Approve</a>";
    } elseif ($e['status'] === 'active') {
        $toggle = "<a href='?role=admin&page=employers&amp;suspend=$id' class='btn btn-warning btn-sm' onclick='return confirm(\"Suspend this employer?\")'>Suspend</a>";
    } else {
        // A suspended employer had no way back before this.
        $toggle = "<a href='?role=admin&page=employers&amp;activate=$id' class='btn btn-success btn-sm'>Activate</a>";
    }
    $rows .= "<tr>
        <td>" . htmlspecialchars($e['company_name']) . "</td>
        <td>" . htmlspecialchars($e['name']) . "</td>
        <td>" . htmlspecialchars($e['email']) . "</td>
        <td>" . htmlspecialchars((string)$e['industry']) . "</td>
        <td>" . date('M d, Y', strtotime($e['created_at'])) . "</td>
        <td><span class='badge $status_badge'>" . ucfirst($e['status']) . "</span></td>
        <td>
            $toggle
            <a href='?role=admin&page=employers&amp;delete=$id' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this employer? This cannot be undone.\")'>Delete</a>
        </td>
    </tr>";
}
if (!$employers) {
    $rows = "<tr><td colspan='7' class='text-muted'>No employers match this search.</td></tr>";
}

render('admin/employers.html', [
    'total_employers' => $total_employers,
    'employer_rows' => new RawHtml($rows),
    'search' => e($search),
    'showing' => count($employers),
    'notification' => flash_message()
]);