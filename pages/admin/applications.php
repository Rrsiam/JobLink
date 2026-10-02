<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Admin can also moderate a single application from the detail page.
$status_actions = [
    'under review' => 'Mark Under Review',
    'shortlisted' => 'Shortlist',
    'accepted' => 'Accept',
    'rejected' => 'Reject',
    'pending' => 'Reset to Pending',
];
if (isset($_GET['set_status'])) {
    $app_id = (int)$_GET['set_status'];
    $new_status = strtolower(trim($_GET['status'] ?? ''));
    if (isset($status_actions[$new_status])) {
        $db->prepare("UPDATE applications SET status = ? WHERE id = ?")->execute([$new_status, $app_id]);
        redirect_with_flash('?role=admin&page=applications', 'Application marked as ' . $status_actions[$new_status] . '.');
    }
}

// A single application can be inspected before acting on it.
$view_id = (int)($_GET['view'] ?? 0);
if ($view_id > 0) {
    $view = $db->prepare("
        SELECT a.*, u.name as applicant_name, u.email as applicant_email, u.phone as applicant_phone,
               ap.professional_title, ap.address as applicant_location, ap.skills,
                j.title as job_title, COALESCE(ep.company_name, emp.name) as company_name, ep.company_name as company_profile_name
        FROM applications a 
        JOIN users u ON a.applicant_id = u.id 
        LEFT JOIN applicant_profiles ap ON ap.user_id = u.id
        JOIN jobs j ON a.job_id = j.id 
        JOIN users emp ON j.employer_id = emp.id 
        LEFT JOIN employer_profiles ep ON ep.user_id = emp.id
        WHERE a.id = ?
    ")->execute([$view_id])->fetch();

    if ($view) {
        $meta_map = application_status_meta();
        $badge = $meta_map[strtolower($view['status'])] ?? $meta_map['pending'];
        $status_buttons = '';
        foreach ($status_actions as $value => $label) {
            if ($value === strtolower($view['status'])) {
                continue;
            }
            $status_buttons .= "<a href='?role=admin&page=applications&amp;set_status={$view_id}&amp;status=" . urlencode($value) . "' class='btn btn-outline btn-sm'>" . e($label) . "</a> ";
        }

        $resume_link = '<span class="text-muted">No resume on file</span>';
        $resume_ref = $view['resume_path'] ?: '';
        if ($resume_ref !== '') {
            $absolute = resume_resolve_path($resume_ref);
            if ($absolute && is_file($absolute)) {
                $resume_link = "<a href='?role=admin&amp;page=resume-file&amp;application_id={$view_id}&amp;download=1' class='btn btn-outline btn-sm'>Download</a>";
            }
        }

        render('admin/application-view.html', [
            'app_id' => $view_id,
            'applicant_name' => $view['applicant_name'],
            'applicant_email' => $view['applicant_email'],
            'applicant_phone' => $view['applicant_phone'] ?: '—',
            'applicant_title' => $view['professional_title'] ?: '—',
            'applicant_location' => $view['applicant_location'] ?: '—',
            'applicant_skills' => $view['skills'] ?: '—',
            'job_title' => $view['job_title'],
            'company_name' => $view['company_profile_name'] ?: $view['company_name'],
            'applied_date' => date('M d, Y', strtotime($view['created_at'])),
            'status' => $badge['label'],
            'status_class' => $badge['badge'],
            'status_buttons' => new RawHtml($status_buttons),
            'resume_link' => new RawHtml($resume_link),
           'notification' => new RawHtml(flash_message()),
        ]);
        exit;
    }

    redirect_with_flash('?role=admin&page=applications', 'Application not found.', 'error');
}

$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];

$sql = "
    SELECT a.*, u.name as applicant_name, j.title as job_title, 
           COALESCE(ep.company_name, emp.name) as company_name 
    FROM applications a 
    JOIN users u ON a.applicant_id = u.id 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users emp ON j.employer_id = emp.id 
    LEFT JOIN employer_profiles ep ON ep.user_id = emp.id ";

if ($status_filter && $status_filter !== 'all') {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
}
if ($search !== '') {
    $where[] = "(u.name LIKE ? OR j.title LIKE ? OR COALESCE(ep.company_name, emp.name) LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY a.created_at DESC";

$total_applications = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();

$stmt = $db->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll();

$statuses = [
    'all' => 'All',
    'pending' => 'Pending',
    'under review' => 'Under Review',
    'shortlisted' => 'Shortlisted',
    'accepted' => 'Accepted',
    'rejected' => 'Rejected',
];
$tabs = '';
$current_filter = $status_filter === '' ? 'all' : $status_filter;
foreach ($statuses as $value => $label) {
    $css = $current_filter === $value ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    $tabs .= "<a href='?role=admin&page=applications&status=" . urlencode($value) . ($search !== '' ? "&amp;search=" . urlencode($search) : '') . "' class='$css'>" . e($label) . "</a>";
}

$rows = '';
$meta_map = application_status_meta();
foreach ($apps as $a) {
    $meta = $meta_map[strtolower($a['status'])] ?? $meta_map['pending'];
    $rows .= "<tr>
        <td>" . htmlspecialchars($a['applicant_name']) . "</td>
        <td>" . htmlspecialchars($a['job_title']) . "</td>
        <td>" . htmlspecialchars($a['company_name']) . "</td>
        <td>" . date('M d, Y', strtotime($a['created_at'])) . "</td>
        <td><span class='badge " . e($meta['badge']) . "'>" . e($meta['label']) . "</span></td>
        <td><a href='?role=admin&page=applications&amp;view={$a['id']}' class='btn btn-outline btn-sm'>View</a></td>
    </tr>";
}
if (!$apps) {
    $rows = "<tr><td colspan='6' class='text-muted'>No applications match this filter.</td></tr>";
}

render('admin/applications.html', [
    'total_applications' => $total_applications,
    'application_rows' => new RawHtml($rows),
    'status_tabs' => new RawHtml($tabs),
    'status_filter' => e($status_filter),
    'search' => e($search),
    'showing' => count($apps),
    'notification' => new RawHtml(flash_message())
]);