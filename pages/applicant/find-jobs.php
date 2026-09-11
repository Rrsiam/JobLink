<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$type = $_GET['type'] ?? '';
$location = $_GET['location'] ?? '';

$db = getDB();

// Build query
$sql = "SELECT j.*, u.name as employer_name, c.name as category_name 
        FROM jobs j 
        LEFT JOIN users u ON j.employer_id = u.id 
        LEFT JOIN categories c ON j.category_id = c.id 
        WHERE j.status = 'active'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (j.title LIKE ? OR j.skills LIKE ? OR j.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($location)) {
    $sql .= " AND j.location LIKE ?";
    $params[] = "%$location%";
}

if (!empty($category)) {
    $sql .= " AND j.category_id = ?";
    $params[] = $category;
}

if (!empty($type)) {
    $sql .= " AND j.type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY j.created_at DESC LIMIT 20";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Count total jobs (without limit)
$count_sql = "SELECT COUNT(*) as total FROM jobs j WHERE j.status = 'active'";
$count_params = [];
if (!empty($search)) {
    $count_sql .= " AND (j.title LIKE ? OR j.skills LIKE ? OR j.description LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}
if (!empty($location)) {
    $count_sql .= " AND j.location LIKE ?";
    $count_params[] = "%$location%";
}
if (!empty($category)) {
    $count_sql .= " AND j.category_id = ?";
    $count_params[] = $category;
}
if (!empty($type)) {
    $count_sql .= " AND j.type = ?";
    $count_params[] = $type;
}
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($count_params);
$total_count = $count_stmt->fetchColumn();

$job_html = '';
if (count($jobs) === 0) {
    $any_jobs = $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn();
    if ($any_jobs == 0) {
        $job_html = "<div class='empty-state' style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>
            <div style='font-size:40px;margin-bottom:12px;'>📭</div>
            <h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Jobs Posted Yet</h3>
            <p style='font-size:14px;'>Employers haven't posted any jobs yet. Check back later.</p>
        </div>";
    } else {
        $job_html = "<div class='empty-state' style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>
            <div style='font-size:40px;margin-bottom:12px;'>🔍</div>
            <h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Jobs Found</h3>
            <p style='font-size:14px;'>No jobs match your filters. Try a different keyword, category, or type.</p>
            <a href='?role=applicant&page=find-jobs' class='btn btn-outline btn-sm' style='margin-top:12px;'>Clear All Filters</a>
        </div>";
    }
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
            <a href='?role=applicant&page=job-details&id={$job['id']}' class='btn btn-primary btn-sm'>View Details</a>
        </div>
    </div>";
}

// Get categories for filter dropdown
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$category_options = '<option value="">All Categories</option>';
foreach ($categories as $cat) {
    $selected = ($category == $cat['id']) ? 'selected' : '';
    $category_options .= "<option value='{$cat['id']}' $selected>{$cat['name']}</option>";
}

// Get job types for filter
$job_types = ['Full-time', 'Part-time', 'Internship', 'Remote'];
$type_options = '<option value="">All Types</option>';
foreach ($job_types as $t) {
    $selected = ($type === $t) ? 'selected' : '';
    $type_options .= "<option value='{$t}' $selected>{$t}</option>";
}

$user = get_user_data();

$filters_active = [];
if (!empty($search)) $filters_active[] = 'Search: "' . $search . '"';
if (!empty($location)) $filters_active[] = 'Location: ' . $location;
if (!empty($category)) {
    $cat_stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $cat_stmt->execute([$category]);
    $filters_active[] = 'Category: ' . $cat_stmt->fetchColumn();
}
if (!empty($type)) $filters_active[] = 'Type: ' . $type;

$filter_note = '';
if (count($filters_active) > 0) {
    $filter_note = '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin:0 0 16px;color:#1e40af;font-size:14px;">Active filters: ' . implode(' &nbsp;·&nbsp; ', $filters_active) . ' &nbsp;<a href="?role=applicant&page=find-jobs" style="color:#2563eb;font-weight:600;">Clear</a></div>';
}

render('applicant/find-jobs.html', [
    'name' => $user['name'] ?? $_SESSION['name'] ?? 'Applicant',
    'job_list' => $job_html,
    'showing' => count($jobs),
    'total' => $total_count,
    'category_options' => $category_options,
    'type_options' => $type_options,
    'search_value' => $search,
    'location_value' => $location,
    'filter_note' => $filter_note
]);