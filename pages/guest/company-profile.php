<?php
require_once __DIR__ . '/../../config.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("
    SELECT u.*, ep.* 
    FROM users u 
    JOIN employer_profiles ep ON u.id = ep.user_id 
    WHERE u.id = ? AND u.role = 'employer'
");
$stmt->execute([$id]);
$company = $stmt->fetch();

if (!$company) {
    header('Location: ?page=home');
    exit;
}

$jobs = $db->prepare("SELECT * FROM jobs WHERE employer_id = ? AND status = 'active'")->execute([$id])->fetchAll();

$open_jobs = '';
foreach ($jobs as $job) {
    $salary = $job['salary_min']
        ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max'])
        : 'Negotiable';
    $open_jobs .= "
    <div class='job-card'>
        <div class='title'>" . e($job['title']) . "</div>
        <div class='meta'>
            <span>⏳ " . e($job['type']) . "</span>
            <span>📍 " . e($job['location']) . "</span>
            <span>💰 " . e($salary) . "</span>
        </div>
        <a href='?page=job-details&id=" . (int)$job['id'] . "' class='btn btn-primary btn-sm'>View</a>
    </div>";
}

render('guest/company-profile.html', [
    'company_name' => $company['company_name'],
    'initials' => substr($company['company_name'], 0, 2),
    'industry' => $company['industry'],
    'location' => $company['address'],
    'size' => $company['company_size'] ?: 'Not specified',
    'founded' => '2018',
    'open_roles' => count($jobs),
    'countries' => '1',
    'about_text' => new RawHtml(nl2br(e($company['description'] ?? ''))),
    'benefits' => $company['benefits']
        ? new RawHtml('<li>' . str_replace("\n", '</li><li>', e($company['benefits'])) . '</li>')
        : '',
    'open_jobs' => new RawHtml($open_jobs),
    'website' => $company['website'] ?: '#'
]);