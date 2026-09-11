<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Handle actions
if (isset($_GET['approve'])) {
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'employer'")->execute([$_GET['approve']]);
    $db->prepare("UPDATE employer_profiles SET verified = 1 WHERE user_id = ?")->execute([$_GET['approve']]);
    header('Location: ?role=admin&page=employers&success=Approved');
    exit;
}
if (isset($_GET['suspend'])) {
    $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'employer'")->execute([$_GET['suspend']]);
    header('Location: ?role=admin&page=employers&success=Suspended');
    exit;
}
if (isset($_GET['delete'])) {
    delete_user_account((int)$_GET['delete']);
    header('Location: ?role=admin&page=employers&success=Deleted');
    exit;
}

$employers = $db->query("
    SELECT u.*, ep.company_name, ep.industry, ep.verified 
    FROM users u 
    JOIN employer_profiles ep ON u.id = ep.user_id 
    WHERE u.role = 'employer' 
    ORDER BY u.created_at DESC
")->fetchAll();

$rows = '';
foreach ($employers as $e) {
    $status_badge = $e['status'] === 'active' ? 'badge-verified' : ($e['status'] === 'pending' ? 'badge-pending' : 'badge-suspended');
    $rows .= "<tr>
        <td>{$e['company_name']}</td>
        <td>{$e['name']}</td>
        <td>{$e['email']}</td>
        <td>{$e['industry']}</td>
        <td>" . date('M d, Y', strtotime($e['created_at'])) . "</td>
        <td><span class='badge $status_badge'>" . ucfirst($e['status']) . "</span></td>
        <td>
            " . ($e['status'] === 'pending' ? "<a href='?role=admin&page=employers&approve={$e['id']}' class='btn btn-success btn-sm'>Approve</a>" : "") . "
            " . ($e['status'] === 'active' ? "<a href='?role=admin&page=employers&suspend={$e['id']}' class='btn btn-warning btn-sm' onclick='return confirm(\"Suspend this employer?\")'>Suspend</a>" : "") . "
            <a href='?role=admin&page=employers&delete={$e['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this employer?\")'>Delete</a>
        </td>
    </tr>";
}

render('admin/employers.html', [
    'total_employers' => count($employers),
    'employer_rows' => $rows,
    'showing' => count($employers)
]);