<?php
// This file is handled by auth.php
// But we keep it for routing consistency
require_once __DIR__ . '/../../config.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    $role = get_user_role();
    header("Location: ?role={$role}&page=dashboard");
    exit;
}

// Display login page
$success = isset($_GET['account-deleted']) ? '<div class="form-success">Your account and all associated data have been deleted permanently.</div>' : '';
render('guest/sign-in.html', ['error' => flash_message(), 'account_notice' => $success]);