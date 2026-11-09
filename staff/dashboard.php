<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BookingQueue.php';
$tutor = require_role('staff');
$pendingStmt = db()->prepare("SELECT a.id, a.booking_date, a.session_time, a.notes, a.created_at, u.full_name AS learner_name, c.course_code, c.course_name FROM appointments a JOIN users u ON u.id = a.learner_id JOIN courses c ON c.id = a.course_id WHERE a.tutor_id = ? AND a.status = 'PENDING' ORDER BY a.created_at ASC");
$pendingStmt->bind_param('i', $tutor['id']);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$queue = new BookingQueue();
while ($request = $pendingResult->fetch_assoc()) $queue->enqueue($request);
$pending = $queue->toArray();
$confirmedStmt = db()->prepare("SELECT a.id, a.booking_date, a.session_time, a.notes, u.full_name AS learner_name, c.course_code, c.course_name FROM appointments a JOIN users u ON u.id = a.learner_id JOIN courses c ON c.id = a.course_id WHERE a.tutor_id = ? AND a.status = 'CONFIRMED' ORDER BY a.booking_date, a.session_time");
$confirmedStmt->bind_param('i', $tutor['id']);
$confirmedStmt->execute();
$confirmed = $confirmedStmt->get_result();
$pageTitle = 'Tutor Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><p class="text-uppercase text-primary fw-semibold small mb-1">Tutor workspace</p><h1 class="page-heading h2 mb-1">Session requests</h1><p class="text-muted mb-0">Requests are shown in the order they arrived.</p></div><form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit">Log out</button></form></div>
<section class="mb-5"><h2 class="h4 mb-3">Pending requests <span class="badge text-bg-warning"><?= count($pending) ?></span></h2>
<?php if (!$pending): ?><div class="alert alert-light border">No requests are waiting for review.</div><?php else: ?><div class="row g-3"><?php foreach ($pending as $row): ?><div class="col-lg-6"><article class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><div><div class="text-muted small"><?= e($row['course_code']) ?></div><h3 class="h5"><?= e($row['course_name']) ?></h3></div><?= status_badge('PENDING') ?></div><p class="mb-1"><strong>Learner:</strong> <?= e($row['learner_name']) ?></p><p class="mb-1"><strong>When:</strong> <?= e(format_date($row['booking_date'])) ?> · <?= e(format_time($row['session_time'])) ?></p><?php if ($row['notes']): ?><p class="small text-muted mt-2"><?= e($row['notes']) ?></p><?php endif; ?><div class="d-flex gap-2 mt-3"><form method="post" action="<?= e(url('staff/actions.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="appointment_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-primary" name="action" value="approve" type="submit">Approve</button></form><form method="post" action="<?= e(url('staff/actions.php')) ?>" data-confirm="Reject this session request?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="appointment_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="action" value="reject" type="submit">Reject</button></form></div></div></article></div><?php endforeach; ?></div><?php endif; ?></section>
<section><h2 class="h4 mb-3">Confirmed sessions</h2><?php if ($confirmed->num_rows === 0): ?><p class="text-muted">You have no confirmed sessions yet.</p><?php else: ?><div class="table-responsive"><table class="table bg-white align-middle"><thead><tr><th>Learner</th><th>Subject</th><th>When</th><th>Action</th></tr></thead><tbody><?php while ($row = $confirmed->fetch_assoc()): ?><tr><td><?= e($row['learner_name']) ?></td><td><?= e($row['course_code'] . ' · ' . $row['course_name']) ?></td><td><?= e(format_date($row['booking_date'])) ?> · <?= e(format_time($row['session_time'])) ?></td><td><form method="post" action="<?= e(url('staff/actions.php')) ?>" data-confirm="Mark this session completed?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="appointment_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-success" name="action" value="complete" type="submit">Mark completed</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
