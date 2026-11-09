<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role('admin');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['admin_note'] ?? ''));
    if (!$applicationId || !in_array($decision, ['approve', 'reject'], true)) {
        $error = 'Choose a valid application action.';
    } elseif (mb_strlen($note) > 500) {
        $error = 'The review note must be 500 characters or fewer.';
    } else {
        db()->begin_transaction();
        try {
            $stmt = db()->prepare('SELECT learner_id, status FROM tutor_applications WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $applicationId);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$application || $application['status'] !== 'PENDING') throw new RuntimeException('This application is no longer pending.');

            $newStatus = $decision === 'approve' ? 'APPROVED' : 'REJECTED';
            if ($decision === 'approve') {
                $roleUpdate = db()->prepare("UPDATE users SET role = 'staff' WHERE id = ? AND role = 'user' AND is_verified = 1");
                $roleUpdate->bind_param('i', $application['learner_id']);
                $roleUpdate->execute();
                $promoted = $roleUpdate->affected_rows === 1;
                $roleUpdate->close();
                if (!$promoted) throw new RuntimeException('The applicant must still be a verified learner to receive tutor access.');
            }
            $noteValue = $note === '' ? null : $note;
            $adminId = (int) $admin['id'];
            $update = db()->prepare('UPDATE tutor_applications SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = \'PENDING\'');
            $update->bind_param('ssii', $newStatus, $noteValue, $adminId, $applicationId);
            $update->execute();
            $update->close();
            log_activity((int) $admin['id'], 'TUTOR_APPLICATION_' . $newStatus, 'Reviewed application #' . $applicationId);
            db()->commit();
            flash('success', $decision === 'approve' ? 'Application approved. The learner now has tutor access.' : 'Application rejected. The learner may revise and resubmit it.');
            redirect('admin/tutor_applications.php');
        } catch (RuntimeException $exception) {
            db()->rollback();
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            db()->rollback();
            error_log('Tutor application review failed: ' . $exception->getMessage());
            $error = 'The application could not be reviewed. Please try again.';
        }
    }
}

$applications = db()->query('SELECT a.id, a.learner_id, a.academic_program, a.subjects, a.experience, a.motivation, a.status, a.admin_note, a.applied_at, u.full_name, u.email FROM tutor_applications a JOIN users u ON u.id = a.learner_id ORDER BY FIELD(a.status, \'PENDING\', \'REJECTED\', \'APPROVED\'), a.applied_at DESC');
$pageTitle = 'Tutor Applications';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Tutor applications</h1><p class="text-muted mb-0">Review learner experience and approve eligible applicants for the tutor workspace.</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($applications->num_rows === 0): ?><div class="card"><div class="card-body text-muted">There are no tutor applications yet.</div></div><?php else: ?><div class="row g-3"><?php while ($application = $applications->fetch_assoc()): ?><div class="col-12"><article class="card"><div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-3"><div><h2 class="h5 mb-1"><?= e($application['full_name']) ?></h2><p class="text-muted small mb-2"><?= e($application['email']) ?> · <?= e($application['academic_program']) ?></p></div><div><?= status_badge($application['status']) ?></div></div><p class="mb-2"><strong>Subjects:</strong> <?= e($application['subjects']) ?></p><p class="mb-2"><strong>Experience:</strong><br><?= nl2br(e($application['experience'])) ?></p><p class="mb-3"><strong>Motivation:</strong><br><?= nl2br(e($application['motivation'])) ?></p><?php if ($application['admin_note']): ?><p class="small text-muted"><strong>Review note:</strong> <?= e($application['admin_note']) ?></p><?php endif; ?>
<?php if ($application['status'] === 'PENDING'): ?><form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><div class="col-md-8"><label class="form-label" for="note-<?= (int) $application['id'] ?>">Review note (optional)</label><input class="form-control" id="note-<?= (int) $application['id'] ?>" name="admin_note" maxlength="500"></div><div class="col-md-4 d-flex gap-2"><button class="btn btn-success" type="submit" name="decision" value="approve">Approve</button><button class="btn btn-outline-danger" type="submit" name="decision" value="reject">Reject</button></div></form><?php endif; ?></div></article></div><?php endwhile; ?></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
