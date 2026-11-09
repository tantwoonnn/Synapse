<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$_SESSION['pending_email'] = $email;
$lastSent = (int) ($_SESSION['last_verification_resend'] ?? 0);
if (time() - $lastSent < 60) {
    flash('warning', 'Please wait a minute before requesting another verification email.');
    redirect('verify-email.php');
}
$_SESSION['last_verification_resend'] = time();

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = db()->prepare('SELECT id, full_name FROM users WHERE email = ? AND is_verified = 0 LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($userId, $fullName);
    if ($stmt->fetch()) {
        $stmt->close();
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $update = db()->prepare('UPDATE users SET verification_token = ?, token_expires_at = ? WHERE id = ? AND is_verified = 0');
        $update->bind_param('ssi', $token, $expires, $userId);
        $update->execute();
        $update->close();
        log_activity((int) $userId, 'VERIFICATION_RESENT', 'Issued a new email verification link');
        if (send_verification_email($email, $fullName, $token)) {
            flash('success', 'If the account exists and is still unverified, a new link has been sent.');
        } else {
            flash('warning', 'Email delivery failed. Check SMTP configuration before requesting another link.');
        }
        redirect('verify-email.php');
    }
    $stmt->close();
}
flash('info', 'If the account exists and is still unverified, a new link has been sent.');
redirect('verify-email.php');
