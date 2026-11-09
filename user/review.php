<?php
require_once __DIR__ . '/../includes/functions.php';
$learner = require_role('user');
$id = filter_var($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) redirect('user/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
    $comment = trim((string) ($_POST['comment'] ?? ''));
    if (!$rating || $rating < 1 || $rating > 5 || strlen($comment) > 500) $error = 'Choose a rating from 1 to 5 and keep feedback under 500 characters.';
    if ($error === '') {
        $check = db()->prepare("SELECT a.id FROM appointments a LEFT JOIN session_reviews r ON r.appointment_id = a.id WHERE a.id = ? AND a.learner_id = ? AND a.status = 'COMPLETED' AND r.id IS NULL");
        $check->bind_param('ii', $id, $learner['id']);
        $check->execute();
        $allowed = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$allowed) $error = 'This completed session cannot be reviewed.';
        else {
            $stmt = db()->prepare('INSERT INTO session_reviews (appointment_id, rating, comment) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $id, $rating, $comment);
            try {
                $stmt->execute();
                $stmt->close();
                log_activity((int) $learner['id'], 'SESSION_REVIEWED', 'Reviewed appointment #' . $id);
                flash('success', 'Thank you for sharing your feedback.');
                redirect('user/dashboard.php');
            } catch (mysqli_sql_exception $exception) {
                $error = 'A review may already have been submitted for this session.';
            }
        }
    }
}
$pageTitle = 'Rate a Session';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Rate your session</h1><p class="text-muted mb-0">Your feedback helps learners choose tutors.</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card"><div class="card-body p-4"><?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $id ?>"><div class="mb-3"><label class="form-label" for="rating">Rating</label><select class="form-select" id="rating" name="rating" required><option value="">Choose stars</option><?php for ($star = 5; $star >= 1; $star--): ?><option value="<?= $star ?>"><?= $star ?> / 5</option><?php endfor; ?></select></div><div class="mb-3"><label class="form-label" for="comment">Feedback <span class="text-muted">(optional)</span></label><textarea class="form-control" id="comment" name="comment" maxlength="500" rows="4"></textarea></div><button class="btn btn-primary" type="submit">Submit review</button> <a class="btn btn-outline-secondary" href="<?= e(url('user/dashboard.php')) ?>">Cancel</a></div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
