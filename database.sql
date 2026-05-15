-- School Attendance Management System - Database Schema
-- Created for Joy's PRD by Jayesh V

CREATE DATABASE IF NOT EXISTS school_attendance;
USE school_attendance;

-- Users table (Admin, Principal, Teacher)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','principal','teacher') NOT NULL DEFAULT 'teacher',
    phone VARCHAR(20),
    subject VARCHAR(100),
    employee_id VARCHAR(50) UNIQUE,
    photo VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    roll_number VARCHAR(50) UNIQUE NOT NULL,
    class VARCHAR(20) NOT NULL,
    section VARCHAR(10),
    card_uid VARCHAR(100) UNIQUE,
    parent_email VARCHAR(150),
    parent_phone VARCHAR(20),
    photo VARCHAR(255),
    date_of_birth DATE,
    address TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Student Attendance table
CREATE TABLE IF NOT EXISTS student_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    status ENUM('present','absent','late','excused') DEFAULT 'present',
    marked_by ENUM('card','manual','system') DEFAULT 'card',
    marked_by_user_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_date (student_id, date)
);

-- Teacher Attendance table
CREATE TABLE IF NOT EXISTS teacher_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present','absent','late','half_day','on_leave') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_date (teacher_id, date)
);

-- Audit Logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Email Notifications table
CREATE TABLE IF NOT EXISTS email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    notification_type ENUM('absent','late','report') DEFAULT 'absent',
    recipient_email VARCHAR(150),
    subject VARCHAR(255),
    message TEXT,
    status ENUM('pending','sent','failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- System Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('school_name', 'Springfield Public School', 'Name of the school'),
('school_address', '123 Main Street, Springfield, State', 'School address for header and location display'),
('google_maps_location', '', 'Google Maps embed URL or map link for the school location'),
('cutoff_time', '09:30:00', 'Time after which student is marked absent'),
('late_time', '08:15:00', 'Time after which student is marked late'),
('school_start_time', '07:30:00', 'School start time'),
('academic_year', '2025-2026', 'Current academic year'),
('smtp_host', '', 'SMTP server host'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP username/email'),
('smtp_password', '', 'SMTP password'),
('smtp_from_name', 'School Attendance System', 'From name for emails'),
('smtp_encryption', 'tls', 'SMTP encryption type'),
('email_notifications', '1', 'Enable/disable email notifications')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Insert default admin user (password: admin@123)
INSERT INTO users (name, email, password, role, employee_id) VALUES
('System Admin', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ADM001'),
('Principal John', 'principal@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'principal', 'PRI001'),
('Teacher Sarah', 'teacher@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'TCH001')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert sample students
INSERT INTO students (name, roll_number, class, section, card_uid, parent_email) VALUES
('Alice Johnson', 'S001', '10', 'A', 'CARD001', 'alice.parent@email.com'),
('Bob Smith', 'S002', '10', 'A', 'CARD002', 'bob.parent@email.com'),
('Charlie Brown', 'S003', '10', 'B', 'CARD003', 'charlie.parent@email.com'),
('Diana Prince', 'S004', '9', 'A', 'CARD004', 'diana.parent@email.com'),
('Edward Norton', 'S005', '9', 'B', 'CARD005', 'edward.parent@email.com'),
('Fiona Green', 'S006', '8', 'A', 'CARD006', 'fiona.parent@email.com'),
('George Wilson', 'S007', '8', 'B', 'CARD007', 'george.parent@email.com'),
('Hannah Lee', 'S008', '7', 'A', 'CARD008', 'hannah.parent@email.com')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Note: Default password for all users is 'password'
-- Admin: admin@school.com / password
-- Principal: principal@school.com / password
-- Teacher: teacher@school.com / password

-- Create admin and principal principals assignment
ALTER TABLE users ADD COLUMN assigned_principals INT DEFAULT NULL AFTER role;
CREATE TABLE IF NOT EXISTS principal_schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    principal_id INT NOT NULL,
    school_id INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (principal_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create teacher class assignment
CREATE TABLE IF NOT EXISTS teacher_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class VARCHAR(20) NOT NULL,
    section VARCHAR(10),
    subject VARCHAR(100),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);