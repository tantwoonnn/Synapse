<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/functions.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function send_verification_email(string $email, string $name, string $token): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('PHPMailer is missing. Run composer install in the Synapse folder.');
        return false;
    }
    require_once $autoload;

    $username = project_env('SMTP_USERNAME');
    $password = project_env('SMTP_PASSWORD');
    if ($username === '' || $password === '') {
        error_log('Synapse SMTP_USERNAME or SMTP_PASSWORD is not configured.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = project_env('SMTP_HOST', 'smtp.gmail.com');
        $mail->Port = (int) project_env('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(project_env('SMTP_FROM_EMAIL', $username), project_env('SMTP_FROM_NAME', 'Synapse'));
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $link = url('verify.php?token=' . urlencode($token));
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mail->Subject = 'Verify your Synapse account';
        $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto">'
            . '<h2>Welcome to Synapse, ' . $safeName . '!</h2>'
            . '<p>Confirm your email address to activate your account.</p>'
            . '<p><a href="' . $safeLink . '">Verify my email</a></p>'
            . '<p>This link expires in 24 hours and can only be used once.</p>'
            . '<p>If you did not create this account, you can ignore this message.</p></div>';
        $mail->AltBody = "Welcome to Synapse, $name!\n\nVerify your email: $link\n\nThis link expires in 24 hours and can only be used once.";
        $mail->send();
        return true;
    } catch (MailException $exception) {
        error_log('Synapse mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
