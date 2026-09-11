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

// Display applicant registration form
render('guest/create-applicant.html', ['error' => flash_message()]);