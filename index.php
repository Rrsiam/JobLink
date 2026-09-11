<?php
session_start();
require_once __DIR__ . '/config.php';

$role = $_GET['role'] ?? 'guest';
$page = $_GET['page'] ?? 'home';

$role = preg_replace('/[^a-zA-Z]/', '', $role);
$page  = preg_replace('/[^a-zA-Z\-]/', '', $page);

// Logout: clear session and return to guest home
if ($page === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ?page=home');
    exit;
}

// Auth actions (login / registration) are handled by auth.php
$auth_pages = ['sign-in', 'sign-up', 'create-applicant', 'create-employer'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($page, $auth_pages)) {
    include __DIR__ . '/auth.php';
    exit;
}

$controller = __DIR__ . "/pages/{$role}/{$page}.php";

if (file_exists($controller)) {
    include $controller;
} else {
    // Fallback to guest home
    include __DIR__ . "/pages/guest/home.php";
}