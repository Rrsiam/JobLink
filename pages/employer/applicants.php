<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

$job_id = $_GET['job_id'] ?? 0;

// Get applicants
$sql = "
    SELECT a.*, u.name as applicant_name, u.email, u.phone, j.title as job_title,
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
$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// Status counts
$counts = $db->prepare("
    SELECT a.status, COUNT(*) as count 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.employer_id = ? 
    GROUP BY a.status
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
    'interview' => 'badge-under-review',
    'accepted' => 'badge-hired',
    'rejected' => 'badge-rejected',
    'hired' => 'badge-hired'
];
foreach ($applicants as $app) {
    $badge = $badges[$app['status']] ?? 'badge-pending';
    $rows .= "<tr>
        <td><a href='?role=employer&page=applicant-details&id={$app['id']}'>{$app['applicant_name']}</a></td>
        <td>{$app['job_location']}</td>
        <td>" . substr($app['skills'] ?: 'No skills', 0, 40) . "</td>
        <td>" . substr($app['experience'] ?: 'No experience', 0, 40) . "</td>
        <td>" . date('M d, Y', strtotime($app['created_at'])) . "</td>
        <td><span class='badge $badge'>" . ucfirst($app['status']) . "</span></td>
        <td>
            <a href='?role=employer&page=applicant-details&id={$app['id']}' class='btn btn-outline btn-sm'>View</a>
        </td>
    </tr>";
}

// Get jobs for dropdown
$jobs = $db->prepare("SELECT id, title FROM jobs WHERE employer_id = ?")->execute([$user_id])->fetchAll();
$job_options = '<option value="">All Jobs</option>';
foreach ($jobs as $job) {
    $selected = ($job_id == $job['id']) ? 'selected' : '';
    $job_options .= "<option value='{$job['id']}' $selected>{$job['title']}</option>";
}

$job_title = '';
if ($job_id) {
    $job_title = $db->prepare("SELECT title FROM jobs WHERE id = ?")->execute([$job_id])->fetchColumn() ?: '';
}

render('employer/applicants.html', [
    'company_name' => 'My Company',
    'job_title' => $job_title,
    'job_options' => $job_options,
    'total_applicants' => count($applicants),
    'applicant_rows' => $rows,
    'showing' => count($applicants),
    'new_count' => $status_counts['pending'] ?? 0,
    'pending_count' => $status_counts['pending'] ?? 0,
    'review_count' => $status_counts['under review'] ?? 0,
    'shortlisted_count' => $status_counts['shortlisted'] ?? 0,
    'rejected_count' => $status_counts['rejected'] ?? 0,
    'hired_count' => $status_counts['hired'] ?? 0
]);