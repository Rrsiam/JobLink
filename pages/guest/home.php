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

// Only applicants can apply. Everyone else is sent to sign in first,
// then returned to the job they wanted to apply for.
$is_applicant = is_logged_in() && get_user_role() === 'applicant';

// Build job cards HTML
$job_html = '';
foreach ($jobs as $job) {
    $skills = $job['skills'] ? explode(',', $job['skills']) : [];
    $skill_tags = '';
    foreach (array_slice($skills, 0, 3) as $skill) {
        if (trim($skill) !== '') {
            $skill_tags .= "<span>" . e(trim($skill)) . "</span>";
        }
    }

    $job_id = (int)$job['id'];
    $details_url = '?page=job-details&id=' . $job_id;
    $apply_target = '?role=applicant&page=job-details&id=' . $job_id . '&apply=1';

    if ($is_applicant) {
        $apply_url = $apply_target;
        $apply_label = 'Apply Now';
    } elseif (is_logged_in()) {
        $apply_url = '?role=' . get_user_role() . '&page=dashboard';
        $apply_label = 'Apply Now';
    } else {
        $apply_url = '?page=sign-in&next=' . rawurlencode($apply_target);
        $apply_label = 'Apply Now';
    }

    $salary = $job['salary_min']
        ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max'])
        : 'Negotiable';

    $job_html .= "
    <div class='job-card'>
        <div class='title'><a href='" . e($details_url) . "'>" . e($job['title']) . "</a></div>
        <div class='company'>" . e($job['employer_name']) . "</div>
        <div class='meta'>
            <span>📍 " . e($job['location']) . "</span>
            <span>💰 " . e($salary) . "</span>
            <span>⏳ " . e($job['type']) . "</span>
        </div>
        <div class='tags'>{$skill_tags}</div>
        <div class='actions'>
            <a href='" . e($details_url) . "' class='btn btn-outline btn-sm'>View Details</a>
            <a href='" . e($apply_url) . "' class='btn btn-primary btn-sm'>" . e($apply_label) . "</a>
        </div>
    </div>";
}

// Build categories HTML
$category_html = '';
foreach ($categories as $cat) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE category_id = ? AND status = 'active'");
    $countStmt->execute([$cat['id']]);
    $count = (int)$countStmt->fetchColumn();
    $category_html .= "
    <div class='category-card'>
        <div class='icon'>💼</div>
        <div class='name'>" . e($cat['name']) . "</div>
        <div class='count'>$count open positions</div>
    </div>";
}

  render('guest/home.html', [
      'job_list' => new RawHtml($job_html),
      'category_list' => new RawHtml($category_html),
      'search_value' => new RawHtml(e($_GET['search'] ?? ''))
  ]);