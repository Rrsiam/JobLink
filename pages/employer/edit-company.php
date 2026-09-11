<?php
require_once __DIR__ . '/../../config.php';
require_role('employer');

$user_id = get_user_id();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = $_POST['company_name'] ?? '';
    $tagline = $_POST['tagline'] ?? '';
    $industry = $_POST['industry'] ?? '';
    $size = $_POST['size'] ?? '';
    $location = $_POST['location'] ?? '';
    $website = $_POST['website'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $about = $_POST['about'] ?? '';
    $benefits = $_POST['benefits'] ?? '';

    // Validate + store logo (optional)
    $logo_file = null;
    if (!empty($_FILES['logo']['name'])) {
        $allowed = ['image/png', 'image/jpeg', 'image/svg+xml'];
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg'];
        $logo_error = null;
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $logo_error = 'Logo upload failed with error code ' . $_FILES['logo']['error'] . '.';
        } elseif (!in_array($_FILES['logo']['type'], $allowed)) {
            $logo_error = 'Logo must be a PNG, JPG, or SVG file.';
        } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $logo_error = 'Logo must be 2 MB or smaller.';
        }
        if ($logo_error) {
            $_SESSION['error'] = $logo_error;
            header('Location: ?role=employer&page=edit-company');
            exit;
        }
        $uploads_dir = __DIR__ . '/../../uploads/logos';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }
        $logo_file = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $exts[$_FILES['logo']['type']];
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploads_dir . '/' . $logo_file)) {
            $_SESSION['error'] = 'Failed to save the uploaded logo.';
            header('Location: ?role=employer&page=edit-company');
            exit;
        }
    }

    // Update user
    $db->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?")->execute([$email, $phone, $user_id]);

    // Update profile (logo only if a new one was uploaded)
    if ($logo_file) {
        $old_logo = $db->prepare("SELECT logo FROM employer_profiles WHERE user_id = ?")->execute([$user_id])->fetchColumn();
        $db->prepare("UPDATE employer_profiles SET 
            company_name = ?, industry = ?, address = ?, 
            website = ?, company_size = ?, description = ?, 
            benefits = ?, logo = ? 
            WHERE user_id = ?")->execute([
            $company_name, $industry, $location,
            $website, $size, $about, $benefits, $logo_file, $user_id
        ]);
        if ($old_logo) {
            $old_path = __DIR__ . '/../../uploads/logos/' . $old_logo;
            if (is_file($old_path)) {
                @unlink($old_path);
            }
        }
    } else {
        $db->prepare("UPDATE employer_profiles SET 
            company_name = ?, industry = ?, address = ?, 
            website = ?, company_size = ?, description = ?, 
            benefits = ? 
            WHERE user_id = ?")->execute([
            $company_name, $industry, $location,
            $website, $size, $about, $benefits, $user_id
        ]);
    }
    
    header('Location: ?role=employer&page=company-profile&success=Profile updated');
    exit;
}

$profile = $db->prepare("
    SELECT u.*, ep.* 
    FROM users u 
    JOIN employer_profiles ep ON u.id = ep.user_id 
    WHERE u.id = ?
")->execute([$user_id])->fetch();

render('employer/edit-company.html', [
    'company_name' => $profile['company_name'] ?? '',
    'tagline' => '',
    'industry' => $profile['industry'] ?? '',
    'size' => $profile['company_size'] ?? '',
    'location' => $profile['address'] ?? '',
    'website' => $profile['website'] ?? '',
    'phone' => $profile['phone'] ?? '',
    'email' => $profile['email'] ?? '',
    'about' => $profile['description'] ?? '',
    'benefits_raw' => $profile['benefits'] ?? ''
]);