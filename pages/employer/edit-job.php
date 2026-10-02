<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$job_id = (int)($_GET['id'] ?? $_POST['job_id'] ?? 0);
$user_id = get_user_id();
$db = getDB();

$job = $db->prepare("SELECT * FROM jobs WHERE id = ? AND employer_id = ?")->execute([$job_id, $user_id])->fetch();

if (!$job) {
    redirect_with_flash('?role=employer&page=manage-jobs', 'Job not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['job_title'] ?? '');
    $category = $_POST['category'] ?: null;
    $type = normalise_job_type($_POST['job_type'] ?? $job['type']);
    $location = trim($_POST['location'] ?? '');
    $vacancy = max(1, (int)($_POST['vacancies'] ?? 1));
    $salary_min = $_POST['min_salary'] ?: null;
    $salary_max = $_POST['max_salary'] ?: null;
    $education = $_POST['education'] ?? '';
    $experience = $_POST['experience'] ?? '';
    $skills = $_POST['skills'] ?? '';
    $description = $_POST['description'] ?? '';
    $responsibilities = $_POST['responsibilities'] ?? '';
    $benefits = $_POST['benefits'] ?? '';
    // An empty or invalid date input must not silently reset the deadline.
    $deadline = $_POST['deadline'] ?? '';
    if (!$deadline || strtotime($deadline) === false) {
        $deadline = $job['deadline'] ?: date('Y-m-d', strtotime('+30 days'));
    }

    if ($title === '' || $location === '') {
        redirect_with_flash('?role=employer&page=edit-job&id=' . $job_id, 'Job title and location are required.', 'error');
    }

    $db->prepare("UPDATE jobs SET 
        title = ?, category_id = ?, type = ?, location = ?, vacancy = ?,
        salary_min = ?, salary_max = ?, education = ?, experience = ?, 
        skills = ?, description = ?, responsibilities = ?, benefits = ?, deadline = ?
        WHERE id = ? AND employer_id = ?")->execute([
        $title, $category, $type, $location, $vacancy,
        $salary_min, $salary_max, $education, $experience,
        $skills, $description, $responsibilities, $benefits, $deadline, $job_id, $user_id
    ]);
    
    redirect_with_flash('?role=employer&page=job-details&id=' . $job_id, 'Job updated.');
}

$categories = get_categories();
$category_options = '<option value="">Select Category</option>';
foreach ($categories as $cat) {
    $selected = ($cat['id'] == $job['category_id']) ? 'selected' : '';
    $category_options .= "<option value='{$cat['id']}' $selected>{$cat['name']}</option>";
}

// Preserve the stored job type instead of always defaulting the select to the
// first option.
$job_type_options = job_type_options_html($job['type']);
if ($job['type'] && !in_array(strtolower($job['type']), array_map('strtolower', job_types()), true)) {
    // A legacy value outside the enum still needs to be visible, otherwise
    // opening the form would quietly change the posting's type.
    $job_type_options .= "<option value='" . e($job['type']) . "' selected>" . e($job['type']) . "</option>";
}

$company_name = 'My Company';
$ep = $db->prepare("SELECT company_name FROM employer_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
if ($ep && !empty($ep['company_name'])) {
    $company_name = $ep['company_name'];
} else {
    $u = $db->prepare("SELECT name FROM users WHERE id = ?")->execute([$user_id])->fetch();
    if ($u && !empty($u['name'])) $company_name = $u['name'];
}

render('employer/edit-job.html', [
    'company_name' => $company_name,
    'job_title' => $job['title'],
    'location' => $job['location'],
    'vacancies' => $job['vacancy'] ?? '',
    'min_salary' => $job['salary_min'] ?? '',
    'max_salary' => $job['salary_max'] ?? '',
    'description' => $job['description'],
    'responsibilities_raw' => $job['responsibilities'] ?? '',
    'education' => $job['education'] ?? '',
    'experience' => $job['experience'] ?? '',
    'skills' => $job['skills'] ?? '',
    'benefits_raw' => $job['benefits'] ?? '',
    'deadline' => $job['deadline'] ? date('Y-m-d', strtotime($job['deadline'])) : '',
    'category_options' => new RawHtml($category_options),
    'notification' => flash_message(),
    'job_type_options' => new RawHtml($job_type_options),
    'job_id' => $job['id']
]);