<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// Handle save/unsave
if (isset($_GET['save'])) {
    $db->prepare("INSERT IGNORE INTO saved_jobs (applicant_id, job_id) VALUES (?, ?)")->execute([$user_id, $id]);
    header('Location: ?role=applicant&page=job-details&id=' . $id);
    exit;
}
if (isset($_GET['unsave'])) {
    $db->prepare("DELETE FROM saved_jobs WHERE applicant_id = ? AND job_id = ?")->execute([$user_id, $id]);
    header('Location: ?role=applicant&page=job-details&id=' . $id);
    exit;
}

// Handle apply
if (isset($_GET['apply'])) {
    // A resume is mandatory. Checked before the generic profile gate because the
    // fix for this one lives on the Resume page, not on the profile form.
    $resume_row = $db->prepare("SELECT resume FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
    if (resume_resolve_path($resume_row['resume'] ?? '') === '') {
        $_SESSION['error'] = 'Upload your resume before you apply for a job.';
        header('Location: ?role=applicant&page=resume');
        exit;
    }

    // Require a complete profile before applying
    $missing = applicant_profile_missing();
    if (count($missing) > 0) {
        $list = implode(', ', array_diff($missing, ['Resume upload']));
        $_SESSION['error'] = "Complete your profile to apply: $list";
        header('Location: ?role=applicant&page=edit-profile');
        exit;
    }

    $already = $db->prepare("SELECT id FROM applications WHERE applicant_id = ? AND job_id = ?")->execute([$user_id, $id])->fetch();
    if (!$already) {
        // The gate above guarantees the resume exists, so the path is never null.
        $resume_path = 'uploads/resumes/' . $resume_row['resume'];
        $db->prepare("INSERT INTO applications (job_id, applicant_id, status, resume_path) VALUES (?, ?, 'pending', ?)")->execute([$id, $user_id, $resume_path]);
    }
    header('Location: ?role=applicant&page=job-details&id=' . $id . '&applied=1');
    exit;
}

// Get job details
$stmt = $db->prepare("
    SELECT j.*, u.name as employer_name, u.id as employer_id, c.name as category_name
    FROM jobs j
    LEFT JOIN users u ON j.employer_id = u.id
    LEFT JOIN categories c ON j.category_id = c.id
    WHERE j.id = ?
");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: ?role=applicant&page=find-jobs');
    exit;
}

// Get similar jobs
$similar = $db->prepare("
    SELECT j.*, u.name as employer_name
    FROM jobs j
    LEFT JOIN users u ON j.employer_id = u.id
    WHERE j.category_id = ? AND j.id != ? AND j.status = 'active'
    LIMIT 3
")->execute([$job['category_id'], $id])->fetchAll();

$similar_html = '';
foreach ($similar as $sj) {
    $similar_html .= "
    <div class='job-card'>
        <div class='title'>" . e($sj['title']) . "</div>
        <div class='company'>" . e($sj['employer_name']) . "</div>
        <div class='meta'><span>📍 " . e($sj['location']) . "</span></div>
        <a href='?role=applicant&page=job-details&id=" . (int)$sj['id'] . "' class='btn btn-primary btn-sm'>View</a>
    </div>";
}

$tags = '';
if ($job['skills']) {
    foreach (explode(',', $job['skills']) as $skill) {
        $tags .= "<span>" . htmlspecialchars(trim($skill)) . "</span>";
    }
}

$responsibilities = '';
if ($job['responsibilities']) {
    foreach (explode("\n", $job['responsibilities']) as $line) {
        if (trim($line)) $responsibilities .= "<li>" . htmlspecialchars(trim($line)) . "</li>";
    }
}

$benefits_html = '';
if ($job['benefits']) {
    foreach (explode("\n", $job['benefits']) as $line) {
        if (trim($line)) $benefits_html .= "<li>" . htmlspecialchars(trim($line)) . "</li>";
    }
}

// Check saved/applied
$is_saved = $db->prepare("SELECT id FROM saved_jobs WHERE applicant_id = ? AND job_id = ?")->execute([$user_id, $id])->fetch();
$has_applied = $db->prepare("SELECT id FROM applications WHERE applicant_id = ? AND job_id = ?")->execute([$user_id, $id])->fetch();
$application = $has_applied ? $db->prepare("SELECT * FROM applications WHERE applicant_id = ? AND job_id = ?")->execute([$user_id, $id])->fetch() : null;

// Debounce double-submission message
$applied_flag = isset($_GET['applied']);

$user = get_user_data();

$applied = $has_applied ? 'true' : 'false';
$apply_url = "?role=applicant&page=job-details&id={$job['id']}&apply=1";
$missing = applicant_profile_missing();
$resume_missing = in_array('Resume upload', $missing, true);
$other_missing = array_values(array_diff($missing, ['Resume upload']));
$profile_note = '';
if (!$has_applied && count($missing) > 0) {
    if (count($other_missing) === 0) {
        // The profile form cannot fix this one, so point at the Resume page.
        $apply_button = "<a href='?role=applicant&page=resume' class='btn btn-primary btn-sm' style='font-size:14px;padding:10px 20px;'>Upload Resume to Apply</a>";
        $apply_msg = '';
        $profile_note = "<div style='background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-top:16px;color:#92400e;font-size:14px;'>
            <strong>⚠️ Resume required.</strong> Employers receive your resume with every application, so upload one before you apply. &nbsp;<a href='?role=applicant&page=resume' style='color:#d97706;font-weight:600;'>Upload it now →</a>
        </div>";
    } else {
        $apply_button = "<a href='?role=applicant&page=edit-profile' class='btn btn-primary btn-sm' style='font-size:14px;padding:10px 20px;'>Complete Profile to Apply</a>";
        $apply_msg = '';
        $parts = $other_missing;
        if ($resume_missing) {
            $parts[] = 'Resume upload';
        }
        $missing_list = e(implode(', ', $parts));
        $profile_note = "<div style='background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-top:16px;color:#92400e;font-size:14px;'>
            <strong>⚠️ Profile incomplete.</strong> Complete your profile before applying. Missing: {$missing_list}. &nbsp;<a href='?role=applicant&page=edit-profile' style='color:#d97706;font-weight:600;'>Complete it now →</a>
        </div>";
    }
} elseif (!$has_applied) {
    $apply_button = "<a href='" . $apply_url . "' class='btn btn-primary btn-sm' style='font-size:14px;padding:10px 20px;'>Apply Now</a>";
    $apply_msg = $applied_flag ? 'Your application was submitted successfully!' : '';
    $profile_note = '';
} else {
    $apply_button = "<span class='badge badge-active' style='font-size:14px;padding:6px 16px;'>✅ Applied · " . htmlspecialchars(ucfirst($application['status'])) . "</span>";
    $apply_msg = $applied_flag ? 'Your application was submitted successfully!' : '';
    $profile_note = '';
}

$save_url = $is_saved
    ? "?role=applicant&page=job-details&id=" . (int)$job['id'] . "&unsave=1"
    : "?role=applicant&page=job-details&id=" . (int)$job['id'] . "&save=1";
$save_label = $is_saved ? '🔖 Saved' : '🔖 Save Job';

render('applicant/job-details.html', [
    'name' => $user['name'] ?? $_SESSION['name'] ?? 'Applicant',
    'job_title' => $job['title'],
    'company' => $job['employer_name'],
    'location' => $job['location'],
    'salary' => ($job['salary_min'] ? number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']) : 'Negotiable'),
    'job_type' => $job['type'],
    'experience' => $job['experience'] ?: 'Not specified',
    'posted' => date('M d, Y', strtotime($job['created_at'])),
    'deadline' => date('M d, Y', strtotime($job['deadline'])),
      'about_text' => new RawHtml(nl2br(e($job['description']))),
      'responsibilities' => new RawHtml($responsibilities),
      'tags' => new RawHtml($tags),
      'benefits' => new RawHtml($benefits_html),
      'similar_jobs' => new RawHtml($similar_html),
    'job_id' => $job['id'],
      'save_url' => new RawHtml($save_url),
      'save_label' => e($save_label),
      'apply_button' => new RawHtml($apply_button),
      'apply_msg' => e($apply_msg),
      'profile_note' => new RawHtml($profile_note),
    'applied' => $applied,
    'app_status' => $application ? ucfirst($application['status']) : ''
]);
