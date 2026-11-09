<?php
require_once __DIR__ . '/../includes/functions.php';
$learner = require_role('user');
$error = '';
$stmt = db()->prepare('SELECT id, academic_program, subjects, experience, motivation, status, admin_note FROM tutor_applications WHERE learner_id = ?');
$stmt->bind_param('i', $learner['id']);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

$form = [
    'academic_program' => (string) ($_POST['academic_program'] ?? $application['academic_program'] ?? ''),
    'subjects' => (string) ($_POST['subjects'] ?? $application['subjects'] ?? ''),
    'experience' => (string) ($_POST['experience'] ?? $application['experience'] ?? ''),
    'motivation' => (string) ($_POST['motivation'] ?? $application['motivation'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($application && $application['status'] !== 'REJECTED') {
        $error = 'Your application is already under review or has been approved.';
    } elseif (mb_strlen(trim($form['academic_program'])) < 2 || mb_strlen($form['academic_program']) > 120) {
        $error = 'Enter your academic program (2 to 120 characters).';
    } elseif (mb_strlen(trim($form['subjects'])) < 2 || mb_strlen($form['subjects']) > 300) {
        $error = 'List the subjects you can tutor (2 to 300 characters).';
    } elseif (mb_strlen(trim($form['experience'])) < 20 || mb_strlen($form['experience']) > 2000) {
        $error = 'Describe your tutoring or subject experience in 20 to 2,000 characters.';
    } elseif (mb_strlen(trim($form['motivation'])) < 20 || mb_strlen($form['motivation']) > 2000) {
        $error = 'Explain why you want to tutor in 20 to 2,000 characters.';
    } else {
        $program = trim($form['academic_program']);
        $subjects = trim($form['subjects']);
        $experience = trim($form['experience']);
        $motivation = trim($form['motivation']);
        if ($application) {
            $stmt = db()->prepare("UPDATE tutor_applications SET academic_program = ?, subjects = ?, experience = ?, motivation = ?, status = 'PENDING', admin_note = NULL, applied_at = CURRENT_TIMESTAMP, reviewed_by = NULL, reviewed_at = NULL WHERE id = ? AND learner_id = ? AND status = 'REJECTED'");
            $stmt->bind_param('ssssii', $program, $subjects, $experience, $motivation, $application['id'], $learner['id']);
            $stmt->execute();
            $saved = $stmt->affected_rows === 1;
            $stmt->close();
        } else {
            $stmt = db()->prepare("INSERT INTO tutor_applications (learner_id, academic_program, subjects, experience, motivation, status) VALUES (?, ?, ?, ?, ?, 'PENDING')");
            $stmt->bind_param('issss', $learner['id'], $program, $subjects, $experience, $motivation);
            $stmt->execute();
            $saved = $stmt->affected_rows === 1;
            $stmt->close();
        }
        if ($saved) {
            log_activity((int) $learner['id'], 'TUTOR_APPLICATION_SUBMITTED', 'Submitted a tutor application');
            flash('success', 'Your tutor application was submitted for review.');
            redirect('user/dashboard.php');
        }
        $error = 'The application could not be saved. Refresh the page and try again.';
    }
}

$pageTitle = 'Apply as a Tutor';
require __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="mb-4"><h1 class="page-heading h2">Apply to become a tutor</h1><p class="text-muted mb-0">Tell us what you can teach. An administrator will review your application before your account is changed to tutor.</p></div>
<?php if ($application && $application['status'] === 'PENDING'): ?><div class="alert alert-secondary">Your application is waiting for administrator review.</div>
<?php elseif ($application && $application['status'] === 'APPROVED'): ?><div class="alert alert-success">Your application has been approved. Sign in again to open your tutor workspace.</div>
<?php else: ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card"><div class="card-body p-4"><?= csrf_field() ?><div class="mb-3"><label class="form-label" for="academic_program">Academic program</label><input class="form-control" id="academic_program" name="academic_program" maxlength="120" required value="<?= e($form['academic_program']) ?>"></div>
<div class="mb-3"><label class="form-label" for="subjects">Subjects you can tutor</label><input class="form-control" id="subjects" name="subjects" maxlength="300" required value="<?= e($form['subjects']) ?>" placeholder="For example: Algebra, Biology, Web Development"></div>
<div class="mb-3"><label class="form-label" for="experience">Relevant experience</label><textarea class="form-control" id="experience" name="experience" rows="4" minlength="20" maxlength="2000" required><?= e($form['experience']) ?></textarea><div class="form-text">Include relevant coursework, grades, projects, or previous tutoring.</div></div>
<div class="mb-4"><label class="form-label" for="motivation">Why would you like to tutor?</label><textarea class="form-control" id="motivation" name="motivation" rows="4" minlength="20" maxlength="2000" required><?= e($form['motivation']) ?></textarea></div>
<button class="btn btn-primary" type="submit">Submit application</button> <a class="btn btn-link" href="<?= e(url('user/dashboard.php')) ?>">Back to dashboard</a></div></form><?php endif; ?></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
