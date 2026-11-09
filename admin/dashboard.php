<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role('admin');

$counts = ['user' => 0, 'staff' => 0, 'admin' => 0];
$result = db()->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
while ($row = $result->fetch_assoc()) $counts[$row['role']] = (int) $row['total'];

$appointments = ['PENDING' => 0, 'CONFIRMED' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0];
$result = db()->query('SELECT status, COUNT(*) AS total FROM appointments GROUP BY status');
while ($row = $result->fetch_assoc()) $appointments[$row['status']] = (int) $row['total'];

$accountCount = (int) db()->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
$verifiedCount = (int) db()->query('SELECT COUNT(*) FROM users WHERE is_verified = 1')->fetch_row()[0];
$courseCount = (int) db()->query('SELECT COUNT(*) FROM courses')->fetch_row()[0];
$upcomingCount = (int) db()->query("SELECT COUNT(*) FROM appointments WHERE status = 'CONFIRMED' AND booking_date >= CURRENT_DATE")->fetch_row()[0];
$recent = db()->query('SELECT l.action, l.details, l.log_timestamp, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.log_timestamp DESC, l.id DESC LIMIT 8');

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-dashboard">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <p class="dashboard-eyebrow mb-1">ADMINISTRATION</p>
            <h1 class="dashboard-title mb-1">System overview</h1>
            <p class="text-muted mb-0">Welcome, <?= e($admin['full_name']) ?>. Here is the current Synapse activity.</p>
        </div>
        <form method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary dashboard-logout" type="submit"><i class="bi bi-box-arrow-right me-1"></i> Log out</button>
        </form>
    </div>

    <section class="dashboard-section" aria-labelledby="accounts-heading">
        <h2 class="dashboard-section-title" id="accounts-heading">Accounts</h2>
        <div class="row g-3">
            <?php foreach ([['Learners', $counts['user']], ['Tutors / staff', $counts['staff']], ['Verified accounts', $verifiedCount], ['Subjects in catalog', $courseCount]] as [$label, $number]): ?>
                <div class="col-sm-6 col-xl-3"><article class="card overview-card h-100"><div class="card-body"><div class="overview-label"><?= e($label) ?></div><div class="overview-number"><?= (int) $number ?></div></div></article></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="appointments-heading">
        <h2 class="dashboard-section-title" id="appointments-heading">Appointments</h2>
        <div class="row g-3">
            <?php foreach ([['Waiting for review', $appointments['PENDING']], ['Confirmed / upcoming', $upcomingCount], ['Completed', $appointments['COMPLETED']], ['Cancelled', $appointments['CANCELLED']]] as [$label, $number]): ?>
                <div class="col-sm-6 col-xl-3"><article class="card overview-card h-100"><div class="card-body"><div class="overview-label"><?= e($label) ?></div><div class="overview-number"><?= (int) $number ?></div></div></article></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="activity-heading">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="dashboard-section-title mb-0" id="activity-heading">Recent activity</h2><span class="small text-muted">Latest 8 events</span></div>
        <div class="card overview-card"><div class="card-body p-0">
            <?php if ($recent->num_rows === 0): ?><p class="text-muted p-3 mb-0">No activity has been recorded yet.</p>
            <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>When</th><th>Account</th><th>Action</th><th>Details</th></tr></thead><tbody>
                <?php while ($log = $recent->fetch_assoc()): ?><tr><td class="text-nowrap"><?= e(date('M j, Y g:i A', strtotime($log['log_timestamp']))) ?></td><td><?= e($log['full_name'] ?? 'Removed account') ?></td><td><?= e($log['action']) ?></td><td><?= e($log['details'] ?? '') ?></td></tr><?php endwhile; ?>
            </tbody></table></div><?php endif; ?>
        </div></div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
