<?php
/** Local XAMPP database connection for the Synapse classroom build. */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'synapse';

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    http_response_code(500);
    exit('Database connection failed. Import database.sql and check config/database.php.');
}
mysqli_set_charset($conn, 'utf8mb4');
