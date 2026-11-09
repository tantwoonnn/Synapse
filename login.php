<?php
require_once __DIR__ . '/includes/functions.php';
if ($user = current_user()) redirect(dashboard_for($user['role']));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role, is_verified FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($id, $fullName, $accountEmail, $passwordHash, $role, $isVerified);
    $found = $stmt->fetch();
    $stmt->close();
    if ($found && password_verify($password, $passwordHash) && (int) $isVerified !== 1) {
        $_SESSION['pending_email'] = $accountEmail;
        flash('warning', 'Your account still needs email verification.');
        redirect('verify-email.php');
    }
    if ($found && password_verify($password, $passwordHash)) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $id, 'full_name' => $fullName, 'email' => $accountEmail, 'role' => $role];
        log_activity((int) $id, 'LOGIN', 'User logged in');
        redirect(dashboard_for($role));
    }
    $error = 'Invalid email or password.';
}

$pageTitle = 'Log In';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center py-md-4"><div class="col-md-7 col-lg-5">
    <div class="text-center mb-4"><p class="text-uppercase text-synapse fw-semibold small mb-1">Welcome back</p><h1 class="page-heading">Log in to Synapse</h1><p class="text-muted">Continue to your learner, tutor, or coordinator workspace.</p></div>
    <div class="card"><div class="card-body p-4 p-lg-5">
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('login.php')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control form-control-lg" id="email" type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
            <div class="mb-4"><label class="form-label" for="password">Password</label><input class="form-control form-control-lg" id="password" type="password" name="password" required autocomplete="current-password"></div>
            <button class="btn btn-primary btn-lg w-100" type="submit">Log in</button>
        </form>
        <p class="small text-muted mt-4 mb-0">Demo account details are in README.md.</p>
    </div></div>
    <p class="text-center small mt-3">New to Synapse? <a href="<?= e(url('register.php')) ?>">Create a learner account</a></p>
    <p class="text-center small mt-2"><a href="<?= e(url('index.php')) ?>">Back to Synapse</a></p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
