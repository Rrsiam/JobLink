<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

$user = get_user_data();
$profile = $db->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_update'])) {
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
$gender_map = ['none' => '', 'female' => '', 'male' => '', 'other' => ''];
$gender_key = strtolower(trim($gender));
if (isset($gender_map[$gender_key]) && $gender_key !== '') {
    $gender_map[$gender_key] = 'selected';
} else {
    $gender_map['none'] = 'selected';
}

render('applicant/edit-profile.html', [
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?? '',
    'title' => $profile['professional_title'] ?? '',
    'location' => $profile['address'] ?? '',
    'about' => $profile['career_objective'] ?? '',
    'education' => $profile['education'] ?? '',
    'experience' => $profile['experience'] ?? '',
    'skills' => $profile['skills'] ?? '',
    'certifications' => $profile['certifications'] ?? '',
    'languages' => $profile['languages'] ?? '',
    'gender_none' => $gender_map['none'],
    'gender_female' => $gender_map['female'],
    'gender_male' => $gender_map['male'],
    'gender_other' => $gender_map['other'],
    'notification' => flash_message()
]);