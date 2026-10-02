<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

$jobs = $db->prepare("SELECT * FROM jobs WHERE employer_id = ? ORDER BY created_at DESC")->execute([$user_id])->fetchAll();

$rows = '';
$status_badges = [
    'draft' => 'badge-pending',
    'active' => 'badge-active',
    'closed' => 'badge-closed',
    'expired' => 'badge-rejected'
];
foreach ($jobs as $job) {
    $badge = $status_badges[$job['status']] ?? 'badge-pending';
    $app_count = $db->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?")->execute([$job['id']])->fetchColumn();
    $rows .= "<tr>
        <td><a href='?role=employer&page=job-details&id=" . (int)$job['id'] . "'>" . e($job['title']) . "</a></td>
        <td>" . e($job['type']) . "</td>
        <td>" . date('M d, Y', strtotime($job['created_at'])) . "</td>
        <td>" . date('M d, Y', strtotime($job['deadline'])) . "</td>
        <td>" . (int)$app_count . "</td>
        <td><span class='badge $badge'>" . e(ucfirst($job['status'])) . "</span></td>
        <td>
            <a href='?role=employer&page=edit-job&id=" . (int)$job['id'] . "' class='btn btn-outline btn-sm'>Edit</a>
            <a href='?role=employer&page=job-details&id=" . (int)$job['id'] . "' class='btn btn-primary btn-sm'>View</a>
        </td>
    </tr>";
}

$company_name = 'My Company';
$ep = $db->prepare("SELECT company_name FROM employer_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
if ($ep && !empty($ep['company_name'])) {
    $company_name = $ep['company_name'];
} else {
    $u = $db->prepare("SELECT name FROM users WHERE id = ?")->execute([$user_id])->fetch();
    if ($u && !empty($u['name'])) {
        $company_name = $u['name'];
    }
}

render('employer/manage-jobs.html', [
    'company_name' => $company_name,
    'total_jobs' => count($jobs),
    'job_rows' => new RawHtml($rows),
    'showing' => count($jobs),
    'notification' => new RawHtml(flash_message())
]);