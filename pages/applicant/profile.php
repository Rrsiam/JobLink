<?php
require_once __DIR__ . '/../../config.php';
require_role('applicant');

$user_id = get_user_id();
$db = getDB();

$user = get_user_data();
$profile = $db->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();

render('applicant/profile.html', [
    'name' => $user['name'],
    'initial' => substr($user['name'], 0, 1),
    'email' => $user['email'],
    'location' => $profile['address'] ?: 'Not set',
    'phone' => $user['phone'] ?: 'Not set',
    'about' => $profile['career_objective'] ?: 'No career objective set.',
    'education' => $profile['education'] ?: 'Not specified',
    'experience' => $profile['experience'] ?: 'Not specified',
    'skills' => $profile['skills'] ?: 'No skills listed',
    'certifications' => $profile['certifications'] ?: 'No certifications'
]);