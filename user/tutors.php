<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('user');
$search = trim((string) ($_GET['q'] ?? ''));
if ($search === '') {
    $stmt = db()->prepare("SELECT u.id, u.full_name, u.email, (SELECT COUNT(*) FROM appointments a WHERE a.tutor_id = u.id AND a.status = 'COMPLETED') AS completed_count, (SELECT AVG(r.rating) FROM session_reviews r JOIN appointments a ON a.id = r.appointment_id WHERE a.tutor_id = u.id) AS average_rating FROM users u WHERE u.role = 'staff' ORDER BY u.full_name");
    $stmt->execute();
} else {
    $like = '%' . $search . '%';
    $stmt = db()->prepare("SELECT u.id, u.full_name, u.email, (SELECT COUNT(*) FROM appointments a WHERE a.tutor_id = u.id AND a.status = 'COMPLETED') AS completed_count, (SELECT AVG(r.rating) FROM session_reviews r JOIN appointments a ON a.id = r.appointment_id WHERE a.tutor_id = u.id) AS average_rating FROM users u WHERE u.role = 'staff' AND (u.full_name LIKE ? OR u.email LIKE ?) ORDER BY u.full_name");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
}
$tutors = $stmt->get_result();
$pageTitle = 'Find a Tutor';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Find a tutor</h1><p class="text-muted mb-0">Browse tutors and view their posted weekly availability.</p></div>
<form method="get" class="card card-body mb-4"><label class="form-label" for="q">Search by tutor name or email</label><div class="input-group"><input id="q" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search tutors"><button class="btn btn-outline-primary" type="submit">Search</button></div></form>
<?php if ($tutors->num_rows === 0): ?><div class="alert alert-info">No tutors matched your search.</div><?php else: ?><div class="row g-3"><?php while ($tutor = $tutors->fetch_assoc()): ?><div class="col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5 mb-1"><?= e($tutor['full_name']) ?></h2><div class="text-muted small"><?= e($tutor['email']) ?></div><p class="small mt-3 mb-2"><?= (int) $tutor['completed_count'] ?> completed session(s) · <?= $tutor['average_rating'] === null ? 'No ratings yet' : e(number_format((float) $tutor['average_rating'], 1) . ' / 5 average') ?></p><h3 class="h6">Availability</h3><?php $availability = db()->prepare('SELECT day_of_week, start_time, end_time FROM tutor_schedules WHERE tutor_id = ? AND is_available = 1 ORDER BY day_of_week, start_time'); $availability->bind_param('i', $tutor['id']); $availability->execute(); $slots = $availability->get_result(); ?><?php if ($slots->num_rows === 0): ?><p class="small text-muted">No times posted yet.</p><?php else: ?><ul class="small list-unstyled"><?php while ($slot = $slots->fetch_assoc()): ?><li><?= e(day_name((int) $slot['day_of_week'])) ?> · <?= e(format_time($slot['start_time'])) ?>–<?= e(format_time($slot['end_time'])) ?></li><?php endwhile; ?></ul><?php endif; ?><a class="btn btn-sm btn-primary" href="<?= e(url('user/book_appointment.php?tutor_id=' . (int) $tutor['id'])) ?>">Request a session</a></div></div></div><?php endwhile; ?></div><?php endif; ?>
<p class="small text-muted mt-4">The database does not yet link tutors to specific subjects, so use the subject selector when requesting a session.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
