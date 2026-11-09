<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role('admin');
$error = '';
$editing = null;

if (isset($_GET['edit'])) {
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = db()->prepare('SELECT id, course_code, course_name, department FROM courses WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $editing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'delete' && $id) {
        try {
            $stmt = db()->prepare('DELETE FROM courses WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            log_activity((int) $admin['id'], 'COURSE_DELETED', 'Deleted course #' . $id);
            flash('success', 'Subject deleted.');
            redirect('admin/courses.php');
        } catch (mysqli_sql_exception $exception) {
            $error = 'This subject is linked to appointments and cannot be deleted.';
        }
    } elseif ($action === 'save') {
        $code = strtoupper(trim((string) ($_POST['course_code'] ?? '')));
        $name = trim((string) ($_POST['course_name'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        if ($code === '' || $name === '' || $department === '') {
            $error = 'Enter a course code, subject name, and department.';
        } else {
            try {
                if ($id) {
                    $stmt = db()->prepare('UPDATE courses SET course_code = ?, course_name = ?, department = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $code, $name, $department, $id);
                    $actionName = 'COURSE_UPDATED';
                } else {
                    $stmt = db()->prepare('INSERT INTO courses (course_code, course_name, department) VALUES (?, ?, ?)');
                    $stmt->bind_param('sss', $code, $name, $department);
                    $actionName = 'COURSE_CREATED';
                }
                $stmt->execute();
                $savedId = $id ?: (int) db()->insert_id;
                $stmt->close();
                log_activity((int) $admin['id'], $actionName, 'Saved subject ' . $code);
                flash('success', 'Subject saved.');
                redirect('admin/courses.php');
            } catch (mysqli_sql_exception $exception) {
                $error = 'Could not save the subject. The course code may already exist.';
            }
        }
    }
}

$courses = db()->query('SELECT id, course_code, course_name, department FROM courses ORDER BY course_code');
$pageTitle = 'Manage Subjects';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="page-heading h2">Subject catalog</h1><p class="text-muted mb-0">Add, edit, and remove subjects offered on Synapse.</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card mb-4"><div class="card-body"><h2 class="h5"><?= $editing ? 'Edit subject' : 'Add a subject' ?></h2><form method="post" class="row g-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <div class="col-md-3"><label class="form-label">Course code</label><input class="form-control" name="course_code" maxlength="20" required value="<?= e($editing['course_code'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Subject name</label><input class="form-control" name="course_name" maxlength="150" required value="<?= e($editing['course_name'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Department</label><input class="form-control" name="department" maxlength="100" required value="<?= e($editing['department'] ?? '') ?>"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Save subject</button></div>
</form></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Code</th><th>Subject</th><th>Department</th><th>Actions</th></tr></thead><tbody>
<?php while ($course = $courses->fetch_assoc()): ?><tr><td><?= e($course['course_code']) ?></td><td><?= e($course['course_name']) ?></td><td><?= e($course['department']) ?></td><td class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/courses.php?edit=' . (int) $course['id'])) ?>">Edit</a><form method="post" data-confirm="Delete this subject?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $course['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></td></tr><?php endwhile; ?>
</tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
