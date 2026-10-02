<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

$job_id = (int)($_GET['job_id'] ?? 0);
$status_filter = (string)($_GET['status'] ?? '');
$status_meta = application_status_meta();
$filter_tabs = [
    '' => 'All',
    'pending' => 'New',
    'under review' => 'Under Review',
    'shortlisted' => 'Shortlisted',
    'rejected' => 'Rejected',
    'hired' => 'Hired',
];

// Row actions (shortlist / hire / reject) come straight from the table, so they are
// handled before the list is built and the employer lands back on the same filter.
$carry = '';
if ($job_id) {
    $carry .= '&amp;job_id=' . $job_id;
}
if ($status_filter !== '') {
    $carry .= '&amp;status=' . rawurlencode($status_filter);
}

if (isset($_GET['action'])) {
    $app_id = (int)($_GET['id'] ?? 0);
    $new_status = employer_set_application_status($app_id, $user_id, (string)$_GET['action']);
    if ($new_status === null) {
        $_SESSION['error'] = 'That action is not available for this application.';
    } else {
        $name = $db->prepare("
            SELECT u.name as applicant_name
            FROM applications a
            JOIN users u ON a.applicant_id = u.id
            WHERE a.id = ?
        ")->execute([$app_id])->fetchColumn();
        $_SESSION['success'] = ($name ?: 'Applicant') . ' ' . $status_meta[$new_status]['message'] . '.';
    }

    $back = '?role=employer&page=applicants';
    if ($job_id) {
        $back .= '&job_id=' . $job_id;
    }
    if ($status_filter !== '' && isset($status_meta[$status_filter])) {
        $back .= '&status=' . rawurlencode($status_filter);
    }
    header('Location: ' . $back);
    exit;
}

if ($status_filter !== '' && !isset($status_meta[$status_filter])) {
    $status_filter = '';
}

// Get applicants
$sql = "
    SELECT a.id, a.status, a.created_at, a.resume_path,
           u.name as applicant_name, u.email, u.phone, j.title as job_title,
           j.location as job_location, ap.skills, ap.experience
    FROM applications a
    JOIN users u ON a.applicant_id = u.id
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN applicant_profiles ap ON u.id = ap.user_id
    WHERE j.employer_id = ?
";
$params = [$user_id];

if ($job_id) {
    $sql .= " AND a.job_id = ?";
    $params[] = $job_id;
}
if ($status_filter !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// Status counts for the tabs, for the whole company or for the job on screen
$count_sql = "
    SELECT a.status, COUNT(*) as count
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.employer_id = ?
";
$count_params = [$user_id];
if ($job_id) {
    $count_sql .= " AND a.job_id = ?";
    $count_params[] = $job_id;
}
$count_sql .= " GROUP BY a.status";

$status_counts = [];
foreach ($db->prepare($count_sql)->execute($count_params)->fetchAll() as $c) {
    $status_counts[$c['status']] = (int)$c['count'];
}
$total_count = array_sum($status_counts);

$rows = '';
$empty_row = '';
foreach ($applicants as $app) {
    $id = (int)$app['id'];
    $current = $status_meta[$app['status']] ?? ['badge' => 'badge-pending', 'label' => ucfirst($app['status'])];

    $actions = "<a href='?role=employer&amp;page=applicant-details&amp;id={$id}' class='btn btn-outline btn-sm'>View</a> ";
    $actions .= "<a href='?role=employer&amp;page=resume&amp;id={$id}' class='btn btn-outline btn-sm'>Resume</a> ";

    if (!in_array($app['status'], ['shortlisted', 'hired', 'rejected'], true)) {
        $actions .= "<a href='?role=employer&amp;page=applicants&amp;action=shortlisted&amp;id={$id}{$carry}' class='btn btn-primary btn-sm'>Shortlist</a> ";
    }
    if (!in_array($app['status'], ['hired', 'rejected'], true)) {
        $actions .= "<a href='?role=employer&amp;page=applicants&amp;action=hired&amp;id={$id}{$carry}' class='btn btn-success btn-sm'>Hire</a> ";
    }
    if ($app['status'] !== 'rejected') {
        $actions .= "<a href='?role=employer&amp;page=applicants&amp;action=rejected&amp;id={$id}{$carry}' class='btn btn-danger btn-sm' data-confirm='Reject " . e($app['applicant_name']) . "?'>Reject</a>";
    }

    $rows .= "<tr>
        <td><a href='?role=employer&amp;page=applicant-details&amp;id={$id}'>" . e($app['applicant_name']) . "</a></td>
        <td>" . e($app['job_location']) . "</td>
        <td>" . e(substr($app['skills'] ?: 'No skills', 0, 40)) . "</td>
        <td>" . e(substr($app['experience'] ?: 'No experience', 0, 40)) . "</td>
        <td>" . date('M d, Y', strtotime($app['created_at'])) . "</td>
        <td><span class='badge {$current['badge']}'>" . e($current['label']) . "</span></td>
        <td style='white-space:nowrap;'>{$actions}</td>
    </tr>";
}

if ($rows === '') {
    $empty_row = "<tr><td colspan='7' class='text-muted' style='text-align:center;padding:24px;'>No applications match this filter.</td></tr>";
}

// Get jobs for dropdown
$jobs = $db->prepare("SELECT id, title FROM jobs WHERE employer_id = ?")->execute([$user_id])->fetchAll();
$job_options = '<option value="">All Jobs</option>';
foreach ($jobs as $job) {
    $selected = ($job_id === (int)$job['id']) ? 'selected' : '';
    $job_options .= "<option value='" . (int)$job['id'] . "' $selected>" . e($job['title']) . "</option>";
}

$job_title = '';
if ($job_id) {
    $job_title = $db->prepare("SELECT title FROM jobs WHERE id = ? AND employer_id = ?")
        ->execute([$job_id, $user_id])->fetchColumn() ?: '';
}

$profile = $db->prepare("SELECT company_name FROM employer_profiles WHERE user_id = ?")
    ->execute([$user_id])->fetch();

// Filter tabs, each one a real link carrying the current job filter along
$tabs_html = '';
foreach ($filter_tabs as $value => $label) {
    $url = '?role=employer&page=applicants';
    if ($job_id) {
        $url .= '&job_id=' . $job_id;
    }
    if ($value !== '') {
        $url .= '&status=' . rawurlencode($value);
    }
    $count = $value === '' ? $total_count : ($status_counts[$value] ?? 0);
    $class = $value === $status_filter ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    $tabs_html .= "<a href='" . e($url) . "' class='$class'>" . e($label) . " $count</a>";
}

render('employer/applicants.html', [
    'company_name' => $profile['company_name'] ?? 'My Company',
    'job_title' => $job_title,
    'job_options' => new RawHtml($job_options),
    'notification' => new RawHtml(flash_message()),
    'filter_tabs' => new RawHtml($tabs_html),
    'total_applicants' => $total_count,
    'applicant_rows' => new RawHtml($rows . $empty_row),
    'showing' => count($applicants),
    'new_count' => $status_counts['pending'] ?? 0,
    'pending_count' => $status_counts['pending'] ?? 0,
    'review_count' => $status_counts['under review'] ?? 0,
    'shortlisted_count' => $status_counts['shortlisted'] ?? 0,
    'rejected_count' => $status_counts['rejected'] ?? 0,
    'hired_count' => $status_counts['hired'] ?? 0
]);
