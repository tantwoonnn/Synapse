<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
if ($user = current_user()) redirect(dashboard_for($user['role']));

$errors = [];
$name = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2 to 100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) $errors[] = 'Password must be at least 8 characters and include letters and numbers.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        try {
            $stmt = db()->prepare("INSERT INTO users (full_name, email, password_hash, role, is_verified, verification_token, token_expires_at) VALUES (?, ?, ?, 'user', 0, ?, ?)");
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('sssss', $name, $email, $hash, $token, $expires);
            $stmt->execute();
            $userId = (int) db()->insert_id;
            $stmt->close();
            log_activity($userId, 'REGISTER', 'Created a learner account');
            $_SESSION['pending_email'] = $email;
            if (send_verification_email($email, $name, $token)) {
                flash('success', 'Account created. Check your inbox for the verification link.');
            } else {
                flash('warning', 'Account created, but the verification email could not be sent. Check SMTP setup, then request another email.');
            }
            redirect('verify-email.php');
        } catch (mysqli_sql_exception $exception) {
            $errors[] = 'That email address may already be registered.';
        }
    }
}

$pageTitle = 'Create Account';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center py-md-4"><div class="col-md-7 col-lg-5"><div class="card"><div class="card-body p-4 p-lg-5">
    <h1 class="page-heading h3">Create a learner account</h1>
    <p class="text-muted">We will email you a one-time verification link that expires in 24 hours.</p>
    <?php if ($errors): ?><div class="alert alert-secondary"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= e(url('register.php')) ?>">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" maxlength="100" required value="<?= e($name) ?>"></div>
        <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" type="email" id="email" name="email" maxlength="150" required value="<?= e($email) ?>"></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" minlength="8" required autocomplete="new-password"></div>
        <div class="mb-4"><label class="form-label" for="password_confirm">Confirm password</label><input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password"></div>
        <button class="btn btn-primary w-100" type="submit">Create account</button>
    </form>
    <p class="small text-center mt-3 mb-0">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</div></div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
