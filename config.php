<?php
// Database settings (replace with your own)
define('DB_HOST', 'localhost');
define('DB_NAME', 'joblink');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL – auto-detect from current request
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
define('BASE_URL', $scriptDir . '/');

// PDO statement wrapper so that ->execute(...)->fetch() chaining works
// (plain PDOStatement::execute() returns bool, not the statement)
class AppPDOStatement {
    private $stmt;

    public function __construct(PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    public function __call($method, $args) {
        return call_user_func_array([$this->stmt, $method], $args);
    }

    public function execute($params = null) {
        $this->stmt->execute($params);
        return $this;
    }

    public function fetch($mode = null) {
        return $mode === null ? $this->stmt->fetch() : $this->stmt->fetch($mode);
    }

    public function fetchAll($mode = null) {
        return $mode === null ? $this->stmt->fetchAll() : $this->stmt->fetchAll($mode);
    }

    public function fetchColumn($column = 0) {
        return $this->stmt->fetchColumn($column);
    }
}

class AppPDO extends PDO {
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = []) {
        return new AppPDOStatement(parent::prepare($query, $options));
    }
}

// Database connection (PDO singleton)
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new AppPDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

// Render engine: loads HTML template and replaces {{key}} with $data['key']
function render($template, $data = []) {
    $path = __DIR__ . '/templates/' . $template;
    if (!file_exists($path)) {
        die("Template not found: $template");
    }
    $html = file_get_contents($path);
    foreach ($data as $key => $value) {
        if ($value === null || !is_scalar($value)) {
            continue;
        }
        $value = (string)$value;
        $replacement = (strpos($value, '<') !== false) ? $value : htmlspecialchars($value);
        $html = str_replace('{{' . $key . '}}', $replacement, $html);
    }
    // Handle loops: we'll use a simple placeholder for job lists, etc.
    // For this demo, we pass pre‑rendered HTML strings for complex lists.
    echo $html;
}

// Session helpers
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role'] ?? 'guest';
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_data() {
    $id = get_user_id();
    if (!$id) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $user = getDB()->prepare("SELECT * FROM users WHERE id = ?")->execute([$id])->fetch();
    }
    return $user;
}

function require_role($role) {
    if (!is_logged_in()) {
        header('Location: ?page=sign-in');
        exit;
    }
    if (get_user_role() !== $role) {
        header('Location: ?role=' . get_user_role() . '&page=dashboard');
        exit;
    }
}

function get_categories() {
    return getDB()->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
}

// Check whether an applicant's profile is complete enough to apply for jobs.
// Returns array of missing field labels (empty array = complete).
function applicant_profile_missing() {
    $user_id = get_user_id();
    if (!$user_id) {
        return ['Profile'];
    }
    $db = getDB();
    $user = get_user_data();
    $profile = $db->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

    $checks = [
        'Phone number'               => !empty($user['phone']),
        'Professional title'         => !empty($profile['professional_title']),
        'Location / address'         => !empty($profile['address']),
        'Career objective'           => !empty($profile['career_objective']),
        'Education'                  => !empty($profile['education']),
        'Work experience'            => !empty($profile['experience']),
        'Skills'                     => !empty($profile['skills']),
    ];

    $missing = [];
    foreach ($checks as $label => $ok) {
        if (!$ok) {
            $missing[] = $label;
        }
    }
    return $missing;
}

// Build a styled flash message from $_SESSION['error'] / $_SESSION['success']
function flash_message() {
    if (isset($_SESSION['error']) && $_SESSION['error'] !== '') {
        $msg = $_SESSION['error'];
        unset($_SESSION['error']);
        return '<div class="form-error">' . htmlspecialchars($msg) . '</div>';
    }
    if (isset($_SESSION['success']) && $_SESSION['success'] !== '') {
        $msg = $_SESSION['success'];
        unset($_SESSION['success']);
        return '<div class="form-success">' . htmlspecialchars($msg) . '</div>';
    }
    return '';
}

