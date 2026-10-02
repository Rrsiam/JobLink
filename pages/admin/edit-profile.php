<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$user_id = get_user_id();
$db = getDB();

$user = get_user_data();
$profile = $db->prepare("SELECT * FROM admin_profiles WHERE user_id = ?")->execute([$user_id])->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = admin_profile_save($db, $user_id, $_POST, $_FILES['photo'] ?? [], trim($profile['photo'] ?? ''));
    if ($error !== null) {
        $_SESSION['error'] = $error;
    }
    header('Location: ?role=admin&page=profile');
    exit;
}

$photo = trim($profile['photo'] ?? '');
$has_photo = $photo !== '' && is_file(__DIR__ . '/../../uploads/photos/' . $photo);

// Escaped here and wrapped in RawHtml, because render() treats any scalar
// containing '<' as pre-built markup and would let DB values inject into
// the value="" attributes and textareas.
render('admin/edit-profile.html', [
    'name' => new RawHtml(e($user['name'] ?? '')),
    'email' => new RawHtml(e($user['email'] ?? '')),
    'phone' => new RawHtml(e($user['phone'] ?? '')),
    'title' => new RawHtml(e($profile['professional_title'] ?? '')),
    'department' => new RawHtml(e($profile['department'] ?? '')),
    'address' => new RawHtml(e($profile['address'] ?? '')),
    'photo_preview' => new RawHtml(
        $has_photo
            ? '<img src="uploads/photos/' . e($photo) . '" alt="' . e($user['name'] ?? 'Admin') . '">'
            : ''
    ),
    'photo_show' => $has_photo ? 'flex' : 'none',
    'notification' => new RawHtml(flash_message()),
]);