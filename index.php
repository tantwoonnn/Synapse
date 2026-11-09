<?php
require_once __DIR__ . '/includes/functions.php';
if ($user = current_user()) redirect(dashboard_for($user['role']));

$pageTitle = 'Welcome';
require __DIR__ . '/includes/header.php';
?>
<section class="hero rounded-4 p-4 p-md-5 mb-4 text-white">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <p class="text-uppercase fw-semibold small opacity-75 mb-2">Peer-to-peer campus tutoring</p>
            <h1 class="display-5 fw-bold">Learn from the classmate who just mastered it.</h1>
            <p class="lead mb-4">Synapse connects campus learners with peer tutors for short, focused one-hour sessions.</p>
            <a href="<?= e(url('register.php')) ?>" class="btn btn-light btn-lg me-2"><i class="bi bi-person-plus me-1"></i> Create an account</a>
            <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-light btn-lg me-2"><i class="bi bi-box-arrow-in-right me-1"></i> Log in</a>
            <a href="#how-it-works" class="btn btn-outline-light btn-lg">How it works</a>
        </div>
        <div class="col-lg-5">
            <ol class="workflow list-unstyled mb-0">
                <li><i class="bi bi-calendar-plus"></i> <strong>Booking</strong> — pick a tutor, subject, and time</li>
                <li><i class="bi bi-check2-circle"></i> <strong>Confirmation</strong> — the tutor reviews your request</li>
                <li><i class="bi bi-people"></i> <strong>Service</strong> — meet for your one-hour session</li>
                <li><i class="bi bi-star"></i> <strong>Completion</strong> — mark the session and leave a rating</li>
            </ol>
        </div>
    </div>
</section>
<section id="how-it-works" class="row g-4">
    <?php foreach ([
        ['bi-mortarboard', 'Learners', 'Browse tutors, request sessions, track progress, and share feedback.'],
        ['bi-person-check', 'Peer tutors', 'Publish weekly availability and work through requests in arrival order.'],
        ['bi-clipboard-data', 'Coordinators', 'Manage accounts and subjects, review activity, and print reports.'],
    ] as [$icon, $title, $description]): ?>
        <div class="col-md-4"><article class="card h-100"><div class="card-body p-4"><i class="bi <?= e($icon) ?> fs-2 text-synapse"></i><h2 class="h5 mt-3"><?= e($title) ?></h2><p class="text-muted mb-0"><?= e($description) ?></p></div></article></div>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
