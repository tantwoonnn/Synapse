# Synapse Setup Guide

Synapse is a PHP and MySQL peer tutoring system. This guide explains how to run it locally with XAMPP on Windows.

## What you need

- XAMPP with Apache, MySQL, and PHP 8.1 or newer.
- Composer for PHP dependency installation.
- A Gmail account with 2-Step Verification and a Google App Password if you want to send real verification emails.

The PHP installation should have `mysqli`, `mbstring`, and OpenSSL enabled. XAMPP normally includes these extensions.

## 1. Put the project in XAMPP

1. Clone the repository, or download it as a ZIP and extract it:

   ```powershell
   git clone https://github.com/tantwoonnn/Synapse.git C:\xampp\htdocs\Synapse
   ```

2. If you downloaded a ZIP, place the extracted project folder at `C:\xampp\htdocs\Synapse`.
3. Open the XAMPP Control Panel and start **Apache** and **MySQL**.

## 2. Create the database

1. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Choose **Import**.
3. Select the project's `database.sql` file and import it.

The script creates the `synapse` database, tables, and demo records. It also adds missing email-verification fields and the tutor-application table when run against an existing `synapse` database. It does not drop existing tables or records. Back up your database before importing into an existing project.

This setup expects the database to be named `synapse`. If your existing database has a different name, export its tables and data, create or select `synapse`, and import that backup there before continuing. Keep the old database until you have confirmed Synapse works.

## 3. Install PHPMailer

Open the project folder in VS Code, then choose **Terminal -> New Terminal**. Run each command separately:

```powershell
cd C:\xampp\htdocs\Synapse
composer install
```

If PowerShell says Composer is not recognized, install Composer for Windows and select `C:\xampp\php\php.exe` in the installer. Close and reopen VS Code after installation, then check `composer --version`.

## 4. Set up email verification

Create a local settings file by running:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Open `.env` and fill in the SMTP values. Example for Gmail:

```dotenv
APP_URL=http://localhost/Synapse
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-sender@gmail.com
SMTP_PASSWORD=your-google-app-password
SMTP_FROM_EMAIL=your-sender@gmail.com
SMTP_FROM_NAME=Synapse
```

Use a Google App Password, not your normal Gmail password. Keep `.env` private and do not commit it to GitHub. The repository's `.gitignore` excludes it.

## 5. Open Synapse

Go to [http://localhost/Synapse/](http://localhost/Synapse/). Register a learner account with an email address you can access, then open the verification link from your inbox. Verified accounts can log in and use the protected pages.

## Demo accounts

These verified accounts are inserted by `database.sql` for local demonstration:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@synapse.test` | `Admin@123` |
| Tutor | `staff@synapse.test` | `Staff@123` |
| Tutor | `staff2@synapse.test` | `Staff@123` |
| Learner | `user@synapse.test` | `User@123` |

The demo emails use the reserved `.test` domain and cannot receive mail. Use a real email address for new registrations and verification.

## Main features

- Learners can find tutors, request sessions, view their bookings, rate completed sessions, and apply to become tutors.
- Tutors can manage weekly availability, review requests in arrival order, and mark sessions complete.
- Administrators can manage accounts and subjects, review tutor applications, manage appointments, view activity logs, and print date-filtered reports.
- Email verification links expire after 24 hours and can only be used once.

## Troubleshooting

- **Unknown database `synapse`:** import `database.sql` in phpMyAdmin and confirm the database name is `synapse`.
- **Unknown column or table:** import `database.sql` into the `synapse` database. It creates missing tables and adds missing email-verification fields without deleting existing data.
- **Composer is not recognized:** install Composer for Windows, select `C:\xampp\php\php.exe`, and reopen VS Code.
- **Verification email is not sent:** check that Composer completed, `.env` has the correct sender email and App Password, Apache/MySQL are running, and `APP_URL` is `http://localhost/Synapse`.
- **Database login fails:** the local defaults are in `config/database.php` (`localhost`, user `root`, blank password). Update those values if your MySQL setup uses different credentials.

## Sharing the project safely

Commit `composer.json`, `composer.lock`, `.env.example`, and the source files. Do not commit `.env` or the `vendor` folder. After downloading the project, each person can run `composer install` to install its PHP dependency.
