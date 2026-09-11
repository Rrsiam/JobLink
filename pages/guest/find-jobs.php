<?php
require_once __DIR__ . '/../../config.php';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$db = getDB();

// Build query
$sql = "SELECT j.*, u.name as employer_name, c.name as category_name 
        FROM jobs j 
        LEFT JOIN users u ON j.employer_id = u.id 
        LEFT JOIN categories c ON j.category_id = c.id 
        WHERE j.status = 'active'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (j.title LIKE ? OR j.location LIKE ? OR j.skills LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($category)) {
    $sql .= " AND j.category_id = ?";
    $params[] = $category;
}

$sql .= " ORDER BY j.created_at DESC LIMIT 20";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$job_html = '';
if (count($jobs) === 0) {
    $job_html = "<div class='empty-state' style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>
        <div style='font-size:40px;margin-bottom:12px;'>🔍</div>
        <h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Jobs Found</h3>
        <p style='font-size:14px;'>No jobs are available right now. Check back later.</p>
    </div>";
}
foreach ($jobs as $job) {
    $skills = $job['skills'] ? explode(',', $job['skills']) : [];
    $skill_tags = '';
    foreach (array_slice($skills, 0, 4) as $skill) {
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
        <div class='actions'>
            <a href='?page=job-details&id={$job['id']}' class='btn btn-primary btn-sm'>View Details</a>
        </div>
    </div>";
}

// Get categories for filter
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$category_options = '<option value="">All Categories</option>';
foreach ($categories as $cat) {
    $selected = ($category == $cat['id']) ? 'selected' : '';
    $category_options .= "<option value='{$cat['id']}' $selected>{$cat['name']}</option>";
}

render('guest/find-jobs.html', [
    'job_list' => $job_html,
    'showing' => count($jobs),
    'total' => count($jobs),
    'category_options' => $category_options
]);