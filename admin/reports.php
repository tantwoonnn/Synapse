<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$validDate = static fn(string $value): bool => $value === '' || (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
$error = '';
if (!$validDate($from) || !$validDate($to) || ($from !== '' && $to !== '' && $from > $to)) {
    $error = 'Choose a valid date range.';
    $from = $to = '';
}
$sql = 'SELECT a.booking_date, a.session_time, a.status, a.notes, l.full_name AS learner_name, t.full_name AS tutor_name, c.course_code, c.course_name, r.rating
        FROM appointments a JOIN users l ON l.id = a.learner_id JOIN users t ON t.id = a.tutor_id JOIN courses c ON c.id = a.course_id
        LEFT JOIN session_reviews r ON r.appointment_id = a.id WHERE 1 = 1';
if ($from !== '') $sql .= ' AND a.booking_date >= ?';
if ($to !== '') $sql .= ' AND a.booking_date <= ?';
$sql .= ' ORDER BY a.booking_date DESC, a.session_time DESC';
$stmt = db()->prepare($sql);
if ($from !== '' && $to !== '') $stmt->bind_param('ss', $from, $to);
elseif ($from !== '') $stmt->bind_param('s', $from);
elseif ($to !== '') $stmt->bind_param('s', $to);
$stmt->execute();
$rows = $stmt->get_result();
$pageTitle = 'Session Report';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><h1 class="page-heading h2">Session report</h1><p class="text-muted mb-0">Filter session history and print or save it as a PDF.</p></div><button class="btn btn-outline-primary no-print" type="button" onclick="window.print()">Print report</button></div>
<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
<form method="get" class="card card-body mb-4 no-print"><div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div><div class="col-md-4"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div><div class="col-md-4"><button class="btn btn-primary" type="submit">Apply filters</button> <a class="btn btn-outline-secondary" href="<?= e(url('admin/reports.php')) ?>">Clear</a></div></div></form>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Time</th><th>Learner</th><th>Tutor</th><th>Subject</th><th>Status</th><th>Rating</th></tr></thead><tbody>
<?php if ($rows->num_rows === 0): ?><tr><td colspan="7" class="text-muted">No sessions match this date range.</td></tr><?php endif; ?>
<?php while ($row = $rows->fetch_assoc()): ?><tr><td><?= e(format_date($row['booking_date'])) ?></td><td><?= e(format_time($row['session_time'])) ?></td><td><?= e($row['learner_name']) ?></td><td><?= e($row['tutor_name']) ?></td><td><?= e($row['course_code'] . ' · ' . $row['course_name']) ?></td><td><?= status_badge($row['status']) ?></td><td><?= $row['rating'] === null ? '—' : (int) $row['rating'] . ' / 5' ?></td></tr><?php endwhile; ?>
</tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
