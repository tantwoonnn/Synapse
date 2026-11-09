<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role('admin');
$error = '';
$editing = null;

if (isset($_GET['edit'])) {
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = db()->prepare("SELECT id, learner_id, tutor_id, course_id, booking_date, session_time, notes, status FROM appointments WHERE id = ? AND status IN ('PENDING','CONFIRMED')");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $editing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'delete' && $id) {
        $stmt = db()->prepare('DELETE FROM appointments WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows === 1;
        $stmt->close();
        if ($deleted) log_activity((int) $admin['id'], 'APPOINTMENT_DELETED', 'Deleted appointment #' . $id);
        flash($deleted ? 'success' : 'warning', $deleted ? 'Appointment deleted.' : 'Appointment was not found.');
        redirect('admin/appointments.php');
    }

    if ($action === 'save' && $id) {
        $learnerId = filter_var($_POST['learner_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tutorId = filter_var($_POST['tutor_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $dateText = (string) ($_POST['booking_date'] ?? '');
        $timeText = (string) ($_POST['session_time'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateText);
        $time = DateTimeImmutable::createFromFormat('!H:i', $timeText);
        if (!$learnerId || !$tutorId || !$courseId) $error = 'Choose a learner, tutor, and subject.';
        elseif (!$date || $date->format('Y-m-d') !== $dateText || $dateText < date('Y-m-d')) $error = 'Choose a valid date today or later.';
        elseif (!$time || $time->format('H:i') !== $timeText) $error = 'Choose a valid session start time.';
        elseif (strlen($notes) > 500) $error = 'Notes must be 500 characters or fewer.';
        elseif ($dateText === date('Y-m-d') && $timeText <= date('H:i')) $error = 'Choose a time later than now.';

        if ($error === '') {
            $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'user'");
            $check->bind_param('i', $learnerId); $check->execute(); $validLearner = $check->get_result()->fetch_assoc(); $check->close();
            $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'staff'");
            $check->bind_param('i', $tutorId); $check->execute(); $validTutor = $check->get_result()->fetch_assoc(); $check->close();
            $check = db()->prepare('SELECT id FROM courses WHERE id = ?');
            $check->bind_param('i', $courseId); $check->execute(); $validCourse = $check->get_result()->fetch_assoc(); $check->close();
            if (!$validLearner || !$validTutor || !$validCourse) $error = 'The selected learner, tutor, or subject is no longer available.';
        }

        if ($error === '') {
            $weekday = (int) $date->format('w');
            $start = $time->format('H:i:s');
            $end = $time->modify('+60 minutes')->format('H:i:s');
            $check = db()->prepare('SELECT start_time, end_time FROM tutor_schedules WHERE tutor_id = ? AND day_of_week = ? AND is_available = 1');
            $check->bind_param('ii', $tutorId, $weekday); $check->execute(); $scheduleRows = $check->get_result();
            $available = false;
            while ($slot = $scheduleRows->fetch_assoc()) if ($start >= $slot['start_time'] && $end <= $slot['end_time']) $available = true;
            $check->close();
            if (!$available) $error = 'The session time must fit the tutor’s weekly availability.';
        }

        if ($error === '') {
            $check = db()->prepare("SELECT session_time FROM appointments WHERE tutor_id = ? AND booking_date = ? AND status IN ('PENDING','CONFIRMED') AND id <> ?");
            $check->bind_param('isi', $tutorId, $dateText, $id); $check->execute(); $busyRows = $check->get_result();
            $startMinute = (int) $time->format('H') * 60 + (int) $time->format('i');
            while ($busy = $busyRows->fetch_assoc()) {
                $busyTime = strtotime('1970-01-01 ' . $busy['session_time']);
                $busyMinute = (int) date('G', $busyTime) * 60 + (int) date('i', $busyTime);
                if ($startMinute < $busyMinute + 60 && $busyMinute < $startMinute + 60) $error = 'That time overlaps another request or confirmed session.';
            }
            $check->close();
        }

        if ($error === '') {
            $notes = $notes === '' ? null : $notes;
            $stmt = db()->prepare("UPDATE appointments SET learner_id = ?, tutor_id = ?, course_id = ?, booking_date = ?, session_time = ?, notes = ? WHERE id = ? AND status IN ('PENDING','CONFIRMED')");
            $stmt->bind_param('iiisssi', $learnerId, $tutorId, $courseId, $dateText, $start, $notes, $id);
            $stmt->execute(); $updated = $stmt->affected_rows >= 0; $stmt->close();
            if ($updated) {
                log_activity((int) $admin['id'], 'APPOINTMENT_UPDATED', 'Updated appointment #' . $id);
                flash('success', 'Appointment updated.');
                redirect('admin/appointments.php');
            }
        }
    }
}

$learners = db()->query("SELECT id, full_name, email FROM users WHERE role = 'user' ORDER BY full_name");
$tutors = db()->query("SELECT id, full_name, email FROM users WHERE role = 'staff' ORDER BY full_name");
$courses = db()->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code');
$appointments = db()->query('SELECT a.id, a.booking_date, a.session_time, a.status, a.notes, l.full_name AS learner, t.full_name AS tutor, c.course_code, c.course_name FROM appointments a JOIN users l ON l.id=a.learner_id JOIN users t ON t.id=a.tutor_id JOIN courses c ON c.id=a.course_id ORDER BY a.booking_date DESC, a.session_time DESC');
$pageTitle = 'Manage Appointments';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Appointment management</h1><p class="text-muted mb-0">Review requests and correct active bookings. Status changes remain part of the tutor workflow.</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($editing): ?><div class="card mb-4"><div class="card-body"><h2 class="h5">Edit appointment #<?= (int) $editing['id'] ?> <span class="text-muted fw-normal">(<?= e($editing['status']) ?>)</span></h2><form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
<div class="col-md-4"><label class="form-label">Learner</label><select class="form-select" name="learner_id" required><?php while ($learner = $learners->fetch_assoc()): ?><option value="<?= (int) $learner['id'] ?>" <?= (int) $editing['learner_id'] === (int) $learner['id'] ? 'selected' : '' ?>><?= e($learner['full_name'] . ' · ' . $learner['email']) ?></option><?php endwhile; ?></select></div>
<div class="col-md-4"><label class="form-label">Tutor</label><select class="form-select" name="tutor_id" required><?php while ($tutor = $tutors->fetch_assoc()): ?><option value="<?= (int) $tutor['id'] ?>" <?= (int) $editing['tutor_id'] === (int) $tutor['id'] ? 'selected' : '' ?>><?= e($tutor['full_name'] . ' · ' . $tutor['email']) ?></option><?php endwhile; ?></select></div>
<div class="col-md-4"><label class="form-label">Subject</label><select class="form-select" name="course_id" required><?php while ($course = $courses->fetch_assoc()): ?><option value="<?= (int) $course['id'] ?>" <?= (int) $editing['course_id'] === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['course_code'] . ' · ' . $course['course_name']) ?></option><?php endwhile; ?></select></div>
<div class="col-md-6"><label class="form-label">Date</label><input class="form-control" type="date" name="booking_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($editing['booking_date']) ?>" required></div><div class="col-md-6"><label class="form-label">Start time</label><input class="form-control" type="time" name="session_time" value="<?= e(substr($editing['session_time'], 0, 5)) ?>" required></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" maxlength="500" rows="2"><?= e($editing['notes'] ?? '') ?></textarea></div><div class="col-12"><button class="btn btn-primary" type="submit">Save changes</button> <a class="btn btn-link" href="<?= e(url('admin/appointments.php')) ?>">Cancel</a></div></form></div></div><?php endif; ?>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date / time</th><th>Learner</th><th>Tutor</th><th>Subject</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if ($appointments->num_rows === 0): ?><tr><td colspan="6" class="text-muted">No appointments yet.</td></tr><?php endif; ?><?php while ($row = $appointments->fetch_assoc()): ?><tr><td><?= e(format_date($row['booking_date'])) ?><br><span class="small text-muted"><?= e(format_time($row['session_time'])) ?></span></td><td><?= e($row['learner']) ?></td><td><?= e($row['tutor']) ?></td><td><?= e($row['course_code'] . ' · ' . $row['course_name']) ?></td><td><?= status_badge($row['status']) ?></td><td class="d-flex gap-2"><?php if (in_array($row['status'], ['PENDING','CONFIRMED'], true)): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/appointments.php?edit=' . (int) $row['id'])) ?>">Edit</a><?php endif; ?><form method="post" data-confirm="Delete this appointment and its review?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></td></tr><?php endwhile; ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
