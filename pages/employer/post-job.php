<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = get_user_id();
    $db = getDB();
    
    $title = trim($_POST['job_title'] ?? '');
    $category = $_POST['category'] ?: null;
    $type = normalise_job_type($_POST['job_type'] ?? '');
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
    $deadline = $_POST['deadline'] ?? '';
    if (!$deadline || strtotime($deadline) === false) {
        $deadline = date('Y-m-d', strtotime('+30 days'));
    }
    // "Save Draft" and "Publish Job" submit the same form, so the intent comes
    // from the button that was pressed instead of being hardcoded to active.
    $status = ($_POST['status'] ?? 'active') === 'draft' ? 'draft' : 'active';

    if ($title === '' || $location === '') {
        $_SESSION['error'] = 'Job title and location are required.';
    } else {
        $stmt = $db->prepare("
            INSERT INTO jobs (employer_id, category_id, title, type, location, vacancy, 
                             salary_min, salary_max, education, experience, skills, 
                             description, responsibilities, benefits, deadline, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $category, $title, $type, $location, $vacancy, 
                        $salary_min, $salary_max, $education, $experience, $skills, 
                        $description, $responsibilities, $benefits, $deadline, $status]);
        
        redirect_with_flash(
            '?role=employer&page=manage-jobs',
            $status === 'draft' ? 'Job saved as a draft.' : 'Job posted successfully!'
        );
    }
}

$categories = get_categories();
$category_options = '<option value="">Select Category</option>';
foreach ($categories as $cat) {
    $category_options .= "<option value='{$cat['id']}'>{$cat['name']}</option>";
}

$company_name = 'My Company';
$ep = $db->prepare("SELECT company_name FROM employer_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
if ($ep && !empty($ep['company_name'])) {
    $company_name = $ep['company_name'];
} else {
    $u = $db->prepare("SELECT name FROM users WHERE id = ?")->execute([$user_id])->fetch();
    if ($u && !empty($u['name'])) $company_name = $u['name'];
}

render('employer/post-job.html', [
    'company_name' => $company_name,
    'category_options' => new RawHtml(category_options_html()),
    'type_options' => new RawHtml(job_type_options_html()),
    'job_types' => job_types(),
    'notification' => new RawHtml(flash_message())
]);