<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$filters = normalise_job_filters($_GET);

$db = getDB();

// The result list and the "showing X of Y" total share one WHERE builder, so the
// two counts can never drift apart.
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
    $any_jobs = (int)$db->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn();
    if ($any_jobs === 0) {
        $job_html = "<div class='empty-state' style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>
            <div style='font-size:40px;margin-bottom:12px;'>&#128233;</div>
            <h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Jobs Posted Yet</h3>
            <p style='font-size:14px;'>Employers haven't posted any jobs yet. Check back later.</p>
        </div>";
    } else {
        $job_html = "<div class='empty-state' style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px;text-align:center;color:#64748b;'>
            <div style='font-size:40px;margin-bottom:12px;'>&#128269;</div>
            <h3 style='font-size:18px;margin-bottom:6px;color:#334155;'>No Jobs Found</h3>
            <p style='font-size:14px;'>No jobs match your filters. Try a different keyword, category, or type.</p>
            <a href='?role=applicant&page=find-jobs' class='btn btn-outline btn-sm' style='margin-top:12px;'>Clear All Filters</a>
        </div>";
    }
}
foreach ($jobs as $job) {
    // Everything below is employer-supplied, so it is escaped on the way out.
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

    // Applying runs through job-details.php, which enforces the profile check and
    // guards against duplicate applications. The fragment drops the applicant on
    // the apply button once the details page loads.
    $apply_url = "?role=applicant&page=job-details&id={$job_id}&apply=1#action-bar";
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
            <a href='?role=applicant&page=job-details&id={$job_id}' class='btn btn-outline btn-sm'>View Details</a>
            <a href='{$apply_url}' class='btn btn-primary btn-sm'>Apply Now</a>
        </div>
    </div>";
}

// Filter dropdowns. Category options are built from the table, the rest come from
// the shared helpers in config.php so both search pages stay in step. There is no
// Job Type dropdown on either page any more.
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$category_options = '<option value="">All Categories</option>';
foreach ($categories as $cat) {
    $selected = ((string)$filters['category'] === (string)$cat['id']) ? ' selected' : '';
    $category_options .= '<option value="' . (int)$cat['id'] . '"' . $selected . '>' . e($cat['name']) . '</option>';
}

$salary_options = salary_band_options_html($filters['min_salary']);
$experience_options = experience_level_options_html($filters['experience']);

$user = get_user_data();

$filters_active = [];
if ($filters['search'] !== '')     $filters_active[] = 'Search: "' . e($filters['search']) . '"';
if ($filters['location'] !== '')   $filters_active[] = 'Location: ' . e($filters['location']);
if ($filters['category'] > 0) {
    $cat_stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $cat_stmt->execute([$filters['category']]);
    $cat_name = $cat_stmt->fetchColumn();
    if ($cat_name !== false && $cat_name !== '') {
        $filters_active[] = 'Category: ' . e($cat_name);
    }
}
if ($filters['type'] !== '')       $filters_active[] = 'Type: ' . e($filters['type']);
if ($filters['min_salary'] !== '') $filters_active[] = 'Min salary: ' . e(salary_bands()[(int)$filters['min_salary']]);
if ($filters['experience'] !== '') $filters_active[] = 'Experience: ' . e($filters['experience']);

$filter_note = '';
if (count($filters_active) > 0) {
    $filter_note = '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin:0 0 16px;color:#1e40af;font-size:14px;">Active filters: '
        . implode(' &nbsp;·&nbsp; ', $filters_active)
        . ' &nbsp;<a href="?role=applicant&page=find-jobs" style="color:#2563eb;font-weight:600;">Clear</a></div>';
}

  render('applicant/find-jobs.html', [
      'name' => $user['name'] ?? $_SESSION['name'] ?? 'Applicant',
      // Pre-built HTML fragments that already escape their own data.
      'job_list' => new RawHtml($job_html),
      'showing' => count($jobs),
      'total' => $total_count,
      'category_options' => new RawHtml($category_options),
      'type_options' => new RawHtml(job_type_filter_options_html($filters['type'])),
      'salary_options' => new RawHtml($salary_options),
      'experience_options' => new RawHtml($experience_options),
      'search_value' => new RawHtml(e($filters['search'])),
      'filter_note' => $filter_note
  ]);
