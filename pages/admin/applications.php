<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

$apps = $db->query("
    SELECT a.*, u.name as applicant_name, j.title as job_title, 
           emp.name as company_name 
    FROM applications a 
    JOIN users u ON a.applicant_id = u.id 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users emp ON j.employer_id = emp.id 
    ORDER BY a.created_at DESC
")->fetchAll();

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
foreach ($apps as $a) {
    $badge = $badges[$a['status']] ?? 'badge-pending';
    $rows .= "<tr>
        <td>{$a['applicant_name']}</td>
        <td>{$a['job_title']}</td>
        <td>{$a['company_name']}</td>
        <td>" . date('M d, Y', strtotime($a['created_at'])) . "</td>
        <td><span class='badge $badge'>" . ucfirst($a['status']) . "</span></td>
        <td><a href='?role=admin&page=applications&view={$a['id']}' class='btn btn-outline btn-sm'>View</a></td>
    </tr>";
}

render('admin/applications.html', [
    'total_applications' => count($apps),
    'application_rows' => $rows,
    'showing' => count($apps)
]);