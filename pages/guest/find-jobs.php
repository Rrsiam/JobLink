<?php
require_once __DIR__ . '/../../config.php';

$filters = normalise_job_filters($_GET);

$db = getDB();

// Shared with the applicant search: one WHERE builder for both the result list
// and the total, so the two can never disagree.
$from = " FROM jobs j
          LEFT JOIN users u ON j.employer_id = u.id
          LEFT JOIN categories c ON j.category_id = c.id
          WHERE j.status = 'active'";
list($where_sql, $where_params) = job_search_where($filters);

$stmt = $db->prepare("SELECT j.*, u.name as employer_name, c.name as category_name"
    . $from . $where_sql . " ORDER BY j.created_at DESC LIMIT 20");
$stmt->execute($where_params);
$jobs = $stmt->fetchAll();

$count_stmt = $db->prepare("SELECT COUNT(*)" . $from . $where_sql);
$count_stmt->execute($where_params);
$total_count = (int)$count_stmt->fetchColumn();

$job_html = '';
if (count($jobs) === 0) {
    $job_html = "<div class='empty-state' style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>
        <div style='font-size:40px;margin-bottom:12px;'>&#128269;</div>
        <h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Jobs Found</h3>
        <p style='font-size:14px;'>No jobs match your search. Try a different keyword or category.</p>
        <a href='?page=find-jobs' class='btn btn-outline btn-sm' style='margin-top:12px;'>Clear Search</a>
    </div>";
}
foreach ($jobs as $job) {
    // Job data is employer-supplied, so it is escaped on the way out.
    $skills = !empty($job['skills']) ? explode(',', $job['skills']) : [];
    $skill_tags = '';
    foreach (array_slice($skills, 0, 4) as $skill) {
        $skill = trim($skill);
        if ($skill === '') continue;
        $skill_tags .= '<span>' . e($skill) . '</span>';
    }

    $job_id = (int)$job['id'];
    $salary = (!empty($job['salary_min']))
        ? number_format((float)$job['salary_min']) . ' - ' . number_format((float)$job['salary_max'])
        : 'Negotiable';

    $job_html .= "
    <div class='job-card'>
        <div class='title'>" . e($job['title']) . "</div>
        <div class='company'>" . e($job['employer_name'] ?? 'Unknown Employer') . "</div>
        <div class='meta'>
            <span>&#128205; " . e($job['location']) . "</span>
            <span>&#128176; " . e($salary) . "</span>
            <span>&#9203; " . e($job['type']) . "</span>
        </div>
        <div class='tags'>{$skill_tags}</div>
        <div class='actions'>
            <a href='?page=job-details&id={$job_id}' class='btn btn-primary btn-sm'>View Details</a>
        </div>
    </div>";
}

$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$category_options = '<option value="">All Categories</option>';
foreach ($categories as $cat) {
    $selected = ((string)$filters['category'] === (string)$cat['id']) ? ' selected' : '';
    $category_options .= '<option value="' . (int)$cat['id'] . '"' . $selected . '>' . e($cat['name']) . '</option>';
}

$salary_options = salary_band_options_html($filters['min_salary']);
$experience_options = experience_level_options_html($filters['experience']);

  render('guest/find-jobs.html', [
      // These are pre-built HTML fragments that already escape their own data,
      // so they must not be escaped a second time by render().
      'job_list' => new RawHtml($job_html),
      'showing' => count($jobs),
      'total' => $total_count,
      'category_options' => new RawHtml($category_options),
      'type_options' => new RawHtml(job_type_filter_options_html($filters['type'])),
      'salary_options' => new RawHtml($salary_options),
      'experience_options' => new RawHtml($experience_options),
      'search_value' => new RawHtml(e($filters['search'])),
  ]);
