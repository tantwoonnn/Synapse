<?php
require_once __DIR__ . '/../includes/functions.php';
$tutor = require_role('staff');
$error = '';
$editing = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = db()->prepare('SELECT id, day_of_week, start_time, end_time FROM tutor_schedules WHERE id = ? AND tutor_id = ? AND is_available = 1');
        $stmt->bind_param('ii', $editId, $tutor['id']);
        $stmt->execute();
        $editing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = db()->prepare('DELETE FROM tutor_schedules WHERE id = ? AND tutor_id = ?');
            $stmt->bind_param('ii', $id, $tutor['id']);
            $stmt->execute();
            $stmt->close();
            log_activity((int) $tutor['id'], 'AVAILABILITY_REMOVED', 'Removed weekly availability #' . $id);
            flash('success', 'Availability removed.');
            redirect('staff/schedule.php');
        }
    } elseif ($action === 'save') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $day = filter_var($_POST['day_of_week'] ?? null, FILTER_VALIDATE_INT);
        $start = (string) ($_POST['start_time'] ?? '');
        $end = (string) ($_POST['end_time'] ?? '');
        if ($day === false || $day < 0 || $day > 6 || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) || $start >= $end) {
            $error = 'Choose a weekday and a valid start/end time.';
        } else {
            try {
                $startValue = $start . ':00';
                $endValue = $end . ':00';
                if ($id) {
                    $stmt = db()->prepare('UPDATE tutor_schedules SET day_of_week = ?, start_time = ?, end_time = ? WHERE id = ? AND tutor_id = ? AND is_available = 1');
                    $stmt->bind_param('issii', $day, $startValue, $endValue, $id, $tutor['id']);
                    $logAction = 'AVAILABILITY_UPDATED';
                } else {
                    $stmt = db()->prepare('INSERT INTO tutor_schedules (tutor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('iiss', $tutor['id'], $day, $startValue, $endValue);
                    $logAction = 'AVAILABILITY_ADDED';
                }
                $stmt->execute();
                $stmt->close();
                log_activity((int) $tutor['id'], $logAction, day_name((int) $day) . ' ' . $start . '-' . $end);
                flash('success', $id ? 'Weekly availability updated.' : 'Weekly availability added.');
                redirect('staff/schedule.php');
            } catch (mysqli_sql_exception $exception) {
                $error = 'That availability period is already listed.';
            }
        }
    }
}
$stmt = db()->prepare('SELECT id, day_of_week, start_time, end_time FROM tutor_schedules WHERE tutor_id = ? AND is_available = 1 ORDER BY day_of_week, start_time');
$stmt->bind_param('i', $tutor['id']);
$stmt->execute();
$slots = $stmt->get_result();
$pageTitle = 'My Availability';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Weekly availability</h1><p class="text-muted mb-0">Add the recurring times when learners can request sessions.</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card mb-4"><div class="card-body"><h2 class="h5"><?= $editing ? 'Edit time period' : 'Add time period' ?></h2><form method="post" class="row g-3 align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>"><div class="col-md-4"><label class="form-label">Day</label><select class="form-select" name="day_of_week"><?php for ($day = 0; $day <= 6; $day++): ?><option value="<?= $day ?>" <?= $editing && (int) $editing['day_of_week'] === $day ? 'selected' : '' ?>><?= e(day_name($day)) ?></option><?php endfor; ?></select></div><div class="col-md-3"><label class="form-label">Start time</label><input class="form-control" type="time" name="start_time" value="<?= e(isset($editing['start_time']) ? substr($editing['start_time'], 0, 5) : '') ?>" required></div><div class="col-md-3"><label class="form-label">End time</label><input class="form-control" type="time" name="end_time" value="<?= e(isset($editing['end_time']) ? substr($editing['end_time'], 0, 5) : '') ?>" required></div><div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><?= $editing ? 'Save changes' : 'Add' ?></button><?php if ($editing): ?><a class="btn btn-link w-100" href="<?= e(url('staff/schedule.php')) ?>">Cancel</a><?php endif; ?></div></form></div></div>
<div class="card"><div class="card-body"><h2 class="h5">Current schedule</h2><?php if ($slots->num_rows === 0): ?><p class="text-muted mb-0">No availability posted.</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Day</th><th>From</th><th>Until</th><th>Actions</th></tr></thead><tbody><?php while ($slot = $slots->fetch_assoc()): ?><tr><td><?= e(day_name((int) $slot['day_of_week'])) ?></td><td><?= e(format_time($slot['start_time'])) ?></td><td><?= e(format_time($slot['end_time'])) ?></td><td class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('staff/schedule.php?edit=' . (int) $slot['id'])) ?>">Edit</a><form method="post" data-confirm="Remove this weekly availability?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $slot['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
