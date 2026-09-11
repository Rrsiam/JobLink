<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete account
    if (isset($_POST['delete_account'])) {
        if (!password_verify($_POST['delete_password'] ?? '', (get_user_data()['password']))) {
            $_SESSION['error'] = 'Incorrect password. Account was not deleted.';
            header('Location: ?role=employer&page=settings');
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
    
    header('Location: ?role=employer&page=settings');
    exit;
}

$user = get_user_data();

render('employer/settings.html', [
    'company_name' => 'My Company',
    'full_name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?: '',
    'notification' => flash_message()
]);