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

-- Additional settings for external email API
INSERT INTO settings (setting_key, setting_value, description) VALUES
('email_api_url', '', 'External email API endpoint URL'),
('email_api_key', '', 'External email API key'),
('email_api_header', 'Authorization', 'External email API authorization header name')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ==================== SAMPLE ATTENDANCE DATA FOR TESTING ====================
-- Insert student attendance data for April-May 2026 (past 30 days)

-- Student 1: Alice Johnson - 10/A (Good attendance)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(1, '2026-04-15', '07:35', 'present', 'card'),
(1, '2026-04-16', '07:40', 'present', 'card'),
(1, '2026-04-17', '08:10', 'late', 'card'),
(1, '2026-04-18', '07:45', 'present', 'card'),
(1, '2026-04-19', '07:38', 'present', 'card'),
(1, '2026-04-20', '07:50', 'present', 'card'),
(1, '2026-04-21', '07:32', 'present', 'card'),
(1, '2026-04-22', '07:55', 'present', 'card'),
(1, '2026-04-23', '07:40', 'present', 'card'),
(1, '2026-04-24', '07:35', 'present', 'card'),
(1, '2026-04-25', '08:05', 'late', 'card'),
(1, '2026-04-26', '07:38', 'present', 'card'),
(1, '2026-04-27', '07:45', 'present', 'card'),
(1, '2026-04-28', '07:40', 'present', 'card'),
(1, '2026-04-29', '07:32', 'present', 'card'),
(1, '2026-04-30', '07:50', 'present', 'card'),
(1, '2026-05-01', '07:35', 'present', 'card'),
(1, '2026-05-02', '07:40', 'present', 'card'),
(1, '2026-05-03', '07:45', 'present', 'card'),
(1, '2026-05-04', '07:38', 'present', 'card'),
(1, '2026-05-05', '07:35', 'present', 'card'),
(1, '2026-05-06', '08:20', 'late', 'card'),
(1, '2026-05-07', '07:40', 'present', 'card'),
(1, '2026-05-08', '07:32', 'present', 'card'),
(1, '2026-05-09', '07:45', 'present', 'card'),
(1, '2026-05-10', '07:50', 'present', 'card'),
(1, '2026-05-11', '07:38', 'present', 'card'),
(1, '2026-05-12', '07:40', 'present', 'card'),
(1, '2026-05-13', '07:35', 'present', 'card'),
(1, '2026-05-14', '07:42', 'present', 'card');

-- Student 2: Bob Smith - 10/A (Few absences)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(2, '2026-04-15', '07:38', 'present', 'card'),
(2, '2026-04-16', '07:42', 'present', 'card'),
(2, '2026-04-17', '08:30', 'absent', 'system'),
(2, '2026-04-18', '07:45', 'present', 'card'),
(2, '2026-04-19', NULL, 'absent', 'system'),
(2, '2026-04-20', '07:50', 'present', 'card'),
(2, '2026-04-21', '07:35', 'present', 'card'),
(2, '2026-04-22', '07:55', 'present', 'card'),
(2, '2026-04-23', '07:40', 'present', 'card'),
(2, '2026-04-24', '07:35', 'present', 'card'),
(2, '2026-04-25', '08:10', 'late', 'card'),
(2, '2026-04-26', '07:38', 'present', 'card'),
(2, '2026-04-27', '07:45', 'present', 'card'),
(2, '2026-04-28', NULL, 'absent', 'system'),
(2, '2026-04-29', '07:32', 'present', 'card'),
(2, '2026-04-30', '07:50', 'present', 'card'),
(2, '2026-05-01', '07:35', 'present', 'card'),
(2, '2026-05-02', '07:40', 'present', 'card'),
(2, '2026-05-03', '07:45', 'present', 'card'),
(2, '2026-05-04', '07:38', 'present', 'card'),
(2, '2026-05-05', NULL, 'absent', 'system'),
(2, '2026-05-06', '07:42', 'present', 'card'),
(2, '2026-05-07', '07:40', 'present', 'card'),
(2, '2026-05-08', '07:32', 'present', 'card'),
(2, '2026-05-09', '07:45', 'present', 'card'),
(2, '2026-05-10', '07:50', 'present', 'card'),
(2, '2026-05-11', '08:15', 'late', 'card'),
(2, '2026-05-12', '07:40', 'present', 'card'),
(2, '2026-05-13', '07:35', 'present', 'card'),
(2, '2026-05-14', '07:42', 'present', 'card');

