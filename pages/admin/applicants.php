<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Handle actions
if (isset($_GET['suspend'])) {
    $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'applicant'")->execute([$_GET['suspend']]);
    header('Location: ?role=admin&page=applicants&success=Suspended');
    exit;
}
if (isset($_GET['activate'])) {
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'applicant'")->execute([$_GET['activate']]);
    header('Location: ?role=admin&page=applicants&success=Activated');
    exit;
}
if (isset($_GET['delete'])) {
    delete_user_account((int)$_GET['delete']);
    header('Location: ?role=admin&page=applicants&success=Deleted');
    exit;
}

$applicants = $db->query("
    SELECT u.*, ap.professional_title, ap.skills 
    FROM users u 
    LEFT JOIN applicant_profiles ap ON u.id = ap.user_id 
    WHERE u.role = 'applicant' 
    ORDER BY u.created_at DESC
")->fetchAll();

$rows = '';
foreach ($applicants as $a) {
    $status_badge = $a['status'] === 'active' ? 'badge-active' : 'badge-suspended';
    $rows .= "<tr>
        <td>{$a['name']}</td>
        <td>{$a['email']}</td>
        <td>" . substr($a['skills'] ?: 'No skills', 0, 50) . "</td>
        <td>" . date('M d, Y', strtotime($a['created_at'])) . "</td>
        <td><span class='badge $status_badge'>" . ucfirst($a['status']) . "</span></td>
        <td>
            <a href='?role=admin&page=applicants&view={$a['id']}' class='btn btn-outline btn-sm'>View</a>
            " . ($a['status'] === 'active' ? "<a href='?role=admin&page=applicants&suspend={$a['id']}' class='btn btn-warning btn-sm' onclick='return confirm(\"Suspend this user?\")'>Suspend</a>" : "<a href='?role=admin&page=applicants&activate={$a['id']}' class='btn btn-success btn-sm'>Activate</a>") . "
            <a href='?role=admin&page=applicants&delete={$a['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this user?\")'>Delete</a>
        </td>
    </tr>";
}

render('admin/applicants.html', [
    'total_applicants' => count($applicants),
    'applicant_rows' => $rows,
    'showing' => count($applicants)
]);