<?php
require_once __DIR__ . '/../includes/functions.php';
$learner = require_role('user');
$error = '';
$form = [
    'tutor_id' => (string) ($_GET['tutor_id'] ?? $_POST['tutor_id'] ?? ''),
    'course_id' => (string) ($_POST['course_id'] ?? ''),
    'booking_date' => (string) ($_POST['booking_date'] ?? ''),
    'session_time' => (string) ($_POST['session_time'] ?? ''),
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];
$tutors = db()->query("SELECT id, full_name FROM users WHERE role = 'staff' ORDER BY full_name");
$courses = db()->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $tutorId = filter_var($form['tutor_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $courseId = filter_var($form['course_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $form['booking_date']);
    $time = DateTimeImmutable::createFromFormat('!H:i', $form['session_time']);
    if (!$tutorId || !$courseId) $error = 'Choose a tutor and subject.';
    elseif (!$date || $date->format('Y-m-d') !== $form['booking_date'] || $date->format('Y-m-d') < date('Y-m-d')) $error = 'Choose a valid date today or later.';
    elseif (!$time || $time->format('H:i') !== $form['session_time']) $error = 'Choose a valid start time.';
    elseif (strlen($form['notes']) > 500) $error = 'Notes must be 500 characters or fewer.';
    elseif ($date->format('Y-m-d') === date('Y-m-d') && $time->format('H:i') <= date('H:i')) $error = 'Choose a start time later than now.';

    if ($error === '') {
        $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'staff'");
        $stmt->bind_param('i', $tutorId);
        $stmt->execute();
        $validTutor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stmt = db()->prepare('SELECT id FROM courses WHERE id = ?');
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $validCourse = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$validTutor || !$validCourse) $error = 'That tutor or subject is no longer available.';
    }

    if ($error === '') {
        $startTime = $time->format('H:i:s');
        $endTime = $time->modify('+60 minutes')->format('H:i:s');
        $weekday = (int) $date->format('w');
        $stmt = db()->prepare('SELECT start_time, end_time FROM tutor_schedules WHERE tutor_id = ? AND day_of_week = ? AND is_available = 1');
        $stmt->bind_param('ii', $tutorId, $weekday);
        $stmt->execute();
        $scheduleRows = $stmt->get_result();
        $available = false;
        while ($slot = $scheduleRows->fetch_assoc()) {
            if ($startTime >= $slot['start_time'] && $endTime <= $slot['end_time']) $available = true;
        }
        $stmt->close();
        if (!$available) $error = 'The selected time does not fit the tutor’s weekly availability.';
    }

    if ($error === '') {
        $stmt = db()->prepare("SELECT session_time FROM appointments WHERE tutor_id = ? AND booking_date = ? AND status IN ('PENDING', 'CONFIRMED')");
        $dateValue = $date->format('Y-m-d');
        $stmt->bind_param('is', $tutorId, $dateValue);
        $stmt->execute();
        $busyRows = $stmt->get_result();
        $startMinute = (int) $time->format('H') * 60 + (int) $time->format('i');
        while ($busy = $busyRows->fetch_assoc()) {
            $busyTime = strtotime('1970-01-01 ' . $busy['session_time']);
            $busyMinute = (int) date('G', $busyTime) * 60 + (int) date('i', $busyTime);
            if ($startMinute < $busyMinute + 60 && $busyMinute < $startMinute + 60) $error = 'That time overlaps another request or confirmed session.';
        }
        $stmt->close();
    }

    if ($error === '') {
        try {
            db()->begin_transaction();
            $notes = $form['notes'] === '' ? null : $form['notes'];
            $stmt = db()->prepare("INSERT INTO appointments (learner_id, tutor_id, course_id, booking_date, session_time, status, notes) VALUES (?, ?, ?, ?, ?, 'PENDING', ?)");
            $stmt->bind_param('iiisss', $learner['id'], $tutorId, $courseId, $dateValue, $startTime, $notes);
            $stmt->execute();
            $appointmentId = (int) db()->insert_id;
            $stmt->close();
            log_activity((int) $learner['id'], 'BOOKING_REQUESTED', 'Requested appointment #' . $appointmentId);
            db()->commit();
            flash('success', 'Your request was sent to the tutor.');
            redirect('user/dashboard.php');
        } catch (Throwable $exception) {
            db()->rollback();
            error_log('Booking request failed: ' . $exception->getMessage());
            $error = 'Could not save your request. Please try again.';
        }
    }
}
$pageTitle = 'Request a Session';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Request a tutoring session</h1><p class="text-muted mb-0">Sessions last one hour. The tutor will approve or decline your request.</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($tutors->num_rows === 0 || $courses->num_rows === 0): ?><div class="alert alert-warning">Add at least one tutor and one subject before requesting a session.</div><?php else: ?><form method="post" class="card"><div class="card-body p-4"><?= csrf_field() ?><div class="row g-3">
    <div class="col-md-6"><label class="form-label">Tutor</label><select class="form-select" name="tutor_id" required><option value="">Choose tutor</option><?php while ($tutor = $tutors->fetch_assoc()): ?><option value="<?= (int) $tutor['id'] ?>" <?= $form['tutor_id'] === (string) $tutor['id'] ? 'selected' : '' ?>><?= e($tutor['full_name']) ?></option><?php endwhile; ?></select></div>
    <div class="col-md-6"><label class="form-label">Subject</label><select class="form-select" name="course_id" required><option value="">Choose subject</option><?php while ($course = $courses->fetch_assoc()): ?><option value="<?= (int) $course['id'] ?>" <?= $form['course_id'] === (string) $course['id'] ? 'selected' : '' ?>><?= e($course['course_code'] . ' · ' . $course['course_name']) ?></option><?php endwhile; ?></select></div>
    <div class="col-md-6"><label class="form-label">Date</label><input class="form-control" type="date" name="booking_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($form['booking_date']) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Start time</label><input class="form-control" type="time" name="session_time" value="<?= e($form['session_time']) ?>" required></div>
    <div class="col-12"><label class="form-label">What would you like help with?</label><textarea class="form-control" name="notes" maxlength="500" rows="3"><?= e($form['notes']) ?></textarea></div>
</div><button class="btn btn-primary mt-4" type="submit">Send request</button></div></form><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
