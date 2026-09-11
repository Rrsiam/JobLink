<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete account
    if (isset($_POST['delete_account'])) {
        if (!password_verify($_POST['delete_password'] ?? '', (get_user_data()['password']))) {
            $_SESSION['error'] = 'Incorrect password. Account was not deleted.';
            header('Location: ?role=applicant&page=settings');
            exit;
        }
        delete_user_account($user_id);
        destroy_session();
        header('Location: ?page=sign-in&account-deleted=1');
        exit;
    }

    // Password change
    if (!empty($_POST['new_password'])) {
        $user = get_user_data();
        if (!password_verify($_POST['current_password'] ?? '', $user['password'])) {
            $_SESSION['error'] = 'Current password is incorrect';
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $_SESSION['error'] = 'Passwords do not match';
        } else {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
            $_SESSION['success'] = 'Password updated successfully';
        }
    }
    
    // Update notification settings
    if (isset($_POST['settings'])) {
        $s = $_POST['settings'];
        $db->prepare("UPDATE user_settings SET 
            job_recommendations = ?, status_updates = ?, 
            employer_messages = ?, weekly_summary = ? 
            WHERE user_id = ?")->execute([
            isset($s['recommendations']) ? 1 : 0,
            isset($s['status_updates']) ? 1 : 0,
            isset($s['employer_messages']) ? 1 : 0,
            isset($s['weekly_summary']) ? 1 : 0,
            $user_id
        ]);
        $_SESSION['success'] = 'Settings updated';
    }
    
    header('Location: ?role=applicant&page=settings');
    exit;
}

$user = get_user_data();
$settings = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?")->execute([$user_id])->fetch();

render('applicant/settings.html', [
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?: '',
    'notif_recommendations' => !empty($settings['job_recommendations']) ? 'checked' : '',
    'notif_status_updates' => !empty($settings['status_updates']) ? 'checked' : '',
    'notif_employer_messages' => !empty($settings['employer_messages']) ? 'checked' : '',
    'notif_weekly_summary' => !empty($settings['weekly_summary']) ? 'checked' : '',
    'notification' => flash_message()
]);