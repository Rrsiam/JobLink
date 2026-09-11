<?php
require_once __DIR__ . '/../../config.php';

$db = getDB();

$companies = $db->query("
    SELECT u.id, u.name, ep.company_name, ep.industry, ep.address, ep.description, 
           COUNT(j.id) as job_count 
    FROM users u 
    JOIN employer_profiles ep ON u.id = ep.user_id 
    LEFT JOIN jobs j ON u.id = j.employer_id AND j.status = 'active' 
    WHERE u.role = 'employer' AND u.status = 'active' 
    GROUP BY u.id 
    ORDER BY job_count DESC
")->fetchAll();

$company_html = '';
foreach ($companies as $c) {
    $company_html .= "
    <div class='company-card'>
        <div class='name'>{$c['company_name']}</div>
        <div class='industry'>{$c['industry']} · {$c['address']}</div>
        <div class='desc'>" . substr($c['description'] ?? '', 0, 120) . "...</div>
        <div class='jobs-count'>{$c['job_count']} open jobs</div>
        <a href='?page=company-profile&id={$c['id']}' class='btn btn-outline btn-sm' style='margin-top:8px;'>View →</a>
    </div>";
}

render('guest/companies.html', [
    'company_list' => $company_html,
    'showing' => count($companies),
    'total' => count($companies)
]);