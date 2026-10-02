<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

$user = get_user_data();
$profile = $db->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

// Handle profile update
// This page posts two separate forms: the profile form (btn_update) and the
// password form (btn_password). Both have to reach the handlers below.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['btn_update']) || isset($_POST['btn_password']))) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $certifications = trim($_POST['certifications'] ?? '');
    $languages = trim($_POST['languages'] ?? '');

    // Password change is handled here rather than on Settings.
    if (!empty($_POST['btn_password'])) {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $user_row = get_user_data();

        if (!password_verify($current, $user_row['password'])) {
            $_SESSION['error'] = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $_SESSION['error'] = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $_SESSION['error'] = 'Passwords do not match.';
        } else {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
            $_SESSION['success'] = 'Password updated successfully.';
        }
        header('Location: ?role=applicant&page=edit-profile');
        exit;
    }

    // The password branch above always exits, so reaching here means only the
    // profile form was submitted. Bail out rather than validating empty input.
    if (!isset($_POST['btn_update'])) {
        header('Location: ?role=applicant&page=edit-profile');
        exit;
    }

    if (empty($full_name) || empty($email)) {
        $_SESSION['error'] = 'Name and email are required.';
        header('Location: ?role=applicant&page=edit-profile');
        exit;
    }

    // Check email doesn't belong to another user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email already in use by another account.';
        header('Location: ?role=applicant&page=edit-profile');
        exit;
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?")
            ->execute([$full_name, $email, $phone, $user_id]);

        $stmt = $db->prepare("UPDATE applicant_profiles SET
            professional_title = ?, address = ?, gender = ?, career_objective = ?,
            education = ?, experience = ?, skills = ?, certifications = ?, languages = ?
            WHERE user_id = ?");
        $stmt->execute([$title, $location, $gender, $summary, $education, $experience, $skills, $certifications, $languages, $user_id]);

        if ($stmt->rowCount() === 0) {
            $db->prepare("INSERT INTO applicant_profiles (user_id, professional_title, address, gender, career_objective, education, experience, skills, certifications, languages) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$user_id, $title, $location, $gender, $summary, $education, $experience, $skills, $certifications, $languages]);
        }

        $db->commit();
        $_SESSION['name'] = $full_name;
        $_SESSION['success'] = 'Profile updated successfully.';
        header('Location: ?role=applicant&page=profile');
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Update failed: ' . $e->getMessage();
        header('Location: ?role=applicant&page=edit-profile');
        exit;
    }
}

$gender = $profile['gender'] ?? '';

// Resume state, so Edit Profile can show an inline preview too.
$resume_name = $profile['resume'] ?? '';
$has_resume = !empty($resume_name);

$gender_map = ['none' => '', 'female' => '', 'male' => '', 'other' => ''];
$gender_key = strtolower(trim($gender));
if (isset($gender_map[$gender_key]) && $gender_key !== '') {
    $gender_map[$gender_key] = 'selected';
} else {
    $gender_map['none'] = 'selected';
}

// Escaped here and marked RawHtml: render() treats any scalar containing '<'
// as pre-built markup, so passing raw DB values would let them inject HTML
// into the value="" attributes and textareas.
render('applicant/edit-profile.html', [
    'name' => new RawHtml(e($user['name'])),
    'email' => new RawHtml(e($user['email'])),
    'phone' => new RawHtml(e($user['phone'] ?? '')),
    'title' => new RawHtml(e($profile['professional_title'] ?? '')),
    'location' => new RawHtml(e($profile['address'] ?? '')),
    'about' => new RawHtml(e($profile['career_objective'] ?? '')),
    'education' => new RawHtml(e($profile['education'] ?? '')),
    'experience' => new RawHtml(e($profile['experience'] ?? '')),
    'skills' => new RawHtml(e($profile['skills'] ?? '')),
    'certifications' => new RawHtml(e($profile['certifications'] ?? '')),
    'languages' => new RawHtml(e($profile['languages'] ?? '')),
    'gender_none' => $gender_map['none'],
    'gender_female' => $gender_map['female'],
    'gender_male' => $gender_map['male'],
    'gender_other' => $gender_map['other'],
    'account_type' => 'Applicant',
    'resume_file' => new RawHtml(e($resume_name)),
    'notification' => new RawHtml(flash_message())
]);