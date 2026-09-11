<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$job_id = $_GET['id'] ?? 0;
$user_id = get_user_id();
$db = getDB();

$job = $db->prepare("SELECT * FROM jobs WHERE id = ? AND employer_id = ?")->execute([$job_id, $user_id])->fetch();

if (!$job) {
    header('Location: ?role=employer&page=manage-jobs');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['job_title'] ?? '';
    $category = $_POST['category'] ?? null;
    $type = $_POST['job_type'] ?? 'Full-time';
    $location = $_POST['location'] ?? '';
    $vacancy = $_POST['vacancies'] ?? 1;
    $salary_min = $_POST['min_salary'] ?: null;
    $salary_max = $_POST['max_salary'] ?: null;
    $education = $_POST['education'] ?? '';
    $experience = $_POST['experience'] ?? '';
    $skills = $_POST['skills'] ?? '';
    $description = $_POST['description'] ?? '';
    $responsibilities = $_POST['responsibilities'] ?? '';
    $benefits = $_POST['benefits'] ?? '';
    $deadline = $_POST['deadline'] ?? date('Y-m-d', strtotime('+30 days'));
    
    $db->prepare("UPDATE jobs SET 
        title = ?, category_id = ?, type = ?, location = ?, vacancy = ?,
        salary_min = ?, salary_max = ?, education = ?, experience = ?, 
        skills = ?, description = ?, responsibilities = ?, benefits = ?, deadline = ?
        WHERE id = ?")->execute([
        $title, $category, $type, $location, $vacancy,
        $salary_min, $salary_max, $education, $experience,
        $skills, $description, $responsibilities, $benefits, $deadline, $job_id
    ]);
    
    header('Location: ?role=employer&page=job-details&id=' . $job_id . '&success=Job updated');
    exit;
}

$categories = get_categories();
$category_options = '<option value="">Select Category</option>';
foreach ($categories as $cat) {
    $selected = ($cat['id'] == $job['category_id']) ? 'selected' : '';
    $category_options .= "<option value='{$cat['id']}' $selected>{$cat['name']}</option>";
}

render('employer/edit-job.html', [
    'company_name' => 'My Company',
    'job_title' => $job['title'],
    'location' => $job['location'],
    'vacancies' => $job['vacancy'],
    'min_salary' => $job['salary_min'],
    'max_salary' => $job['salary_max'],
    'description' => $job['description'],
    'responsibilities_raw' => $job['responsibilities'],
    'education' => $job['education'],
    'experience' => $job['experience'],
    'skills' => $job['skills'],
    'benefits_raw' => $job['benefits'],
    'deadline' => $job['deadline'],
    'category_options' => $category_options,
    'job_types' => ['Full-time', 'Part-time', 'Internship', 'Remote']
]);