-- Student 3: Charlie Brown - 10/B (Regular absences)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(3, '2026-04-15', NULL, 'absent', 'system'),
(3, '2026-04-16', '07:42', 'present', 'card'),
(3, '2026-04-17', '07:35', 'present', 'card'),
(3, '2026-04-18', NULL, 'absent', 'system'),
(3, '2026-04-19', '07:48', 'present', 'card'),
(3, '2026-04-20', NULL, 'absent', 'system'),
(3, '2026-04-21', '07:35', 'present', 'card'),
(3, '2026-04-22', '07:55', 'present', 'card'),
(3, '2026-04-23', NULL, 'absent', 'system'),
(3, '2026-04-24', '07:35', 'present', 'card'),
(3, '2026-04-25', '08:10', 'late', 'card'),
(3, '2026-04-26', NULL, 'absent', 'system'),
(3, '2026-04-27', '07:45', 'present', 'card'),
(3, '2026-04-28', NULL, 'absent', 'system'),
(3, '2026-04-29', '07:32', 'present', 'card'),
(3, '2026-04-30', NULL, 'absent', 'system'),
(3, '2026-05-01', '07:35', 'present', 'card'),
(3, '2026-05-02', NULL, 'absent', 'system'),
(3, '2026-05-03', '07:45', 'present', 'card'),
(3, '2026-05-04', '07:38', 'present', 'card'),
(3, '2026-05-05', NULL, 'absent', 'system'),
(3, '2026-05-06', '07:42', 'present', 'card'),
(3, '2026-05-07', '08:25', 'late', 'card'),
(3, '2026-05-08', '07:32', 'present', 'card'),
(3, '2026-05-09', NULL, 'absent', 'system'),
(3, '2026-05-10', '07:50', 'present', 'card'),
(3, '2026-05-11', '07:38', 'present', 'card'),
(3, '2026-05-12', '07:40', 'present', 'card'),
(3, '2026-05-13', NULL, 'absent', 'system'),
(3, '2026-05-14', '07:42', 'present', 'card');

-- Student 4: Diana Prince - 9/A (Excellent attendance)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(4, '2026-04-15', '07:35', 'present', 'card'),
(4, '2026-04-16', '07:40', 'present', 'card'),
(4, '2026-04-17', '07:32', 'present', 'card'),
(4, '2026-04-18', '07:45', 'present', 'card'),
(4, '2026-04-19', '07:38', 'present', 'card'),
(4, '2026-04-20', '07:50', 'present', 'card'),
(4, '2026-04-21', '07:32', 'present', 'card'),
(4, '2026-04-22', '07:55', 'present', 'card'),
(4, '2026-04-23', '07:40', 'present', 'card'),
(4, '2026-04-24', '07:35', 'present', 'card'),
(4, '2026-04-25', '07:42', 'present', 'card'),
(4, '2026-04-26', '07:38', 'present', 'card'),
(4, '2026-04-27', '07:45', 'present', 'card'),
(4, '2026-04-28', '07:40', 'present', 'card'),
(4, '2026-04-29', '07:32', 'present', 'card'),
(4, '2026-04-30', '07:50', 'present', 'card'),
(4, '2026-05-01', '07:35', 'present', 'card'),
(4, '2026-05-02', '07:40', 'present', 'card'),
(4, '2026-05-03', '07:45', 'present', 'card'),
(4, '2026-05-04', '07:38', 'present', 'card'),
(4, '2026-05-05', '07:35', 'present', 'card'),
(4, '2026-05-06', '07:42', 'present', 'card'),
(4, '2026-05-07', '07:40', 'present', 'card'),
(4, '2026-05-08', '07:32', 'present', 'card'),
(4, '2026-05-09', '07:45', 'present', 'card'),
(4, '2026-05-10', '07:50', 'present', 'card'),
(4, '2026-05-11', '07:38', 'present', 'card'),
(4, '2026-05-12', '07:40', 'present', 'card'),
(4, '2026-05-13', '07:35', 'present', 'card'),
(4, '2026-05-14', '07:42', 'present', 'card');

