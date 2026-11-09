<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$logs = db()->query('SELECT l.action, l.details, l.log_timestamp, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.log_timestamp DESC, l.id DESC LIMIT 200');
$pageTitle = 'Activity Logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Activity log</h1><p class="text-muted mb-0">The latest 200 recorded account and booking actions.</p></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>When</th><th>Account</th><th>Action</th><th>Details</th></tr></thead><tbody>
<?php if ($logs->num_rows === 0): ?><tr><td colspan="4" class="text-muted">No activity recorded.</td></tr><?php endif; ?>
<?php while ($row = $logs->fetch_assoc()): ?><tr><td class="text-nowrap"><?= e(date('M j, Y g:i A', strtotime($row['log_timestamp']))) ?></td><td><?= e($row['full_name'] ?? 'Removed account') ?></td><td><?= e($row['action']) ?></td><td><?= e($row['details'] ?? '') ?></td></tr><?php endwhile; ?>
</tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
