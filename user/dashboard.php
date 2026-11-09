<?php
require_once __DIR__ . '/../includes/functions.php';
$learner = require_role('user');
$stmt = db()->prepare('SELECT a.id, a.booking_date, a.session_time, a.status, a.notes, t.full_name AS tutor_name, c.course_code, c.course_name, r.id AS review_id FROM appointments a JOIN users t ON t.id = a.tutor_id JOIN courses c ON c.id = a.course_id LEFT JOIN session_reviews r ON r.appointment_id = a.id WHERE a.learner_id = ? ORDER BY a.booking_date DESC, a.session_time DESC, a.created_at DESC');
$stmt->bind_param('i', $learner['id']);
$stmt->execute();
$sessions = $stmt->get_result();
$total = $sessions->num_rows;
$applicationStmt = db()->prepare('SELECT status, admin_note FROM tutor_applications WHERE learner_id = ?');
$applicationStmt->bind_param('i', $learner['id']);
$applicationStmt->execute();
$tutorApplication = $applicationStmt->get_result()->fetch_assoc();
$applicationStmt->close();
$pageTitle = 'My Sessions';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><p class="text-uppercase text-primary fw-semibold small mb-1">Learner space</p><h1 class="page-heading h2 mb-1">My sessions</h1><p class="text-muted mb-0">Welcome, <?= e($learner['full_name']) ?>. You have <?= $total ?> session request(s).</p></div><div class="d-flex gap-2"><a class="btn btn-primary" href="<?= e(url('user/tutors.php')) ?>">Find a tutor</a><form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit">Log out</button></form></div></div>
<div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3"><div><h2 class="h5 mb-1">Interested in tutoring?</h2><?php if (!$tutorApplication): ?><p class="text-muted mb-0">Apply to share your knowledge with other learners. An administrator will review your application.</p><?php else: ?><p class="text-muted mb-0">Application status: <?= e(ucfirst(strtolower($tutorApplication['status']))) ?>.<?php if ($tutorApplication['status'] === 'REJECTED' && $tutorApplication['admin_note']): ?> Review note: <?= e($tutorApplication['admin_note']) ?><?php endif; ?></p><?php endif; ?></div><?php if (!$tutorApplication || $tutorApplication['status'] === 'REJECTED'): ?><a class="btn btn-outline-primary" href="<?= e(url('user/tutor_application.php')) ?>"><?= $tutorApplication ? 'Update application' : 'Apply as tutor' ?></a><?php endif; ?></div></div>
<?php if ($total === 0): ?><div class="card"><div class="card-body text-center py-5"><h2 class="h5">No sessions yet</h2><p class="text-muted">Browse tutors and send your first request.</p><a class="btn btn-primary" href="<?= e(url('user/tutors.php')) ?>">Browse tutors</a></div></div><?php else: ?>
<div class="row g-3"><?php while ($row = $sessions->fetch_assoc()): ?><div class="col-lg-6"><article class="card h-100"><div class="card-body"><div class="d-flex justify-content-between gap-3"><div><div class="text-muted small"><?= e($row['course_code']) ?></div><h2 class="h5 mb-1"><?= e($row['course_name']) ?></h2></div><?= status_badge($row['status']) ?></div><p class="mt-3 mb-1"><strong>Tutor:</strong> <?= e($row['tutor_name']) ?></p><p class="mb-1"><strong>When:</strong> <?= e(format_date($row['booking_date'])) ?> · <?= e(format_time($row['session_time'])) ?></p><?php if ($row['notes']): ?><p class="text-muted small mt-2 mb-0"><?= e($row['notes']) ?></p><?php endif; ?><?php if ($row['status'] === 'COMPLETED' && !$row['review_id']): ?><a class="btn btn-sm btn-outline-primary mt-3" href="<?= e(url('user/review.php?appointment_id=' . (int) $row['id'])) ?>">Rate this session</a><?php elseif ($row['review_id']): ?><span class="small text-success d-block mt-3">Review submitted</span><?php endif; ?></div></article></div><?php endwhile; ?></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
