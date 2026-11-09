<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

function db(): mysqli
{
    global $conn;
    return $conn;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(project_env('APP_URL', 'http://localhost/Synapse'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function dashboard_for(string $role): string
{
    if ($role === 'admin') return 'admin/dashboard.php';
    if ($role === 'staff') return 'staff/dashboard.php';
    return 'user/dashboard.php';
}

function require_role(string|array $roles): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please log in first.');
        redirect('login.php');
    }
    $stmt = db()->prepare('SELECT full_name, email, role, is_verified FROM users WHERE id = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php');
    }
    if ((int) $account['is_verified'] !== 1) {
        unset($_SESSION['user']);
        $_SESSION['pending_email'] = $account['email'];
        flash('warning', 'Verify your email before using protected features.');
        redirect('verify-email.php');
    }
    $_SESSION['user'] = ['id' => (int) $user['id'], 'full_name' => $account['full_name'], 'email' => $account['email'], 'role' => $account['role']];
    $user = $_SESSION['user'];
    if (!in_array($user['role'], (array) $roles, true)) {
        http_response_code(403);
        exit('403 Forbidden. You do not have permission to view this page.');
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Form expired. Refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function log_activity(?int $userId, string $action, string $details = ''): void
{
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function status_badge(string $status): string
{
    $class = match ($status) {
        'PENDING' => 'warning text-dark',
        'CONFIRMED' => 'primary',
        'COMPLETED' => 'success',
        'CANCELLED' => 'secondary',
        default => 'light text-dark',
    };
    return '<span class="badge bg-' . $class . '">' . e($status) . '</span>';
}

function day_name(int $day): string
{
    return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day] ?? 'Unknown';
}

function format_date(string $date): string
{
    return date('M j, Y', strtotime($date));
}

function format_time(string $time): string
{
    return date('g:i A', strtotime($time));
}