-- Student 5: Edward Norton - 9/B (Average attendance)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(5, '2026-04-15', '07:38', 'present', 'card'),
(5, '2026-04-16', '08:05', 'late', 'card'),
(5, '2026-04-17', '07:35', 'present', 'card'),
(5, '2026-04-18', NULL, 'absent', 'system'),
(5, '2026-04-19', '07:48', 'present', 'card'),
(5, '2026-04-20', '07:50', 'present', 'card'),
(5, '2026-04-21', '07:32', 'present', 'card'),
(5, '2026-04-22', '07:55', 'present', 'card'),
(5, '2026-04-23', '07:40', 'present', 'card'),
(5, '2026-04-24', NULL, 'absent', 'system'),
(5, '2026-04-25', '08:10', 'late', 'card'),
(5, '2026-04-26', '07:38', 'present', 'card'),
(5, '2026-04-27', '07:45', 'present', 'card'),
(5, '2026-04-28', '07:40', 'present', 'card'),
(5, '2026-04-29', '07:32', 'present', 'card'),
(5, '2026-04-30', '07:50', 'present', 'card'),
(5, '2026-05-01', '07:35', 'present', 'card'),
(5, '2026-05-02', '07:40', 'present', 'card'),
(5, '2026-05-03', '08:15', 'late', 'card'),
(5, '2026-05-04', NULL, 'absent', 'system'),
(5, '2026-05-05', '07:35', 'present', 'card'),
(5, '2026-05-06', '07:42', 'present', 'card'),
(5, '2026-05-07', '07:40', 'present', 'card'),
(5, '2026-05-08', '07:32', 'present', 'card'),
(5, '2026-05-09', '07:45', 'present', 'card'),
(5, '2026-05-10', '07:50', 'present', 'card'),
(5, '2026-05-11', '07:38', 'present', 'card'),
(5, '2026-05-12', NULL, 'absent', 'system'),
(5, '2026-05-13', '07:35', 'present', 'card'),
(5, '2026-05-14', '07:42', 'present', 'card');

-- Student 6: Fiona Green - 8/A (Good attendance)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(6, '2026-04-15', '07:35', 'present', 'card'),
(6, '2026-04-16', '07:40', 'present', 'card'),
(6, '2026-04-17', '07:32', 'present', 'card'),
(6, '2026-04-18', '07:45', 'present', 'card'),
(6, '2026-04-19', '07:38', 'present', 'card'),
(6, '2026-04-20', '07:50', 'present', 'card'),
(6, '2026-04-21', NULL, 'absent', 'system'),
(6, '2026-04-22', '07:55', 'present', 'card'),
(6, '2026-04-23', '07:40', 'present', 'card'),
(6, '2026-04-24', '07:35', 'present', 'card'),
(6, '2026-04-25', '07:42', 'present', 'card'),
(6, '2026-04-26', '07:38', 'present', 'card'),
(6, '2026-04-27', '07:45', 'present', 'card'),
(6, '2026-04-28', '07:40', 'present', 'card'),
(6, '2026-04-29', '07:32', 'present', 'card'),
(6, '2026-04-30', '07:50', 'present', 'card'),
(6, '2026-05-01', '07:35', 'present', 'card'),
(6, '2026-05-02', '07:40', 'present', 'card'),
(6, '2026-05-03', '07:45', 'present', 'card'),
(6, '2026-05-04', '07:38', 'present', 'card'),
(6, '2026-05-05', '07:35', 'present', 'card'),
(6, '2026-05-06', '07:42', 'present', 'card'),
(6, '2026-05-07', '07:40', 'present', 'card'),
(6, '2026-05-08', '08:20', 'late', 'card'),
(6, '2026-05-09', '07:45', 'present', 'card'),
(6, '2026-05-10', '07:50', 'present', 'card'),
(6, '2026-05-11', '07:38', 'present', 'card'),
(6, '2026-05-12', '07:40', 'present', 'card'),
(6, '2026-05-13', '07:35', 'present', 'card'),
(6, '2026-05-14', '07:42', 'present', 'card');

-- Student 7: George Wilson - 8/B (Frequent absences)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(7, '2026-04-15', NULL, 'absent', 'system'),
(7, '2026-04-16', '07:42', 'present', 'card'),
(7, '2026-04-17', NULL, 'absent', 'system'),
(7, '2026-04-18', '07:45', 'present', 'card'),
(7, '2026-04-19', NULL, 'absent', 'system'),
(7, '2026-04-20', '07:50', 'present', 'card'),
(7, '2026-04-21', NULL, 'absent', 'system'),
(7, '2026-04-22', '07:55', 'present', 'card'),
(7, '2026-04-23', '07:40', 'present', 'card'),
(7, '2026-04-24', NULL, 'absent', 'system'),
(7, '2026-04-25', '08:10', 'late', 'card'),
(7, '2026-04-26', NULL, 'absent', 'system'),
(7, '2026-04-27', '07:45', 'present', 'card'),
(7, '2026-04-28', '07:40', 'present', 'card'),
(7, '2026-04-29', NULL, 'absent', 'system'),
(7, '2026-04-30', '07:50', 'present', 'card'),
(7, '2026-05-01', NULL, 'absent', 'system'),
(7, '2026-05-02', '07:40', 'present', 'card'),
(7, '2026-05-03', '07:45', 'present', 'card'),
(7, '2026-05-04', NULL, 'absent', 'system'),
(7, '2026-05-05', '07:35', 'present', 'card'),
(7, '2026-05-06', NULL, 'absent', 'system'),
(7, '2026-05-07', '07:40', 'present', 'card'),
(7, '2026-05-08', '07:32', 'present', 'card'),
(7, '2026-05-09', NULL, 'absent', 'system'),
(7, '2026-05-10', '07:50', 'present', 'card'),
(7, '2026-05-11', '07:38', 'present', 'card'),
(7, '2026-05-12', '08:25', 'late', 'card'),
(7, '2026-05-13', '07:35', 'present', 'card'),
(7, '2026-05-14', NULL, 'absent', 'system');

