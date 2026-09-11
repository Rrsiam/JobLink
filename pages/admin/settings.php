<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin accounts cannot be deleted through the application.
    if (isset($_POST['delete_account'])) {
        $_SESSION['error'] = 'Admin accounts cannot be deleted through the application.';
        header('Location: ?role=admin&page=settings');
        exit;
    }

    // Update admin password
    if (!empty($_POST['new_password'])) {
        $user = get_user_data();
        if (!password_verify($_POST['current_password'] ?? '', $user['password'])) {
            $_SESSION['error'] = 'Current password is incorrect';
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $_SESSION['error'] = 'Passwords do not match';
        } else {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, get_user_id()]);
            $_SESSION['success'] = 'Password updated successfully';
        }
    }
    header('Location: ?role=admin&page=settings');
    exit;
}

$user = get_user_data();

render('admin/settings.html', [
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?: '',
    'notification' => flash_message()
]);