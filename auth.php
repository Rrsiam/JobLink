<?php
require_once __DIR__ . '/config.php';

$action = $_GET['page'] ?? 'sign-in';

// Handle login
if ($action === 'sign-in' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        
        // Redirect to dashboard
        $role = $user['role'];
        header("Location: ?role={$role}&page=dashboard");
        exit;
    } else {
        $_SESSION['error'] = 'Invalid email or password.';
        header('Location: ?page=sign-in');
        exit;
    }
}

// Handle registration (role selection)
if ($action === 'sign-up' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    if ($role === 'applicant') {
        header('Location: ?page=create-applicant');
        exit;
    } elseif ($role === 'employer') {
        header('Location: ?page=create-employer');
        exit;
    } elseif ($role === 'admin') {
        // Admin creation should be restricted, but for demo we redirect
        $_SESSION['error'] = 'Admin accounts are provisioned by the system administrator. Please sign in with an existing account.';
        header('Location: ?page=sign-in');
        exit;
    } else {
        $_SESSION['error'] = 'Please select an account type.';
        header('Location: ?page=sign-up');
        exit;
    }
}

// Handle applicant registration
if ($action === 'create-applicant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $address = $_POST['address'] ?? '';
    $education = $_POST['education'] ?? '';
    $experience = $_POST['experience'] ?? '';
    $skills = $_POST['skills'] ?? '';
    
    // Validate
    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
        header('Location: ?page=create-applicant');
        exit;
    }
    
    if ($password !== $confirm) {
        $_SESSION['error'] = 'Passwords do not match.';
        header('Location: ?page=create-applicant');
        exit;
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters.';
        header('Location: ?page=create-applicant');
        exit;
    }
    
    $db = getDB();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email already registered.';
        header('Location: ?page=create-applicant');
        exit;
    }
    
    $db->beginTransaction();
    
    try {
        // Insert user
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'applicant', 'active')");
        $stmt->execute([$name, $email, $phone, $hashed]);
        $user_id = $db->lastInsertId();
        
        // Insert applicant profile
        $stmt = $db->prepare("INSERT INTO applicant_profiles (user_id, address, education, experience, skills) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $address, $education, $experience, $skills]);
        
        // Insert user settings
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        
        $db->commit();
        
        // Auto-login
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'applicant';
        $_SESSION['name'] = $name;
        
        header('Location: ?role=applicant&page=dashboard');
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
        header('Location: ?page=create-applicant');
        exit;
    }
}

// Handle employer registration
if ($action === 'create-employer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['full_name'] ?? '';
    $company = $_POST['company_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $address = $_POST['address'] ?? '';
    $industry = $_POST['industry'] ?? '';
    $website = $_POST['website'] ?? '';
    $description = $_POST['description'] ?? '';

    // Validate
    if (empty($name) || empty($company) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
        header('Location: ?page=create-employer');
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['error'] = 'Passwords do not match.';
        header('Location: ?page=create-employer');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters.';
        header('Location: ?page=create-employer');
        exit;
    }

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
            header('Location: ?page=create-employer');
            exit;
        }
        $uploads_dir = __DIR__ . '/uploads/logos';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }
        $logo_file = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $exts[$_FILES['logo']['type']];
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploads_dir . '/' . $logo_file)) {
            $_SESSION['error'] = 'Failed to save the uploaded logo.';
            header('Location: ?page=create-employer');
            exit;
        }
    }
    
    $db = getDB();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email already registered.';
        header('Location: ?page=create-employer');
        exit;
    }
    
    $db->beginTransaction();
    
    try {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'employer', 'pending')");
        $stmt->execute([$name, $email, $phone, $hashed]);
        $user_id = $db->lastInsertId();
        
        $stmt = $db->prepare("INSERT INTO employer_profiles (user_id, company_name, industry, address, website, description, verified, logo) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
        $stmt->execute([$user_id, $company, $industry, $address, $website, $description, $logo_file]);
        
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        
        $db->commit();
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'employer';
        $_SESSION['name'] = $name;
        
        header('Location: ?role=employer&page=dashboard');
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
        header('Location: ?page=create-employer');
        exit;
    }
}

// Display login page if not POST
if ($action === 'sign-in') {
    if (is_logged_in()) {
        $role = get_user_role();
        header("Location: ?role={$role}&page=dashboard");
        exit;
    }
    render('guest/sign-in.html', ['error' => flash_message()]);
    exit;
}

// Display signup page
if ($action === 'sign-up') {
    if (is_logged_in()) {
        $role = get_user_role();
        header("Location: ?role={$role}&page=dashboard");
        exit;
    }
    render('guest/sign-up.html', ['error' => flash_message()]);
    exit;
}

// Display applicant registration form
if ($action === 'create-applicant') {
    if (is_logged_in()) {
        $role = get_user_role();
        header("Location: ?role={$role}&page=dashboard");
        exit;
    }
    render('guest/create-applicant.html', ['error' => flash_message()]);
    exit;
}

// Display employer registration form
if ($action === 'create-employer') {
    if (is_logged_in()) {
        $role = get_user_role();
        header("Location: ?role={$role}&page=dashboard");
        exit;
    }
    render('guest/create-employer.html', ['error' => flash_message()]);
    exit;
}