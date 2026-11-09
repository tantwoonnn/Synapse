<?php
require_once __DIR__ . '/../includes/functions.php';
$tutor = require_role('staff');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$id = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
if (!$id || !in_array($action, ['approve', 'reject', 'complete'], true)) {
    flash('danger', 'Invalid session action.');
    redirect('staff/dashboard.php');
}

$pdo = db();
$pdo->begin_transaction();
try {
    $stmt = $pdo->prepare('SELECT booking_date, session_time, status FROM appointments WHERE id = ? AND tutor_id = ? FOR UPDATE');
    $stmt->bind_param('ii', $id, $tutor['id']);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$appointment) throw new RuntimeException('Session request was not found.');

    if ($action === 'approve' && $appointment['status'] === 'PENDING') {
        $check = $pdo->prepare("SELECT session_time FROM appointments WHERE tutor_id = ? AND booking_date = ? AND status = 'CONFIRMED' AND id <> ?");
        $check->bind_param('isi', $tutor['id'], $appointment['booking_date'], $id);
        $check->execute();
        $busy = $check->get_result();
        $start = strtotime('1970-01-01 ' . $appointment['session_time']);
        while ($other = $busy->fetch_assoc()) {
            $otherStart = strtotime('1970-01-01 ' . $other['session_time']);
            if ($start < $otherStart + 3600 && $otherStart < $start + 3600) throw new RuntimeException('This tutor already has a confirmed session that overlaps this time.');
        }
        $check->close();
        $newStatus = 'CONFIRMED';
    } elseif ($action === 'reject' && $appointment['status'] === 'PENDING') {
        $newStatus = 'CANCELLED';
    } elseif ($action === 'complete' && $appointment['status'] === 'CONFIRMED') {
        $newStatus = 'COMPLETED';
    } else {
        throw new RuntimeException('This session is no longer in a state that can use that action.');
    }

    $update = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ? AND tutor_id = ?');
    $update->bind_param('sii', $newStatus, $id, $tutor['id']);
    $update->execute();
    $update->close();
    log_activity((int) $tutor['id'], 'SESSION_' . $newStatus, 'Updated appointment #' . $id);
    $pdo->commit();
    flash('success', 'Session updated to ' . strtolower($newStatus) . '.');
} catch (RuntimeException $exception) {
    $pdo->rollback();
    flash('warning', $exception->getMessage());
} catch (Throwable $exception) {
    $pdo->rollback();
    error_log('Tutor appointment action failed: ' . $exception->getMessage());
    flash('danger', 'Could not update the session. Please try again.');
}
redirect('staff/dashboard.php');
