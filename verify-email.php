<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Verify Email';
$email = (string) ($_SESSION['pending_email'] ?? '');
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center py-md-4"><div class="col-md-7 col-lg-5"><div class="card"><div class="card-body p-4 p-lg-5 text-center">
    <i class="bi bi-envelope-check display-4 text-synapse"></i>
    <h1 class="page-heading h3 mt-3">Verify your email</h1>
    <p class="text-muted">Your account needs a verified email before you can use protected features. The link expires after 24 hours and works once.</p>
    <form method="post" action="<?= e(url('resend-verification.php')) ?>" class="text-start mt-4">
        <?= csrf_field() ?>
        <label class="form-label" for="email">Email address</label>
        <input class="form-control mb-3" type="email" id="email" name="email" required value="<?= e($email) ?>">
        <button class="btn btn-primary w-100" type="submit">Resend verification email</button>
    </form>
    <p class="small mt-3 mb-0"><a href="<?= e(url('login.php')) ?>">Back to login</a></p>
</div></div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
