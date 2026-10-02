<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Handle actions
if (isset($_GET['approve'])) {
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'applicant'")->execute([$_GET['approve']]);
    redirect_with_flash('?role=admin&page=applicants', 'Applicant approved.');
}
if (isset($_GET['suspend'])) {
    $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'applicant'")->execute([$_GET['suspend']]);
    redirect_with_flash('?role=admin&page=applicants', 'Applicant suspended.');
}
if (isset($_GET['activate'])) {
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'applicant'")->execute([$_GET['activate']]);
    redirect_with_flash('?role=admin&page=applicants', 'Applicant reactivated.');
}
if (isset($_GET['delete'])) {
    delete_user_account((int)$_GET['delete']);
    redirect_with_flash('?role=admin&page=applicants', 'Applicant account deleted.');
}

// "View" needs a destination; without this the button reloaded the same list.
$view_id = (int)($_GET['view'] ?? 0);
if ($view_id > 0) {
    $view = $db->prepare("
        SELECT u.*, ap.*
        FROM users u
        LEFT JOIN applicant_profiles ap ON ap.user_id = u.id
        WHERE u.id = ? AND u.role = 'applicant'
    ")->execute([$view_id])->fetch();

    if ($view) {
        $status_badge = $view['status'] === 'active' ? 'badge-active' : ($view['status'] === 'pending' ? 'badge-pending' : 'badge-suspended');
        $status_buttons = $view['status'] === 'pending'
            ? "<a href='?role=admin&page=applicants&amp;approve={$view_id}' class='btn btn-success btn-sm'>Approve</a>"
            : ($view['status'] === 'active'
                ? "<a href='?role=admin&page=applicants&amp;suspend={$view_id}' class='btn btn-warning btn-sm' onclick=\"return confirm('Suspend this user?')\">Suspend</a>"
                : "<a href='?role=admin&page=applicants&amp;activate={$view_id}' class='btn btn-success btn-sm'>Activate</a>");
        $status_buttons .= " <a href='?role=admin&page=applicants&amp;delete={$view_id}' class='btn btn-danger btn-sm' onclick=\"return confirm('Delete this user? This cannot be undone.')\">Delete</a>";

        $doc_cell = '<span class="text-muted">No document uploaded</span>';
        if (!empty($view['verification_doc'])) {
            $doc_cell = "<a href='?role=admin&amp;page=verification-doc&amp;user_id=" . (int)$view_id . "' class='btn btn-outline btn-sm'>Open Document</a>";
        }

        render('admin/applicant-view.html', [
            'user_id' => $view_id,
            'full_name' => $view['name'],
            'email' => $view['email'],
            'phone' => $view['phone'] ?: '—',
            'registered' => date('M d, Y', strtotime($view['created_at'])),
            'status' => ucfirst($view['status']),
            'status_class' => $status_badge,
            'professional_title' => $view['professional_title'] ?: '—',
            'address' => $view['address'] ?: '—',
            'gender' => $view['gender'] ?: '—',
            'career_objective' => $view['career_objective'] ?: '—',
            'education' => $view['education'] ?: '—',
            'experience' => $view['experience'] ?: '—',
            'skills' => $view['skills'] ?: '—',
            'certifications' => $view['certifications'] ?: '—',
            'languages' => $view['languages'] ?: '—',
            'document_link' => new RawHtml($doc_cell),
            'status_buttons' => new RawHtml($status_buttons),
            'notification' => flash_message(),
        ]);
        exit;
    }

    redirect_with_flash('?role=admin&page=applicants', 'Applicant not found.', 'error');
}

$search = trim($_GET['search'] ?? '');
$params = [];
$sql = "
    SELECT u.*, ap.professional_title, ap.skills, ap.verification_doc 
    FROM users u 
    LEFT JOIN applicant_profiles ap ON u.id = ap.user_id 
    WHERE u.role = 'applicant' ";
if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR ap.skills LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= " ORDER BY u.created_at DESC";

$total_applicants = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'applicant'")->fetchColumn();

$stmt = $db->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

$rows = '';
foreach ($applicants as $a) {
    $status_badge = $a['status'] === 'active' ? 'badge-active' : ($a['status'] === 'pending' ? 'badge-pending' : 'badge-suspended');
    $id = (int)$a['id'];
    $doc_cell = '<span class="text-muted">—</span>';
    if (!empty($a['verification_doc'])) {
        $doc_cell = "<a href='?role=admin&amp;page=verification-doc&amp;user_id=$id' class='btn btn-outline btn-sm'>View Doc</a>";
    }
    $rows .= "<tr>
        <td>" . htmlspecialchars($a['name']) . "</td>
        <td>" . htmlspecialchars($a['email']) . "</td>
        <td>" . htmlspecialchars(substr($a['skills'] ?: 'No skills', 0, 50)) . "</td>
        <td>" . date('M d, Y', strtotime($a['created_at'])) . "</td>
        <td><span class='badge $status_badge'>" . ucfirst($a['status']) . "</span></td>
        <td>
            {$doc_cell}
            <a href='?role=admin&page=applicants&amp;view=$id' class='btn btn-outline btn-sm'>View</a>
            " . ($a['status'] === 'pending' ? "<a href='?role=admin&page=applicants&amp;approve=$id' class='btn btn-success btn-sm'>Approve</a>" : "") . "
            " . ($a['status'] === 'active' ? "<a href='?role=admin&page=applicants&amp;suspend=$id' class='btn btn-warning btn-sm' onclick='return confirm(\"Suspend this user?\")'>Suspend</a>" : "<a href='?role=admin&page=applicants&amp;activate=$id' class='btn btn-success btn-sm'>Activate</a>") . "
            <a href='?role=admin&page=applicants&amp;delete=$id' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this user? This cannot be undone.\")'>Delete</a>
        </td>
    </tr>";
}
if (!$applicants) {
    $rows = "<tr><td colspan='6' class='text-muted'>No applicants match this search.</td></tr>";
}

render('admin/applicants.html', [
    'total_applicants' => $total_applicants,
    'applicant_rows' => new RawHtml($rows),
    'search' => e($search),
    'showing' => count($applicants),
    'notification' => flash_message()
]);