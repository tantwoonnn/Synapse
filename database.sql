-- Synapse database setup for fresh installs and existing synapse databases.
-- This script creates missing tables and adds missing email-verification fields.
-- It does not drop existing data. Keep a backup before importing into a live DB.

CREATE DATABASE IF NOT EXISTS synapse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE synapse;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user',
    is_verified TINYINT(1) NOT NULL DEFAULT 1,
    verification_token CHAR(32) NULL,
    token_expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add email-verification fields to older users tables, if any are missing.
SET @has_is_verified = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_verified');
SET @ddl_is_verified = IF(@has_is_verified = 0, 'ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 1 AFTER role', 'SELECT 1');
PREPARE stmt FROM @ddl_is_verified;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_verification_token = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verification_token');
SET @ddl_verification_token = IF(@has_verification_token = 0, 'ALTER TABLE users ADD COLUMN verification_token CHAR(32) NULL AFTER is_verified', 'SELECT 1');
PREPARE stmt FROM @ddl_verification_token;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_token_expires_at = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'token_expires_at');
SET @ddl_token_expires_at = IF(@has_token_expires_at = 0, 'ALTER TABLE users ADD COLUMN token_expires_at DATETIME NULL AFTER verification_token', 'SELECT 1');
PREPARE stmt FROM @ddl_token_expires_at;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure verification tokens are unique, without adding a duplicate index
-- when an earlier migration already created one.
SET @has_unique_verification_token = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verification_token' AND NON_UNIQUE = 0);
SET @ddl_unique_verification_token = IF(@has_unique_verification_token = 0, 'ALTER TABLE users ADD UNIQUE KEY uq_users_verification_token (verification_token)', 'SELECT 1');
PREPARE stmt FROM @ddl_unique_verification_token;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tutor_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    learner_id INT NOT NULL UNIQUE,
    academic_program VARCHAR(120) NOT NULL,
    subjects VARCHAR(300) NOT NULL,
    experience TEXT NOT NULL,
    motivation TEXT NOT NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    admin_note VARCHAR(500) NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    CONSTRAINT fk_tutor_application_learner FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tutor_application_status (status, applied_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tutor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT NOT NULL DEFAULT 1,
    CONSTRAINT fk_schedule_tutor FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_schedule_day CHECK (day_of_week BETWEEN 0 AND 6),
    CONSTRAINT chk_schedule_time CHECK (end_time > start_time),
    UNIQUE KEY uq_schedule (tutor_id, day_of_week, start_time, end_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    learner_id INT NOT NULL,
    tutor_id INT NOT NULL,
    course_id INT NOT NULL,
    booking_date DATE NOT NULL,
    session_time TIME NOT NULL,
    status ENUM('PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_appointment_learner FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_tutor FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    INDEX idx_appointment_tutor_slot (tutor_id, booking_date, session_time, status),
    INDEX idx_appointment_learner (learner_id),
    INDEX idx_appointment_queue (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS session_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL UNIQUE,
    rating TINYINT NOT NULL,
    comment VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details VARCHAR(500) NULL,
    log_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_time (log_timestamp)
) ENGINE=InnoDB;

-- The password hashes were created with PHP password_hash().
INSERT IGNORE INTO users (full_name, email, password_hash, role, is_verified) VALUES
('Campus Coordinator', 'admin@synapse.test', '$2y$10$fCPMVoHmsnGY/Lv79RDo2esFtogkH6H3LbVcSAwcpstkubD7FtRqe', 'admin', 1),
('Tina Tutor', 'staff@synapse.test', '$2y$10$c4WPVeNw0/rOPuYFy8C6h.ifWOGDZRIOSAWKUGogA5.3Hu7WxBKRu', 'staff', 1),
('Marco Mentor', 'staff2@synapse.test', '$2y$10$c4WPVeNw0/rOPuYFy8C6h.ifWOGDZRIOSAWKUGogA5.3Hu7WxBKRu', 'staff', 1),
('Lea Learner', 'user@synapse.test', '$2y$10$QkmIudaefQWEdanlxL9A3uTxJ7dJxXrhdj3A2YeAciUiWeyKzzsRy', 'user', 1);

INSERT IGNORE INTO courses (course_code, course_name, department) VALUES
('IT101', 'Introduction to Computing', 'Information Technology'),
('IT202', 'Data Structures and Algorithms', 'Information Technology'),
('IT305', 'Web Systems and Technologies', 'Information Technology'),
('MATH101', 'College Algebra', 'Mathematics'),
('MATH201', 'Calculus I', 'Mathematics'),
('ENG101', 'Purposive Communication', 'Languages'),
('SCI110', 'General Chemistry', 'Natural Sciences'),
('ACC101', 'Fundamentals of Accounting', 'Business');

INSERT IGNORE INTO tutor_schedules (tutor_id, day_of_week, start_time, end_time, is_available)
SELECT u.id, s.day_of_week, s.start_time, s.end_time, 1
FROM users u
JOIN (
    SELECT 'staff@synapse.test' AS email, 1 AS day_of_week, '08:00:00' AS start_time, '12:00:00' AS end_time UNION ALL
    SELECT 'staff@synapse.test', 2, '08:00:00', '12:00:00' UNION ALL
    SELECT 'staff@synapse.test', 3, '08:00:00', '12:00:00' UNION ALL
    SELECT 'staff@synapse.test', 4, '08:00:00', '12:00:00' UNION ALL
    SELECT 'staff@synapse.test', 5, '08:00:00', '12:00:00' UNION ALL
    SELECT 'staff@synapse.test', 1, '13:00:00', '17:00:00' UNION ALL
    SELECT 'staff@synapse.test', 3, '13:00:00', '17:00:00' UNION ALL
    SELECT 'staff@synapse.test', 5, '13:00:00', '17:00:00' UNION ALL
    SELECT 'staff2@synapse.test', 2, '09:00:00', '16:00:00' UNION ALL
    SELECT 'staff2@synapse.test', 4, '09:00:00', '16:00:00' UNION ALL
    SELECT 'staff2@synapse.test', 6, '09:00:00', '16:00:00'
) s ON s.email = u.email
WHERE u.role = 'staff';