// Permanently delete a user account together with every record and file
// that belongs to it (profiles, settings, jobs, applications, saved jobs,
// uploads). Returns true on success, false if the account does not exist.
function delete_user_account($user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return false;
    }
    $db = getDB();

    $user = $db->prepare("SELECT * FROM users WHERE id = ?")->execute([$user_id])->fetch();
    if (!$user) {
        return false;
    }

    // Admin accounts are protected: they can only be removed directly
    // at the database level (see BEFORE DELETE trigger on `users`).
    if ($user['role'] === 'admin') {
        return false;
    }

    $uploads_dir = __DIR__ . '/uploads';

    // Collect files owned by this user so they are removed too.
    $files = [];
    if ($user['role'] === 'applicant') {
        $profile = $db->prepare("SELECT resume, photo FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
        if ($profile) {
            if (!empty($profile['resume']))  $files[] = $uploads_dir . '/resumes/' . $profile['resume'];
            if (!empty($profile['photo']))   $files[] = $uploads_dir . '/photos/' . $profile['photo'];
        }
    } elseif ($user['role'] === 'employer') {
        $profile = $db->prepare("SELECT logo, cover FROM employer_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
        if ($profile) {
            if (!empty($profile['logo']))  $files[] = $uploads_dir . '/logos/' . $profile['logo'];
            if (!empty($profile['cover'])) $files[] = $uploads_dir . '/covers/' . $profile['cover'];
        }
    }

    // Delete the user row. Foreign keys (ON DELETE CASCADE) remove
    // applicant/employer profiles, user_settings, saved_jobs,
    // applications, and the employer's jobs + their received applications.
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

    // Remove uploaded files from disk (best-effort).
    foreach ($files as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    return true;
}

// Destroy the current session (used after account deletion / logout).
function destroy_session() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// Dummy data for demo (replace with DB queries)
function get_jobs() {
    return [
        ['id'=>1, 'title'=>'Senior Frontend Developer', 'company'=>'TechNova Inc.', 'location'=>'San Francisco, CA', 'salary'=>'$95k – $130k', 'type'=>'Full-time', 'tags'=>['React','TypeScript','GraphQL']],
        ['id'=>2, 'title'=>'Marketing Manager', 'company'=>'GrowthBridge', 'location'=>'Austin, TX', 'salary'=>'$70k – $90k', 'type'=>'Full-time', 'tags'=>['SEO','Content','Analytics']],
        ['id'=>3, 'title'=>'Registered Nurse - ICU', 'company'=>'Valley Medical Center', 'location'=>'Phoenix, AZ', 'salary'=>'$75k – $95k', 'type'=>'Full-time', 'tags'=>['ICU','BLS','ACLS']],
        ['id'=>4, 'title'=>'Data Analyst', 'company'=>'Datasphere Co.', 'location'=>'Chicago, IL', 'salary'=>'$65k – $85k', 'type'=>'Full-time', 'tags'=>['SQL','Python','Tableau']],
        ['id'=>5, 'title'=>'UX/UI Designer', 'company'=>'PixelCraft Studio', 'location'=>'New York, NY', 'salary'=>'$80k – $110k', 'type'=>'Full-time', 'tags'=>['Figma','User Research','Prototyping']],
    ];
}
function get_applications() {
    return [
        ['job'=>'Senior Frontend Developer', 'company'=>'TechNova Inc.', 'date'=>'Jul 14, 2025', 'status'=>'Shortlisted'],
        ['job'=>'Marketing Manager', 'company'=>'GrowthBridge', 'date'=>'Jul 12, 2025', 'status'=>'Under Review'],
        ['job'=>'Data Analyst', 'company'=>'Datasphere Co.', 'date'=>'Jul 7, 2025', 'status'=>'Rejected'],
    ];
}
function get_companies() {
    return [
        ['name'=>'TechNova Inc.', 'industry'=>'Technology · San Francisco, CA', 'desc'=>'Next‑gen developer tools used by 40,000 engineers.', 'jobs'=>14],
        ['name'=>'GrowthBridge', 'industry'=>'Marketing · Austin, TX', 'desc'=>'Data‑driven B2B SaaS marketing strategies.', 'jobs'=>6],
        ['name'=>'Valley Medical Center', 'industry'=>'Healthcare · Phoenix, AZ', 'desc'=>'Leading regional hospital network.', 'jobs'=>31],
    ];
}
function get_active_jobs_employer() {
    return [
        ['title'=>'Senior Frontend Developer', 'apps'=>45, 'deadline'=>'Dec 31, 2025', 'status'=>'Active'],
        ['title'=>'Backend Engineer', 'apps'=>38, 'deadline'=>'Jan 15, 2026', 'status'=>'Active'],
    ];
}