-- Student 8: Hannah Lee - 7/A (Good attendance)
INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES
(8, '2026-04-15', '07:35', 'present', 'card'),
(8, '2026-04-16', '07:40', 'present', 'card'),
(8, '2026-04-17', '07:32', 'present', 'card'),
(8, '2026-04-18', '07:45', 'present', 'card'),
(8, '2026-04-19', '07:38', 'present', 'card'),
(8, '2026-04-20', '07:50', 'present', 'card'),
(8, '2026-04-21', '07:32', 'present', 'card'),
(8, '2026-04-22', '07:55', 'present', 'card'),
(8, '2026-04-23', '07:40', 'present', 'card'),
(8, '2026-04-24', NULL, 'absent', 'system'),
(8, '2026-04-25', '07:42', 'present', 'card'),
(8, '2026-04-26', '07:38', 'present', 'card'),
(8, '2026-04-27', '07:45', 'present', 'card'),
(8, '2026-04-28', '07:40', 'present', 'card'),
(8, '2026-04-29', '07:32', 'present', 'card'),
(8, '2026-04-30', '07:50', 'present', 'card'),
(8, '2026-05-01', '07:35', 'present', 'card'),
(8, '2026-05-02', '07:40', 'present', 'card'),
(8, '2026-05-03', '07:45', 'present', 'card'),
(8, '2026-05-04', '07:38', 'present', 'card'),
(8, '2026-05-05', '07:35', 'present', 'card'),
(8, '2026-05-06', '07:42', 'present', 'card'),
(8, '2026-05-07', '07:40', 'present', 'card'),
(8, '2026-05-08', '07:32', 'present', 'card'),
(8, '2026-05-09', '07:45', 'present', 'card'),
(8, '2026-05-10', '07:50', 'present', 'card'),
(8, '2026-05-11', '07:38', 'present', 'card'),
(8, '2026-05-12', '07:40', 'present', 'card'),
(8, '2026-05-13', '07:35', 'present', 'card'),
(8, '2026-05-14', '07:42', 'present', 'card');

-- Sample teacher attendance data
INSERT INTO teacher_attendance (teacher_id, date, time_in, time_out, status) VALUES
(3, '2026-05-14', '07:30', '15:30', 'present'),
(3, '2026-05-13', '07:30', '15:30', 'present'),
(3, '2026-05-12', '07:35', '15:30', 'present'),
(3, '2026-05-11', '07:28', '15:30', 'present'),
(3, '2026-05-10', NULL, NULL, 'absent'),
(3, '2026-05-09', '07:32', '15:30', 'present'),
(3, '2026-05-08', '07:30', '14:00', 'half_day'),
(3, '2026-05-07', '07:25', '15:30', 'present'),
(3, '2026-05-06', '07:30', '15:30', 'present'),
(3, '2026-05-05', '07:40', '15:30', 'late');

-- Sample audit logs
INSERT INTO audit_logs (user_id, action, module, description) VALUES
(1, 'CREATE', 'students', 'Added student: Alice Johnson'),
(1, 'CREATE', 'students', 'Added student: Bob Smith'),
(2, 'UPDATE', 'attendance', 'Updated attendance for student Bob Smith on 2026-05-14'),
(3, 'UPDATE', 'attendance', 'Marked my attendance: 2026-05-14'),
(1, 'VIEW', 'reports', 'Accessed daily attendance report for 2026-05-14'),
(2, 'VIEW', 'reports', 'Generated student performance report'),
(1, 'UPDATE', 'settings', 'Updated system settings');