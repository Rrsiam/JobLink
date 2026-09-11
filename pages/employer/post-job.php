<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = get_user_id();
    $db = getDB();
    
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
    
    $stmt = $db->prepare("
        INSERT INTO jobs (employer_id, category_id, title, type, location, vacancy, 
                         salary_min, salary_max, education, experience, skills, 
                         description, responsibilities, benefits, deadline, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$user_id, $category, $title, $type, $location, $vacancy, 
                    $salary_min, $salary_max, $education, $experience, $skills, 
                    $description, $responsibilities, $benefits, $deadline]);
    
    $_SESSION['success'] = 'Job posted successfully!';
    header('Location: ?role=employer&page=manage-jobs');
    exit;
}

$categories = get_categories();
$category_options = '<option value="">Select Category</option>';
foreach ($categories as $cat) {
    $category_options .= "<option value='{$cat['id']}'>{$cat['name']}</option>";
}

render('employer/post-job.html', [
    'company_name' => 'My Company',
    'category_options' => $category_options
]);