<?php
require_once __DIR__ . '/config.php';
session_start();

// Clear all session data and remove the session cookie
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

header('Location: ' . BASE_URL . '?page=home');
exit;