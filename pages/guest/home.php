<?php
require_once __DIR__ . '/../../config.php';

$db = getDB();

// Get latest jobs
$stmt = $db->query("
    SELECT j.*, u.name as employer_name, c.name as category_name 
    FROM jobs j 
    LEFT JOIN users u ON j.employer_id = u.id 
    LEFT JOIN categories c ON j.category_id = c.id 
    WHERE j.status = 'active' 
    ORDER BY j.created_at DESC 
    LIMIT 6
");
$jobs = $stmt->fetchAll();

// Get categories
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Build job cards HTML
$job_html = '';
foreach ($jobs as $job) {
    $skills = $job['skills'] ? explode(',', $job['skills']) : [];
    $skill_tags = '';
    foreach (array_slice($skills, 0, 3) as $skill) {
        $skill_tags .= "<span>" . trim($skill) . "</span>";
    }
    
    $job_html .= "
    <div class='job-card'>
        <div class='title'>{$job['title']}</div>
        <div class='company'>{$job['employer_name']}</div>
        <div class='meta'>
            <span>📍 {$job['location']}</span>
            <span>💰 " . ($job['salary_min'] ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']) : 'Negotiable') . "</span>
            <span>⏳ {$job['type']}</span>
        </div>
        <div class='tags'>{$skill_tags}</div>
        <a href='?page=job-details&id={$job['id']}' class='btn btn-primary btn-sm'>Apply Now</a>
    </div>";
}

// Build categories HTML
$category_html = '';
foreach ($categories as $cat) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE category_id = ? AND status = 'active'");
    $countStmt->execute([$cat['id']]);
    $count = $countStmt->fetchColumn();
    $category_html .= "
    <div class='category-card'>
        <div class='icon'>💼</div>
        <div class='name'>{$cat['name']}</div>
        <div class='count'>{$count} open positions</div>
    </div>";
}

render('guest/home.html', [
    'job_list' => $job_html,
    'category_list' => $category_html
]);