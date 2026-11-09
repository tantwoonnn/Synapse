<?php
require_once __DIR__ . '/includes/functions.php';
$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$verified = false;
if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $stmt = db()->prepare('SELECT id, email FROM users WHERE verification_token = ? AND is_verified = 0 AND token_expires_at > NOW() LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->bind_result($userId, $email);
    if ($stmt->fetch()) {
        $stmt->close();
        $update = db()->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = ? AND verification_token = ? AND is_verified = 0 AND token_expires_at > NOW()');
        $update->bind_param('is', $userId, $token);
        $update->execute();
        $verified = $update->affected_rows === 1;
        $update->close();
        if ($verified) {
            log_activity((int) $userId, 'EMAIL_VERIFIED', 'Email address verified');
            unset($_SESSION['pending_email']);
            flash('success', 'Email verified. You can now log in.');
            redirect('login.php');
        }
    } else {
        $stmt->close();
    }
}
$pageTitle = 'Verification Link';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center py-md-4"><div class="col-md-7 col-lg-5"><div class="card"><div class="card-body p-4 p-lg-5 text-center">
    <h1 class="page-heading h3">This verification link is invalid or expired</h1>
    <p class="text-muted">Verification links expire after 24 hours and cannot be reused. Request a new link to continue.</p>
    <a class="btn btn-primary" href="<?= e(url('verify-email.php')) ?>">Request another link</a>
</div></div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
