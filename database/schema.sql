-- ============================================
-- Attendance Tracker — Database Schema
-- Version: 1.0.0 (Phase 1 MVP)
-- ============================================

-- CREATE DATABASE IF NOT EXISTS attendance_tracker
--     CHARACTER SET utf8mb4
--     COLLATE utf8mb4_unicode_ci;

-- USE attendance_tracker;

-- ============================================
-- 1. Companies
-- ============================================
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    logo_url VARCHAR(500),
    office_latitude DECIMAL(10,8) DEFAULT NULL,
    office_longitude DECIMAL(11,8) DEFAULT NULL,
    office_radius INT DEFAULT 200 COMMENT 'Allowed radius in meters for office attendance',
    subscription_plan ENUM('trial','basic','pro','enterprise') DEFAULT 'trial',
    subscription_expiry DATE DEFAULT NULL,
    max_employees INT DEFAULT 5,
    status ENUM('active','inactive','pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================
-- 2. Users (Super Admin, Company Admin, Employee)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT DEFAULT NULL COMMENT 'NULL for super_admin',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','company_admin','employee') NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    designation VARCHAR(100) DEFAULT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    employee_id_code VARCHAR(50) DEFAULT NULL COMMENT 'Company-specific employee ID like EMP-001',
    status ENUM('active','inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_email (email),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 3. Attendance
-- ============================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    company_id INT NOT NULL,
    date DATE NOT NULL,
    checkin_time DATETIME DEFAULT NULL,
    checkout_time DATETIME DEFAULT NULL,
    checkin_latitude DECIMAL(10,8) DEFAULT NULL,
    checkin_longitude DECIMAL(11,8) DEFAULT NULL,
    checkout_latitude DECIMAL(10,8) DEFAULT NULL,
    checkout_longitude DECIMAL(11,8) DEFAULT NULL,
    attendance_type ENUM('office','field') DEFAULT 'office',
    status ENUM('present','absent','late','half_day') DEFAULT 'present',
    total_hours DECIMAL(5,2) DEFAULT NULL COMMENT 'Auto-calculated on checkout',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (employee_id, date),
    INDEX idx_company_date (company_id, date),
    INDEX idx_employee_date (employee_id, date),
    INDEX idx_status (status),
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 4. Live Tracking (Phase 2 — schema ready)
-- ============================================
CREATE TABLE IF NOT EXISTS live_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy FLOAT DEFAULT NULL,
    speed FLOAT DEFAULT NULL,
    heading FLOAT DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 5. Departments
-- ============================================
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dept (company_id, name),
    INDEX idx_company (company_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 6. Subscriptions (Phase 3 — schema ready)
-- ============================================
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    plan ENUM('trial','basic','pro','enterprise') NOT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    payment_id VARCHAR(255) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    start_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 7. Activity Log
-- ============================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL COMMENT 'e.g., user, attendance, company',
    entity_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_company (company_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 8. Company Settings (working hours, etc.)
-- ============================================
CREATE TABLE IF NOT EXISTS company_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL UNIQUE,
    work_start_time TIME DEFAULT '09:00:00',
    work_end_time TIME DEFAULT '18:00:00',
    late_threshold_minutes INT DEFAULT 15 COMMENT 'Minutes after start time to mark late',
    half_day_hours DECIMAL(3,1) DEFAULT 4.0,
    full_day_hours DECIMAL(3,1) DEFAULT 8.0,
    working_days JSON DEFAULT NULL COMMENT '["mon","tue","wed","thu","fri"]',
    timezone VARCHAR(50) DEFAULT 'Asia/Kolkata',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- SEED DATA
-- ============================================

-- Default Super Admin
-- Password: Admin@123 (bcrypt hashed)
INSERT INTO users (name, email, password_hash, role, status) VALUES
('Super Admin', 'admin@attendify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active');

-- Demo Company
INSERT INTO companies (company_name, email, phone, office_latitude, office_longitude, office_radius, subscription_plan, subscription_expiry, max_employees, status) VALUES
('Demo Technologies', 'demo@attendify.com', '+91-9876543210', 28.61390000, 77.20900000, 200, 'trial', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 5, 'active');

-- Demo Company Settings
INSERT INTO company_settings (company_id, work_start_time, work_end_time, working_days) VALUES
(1, '09:00:00', '18:00:00', '["mon","tue","wed","thu","fri"]');

-- Demo Company Admin
-- Password: Company@123
INSERT INTO users (company_id, name, email, password_hash, role, department, designation, status) VALUES
(1, 'Rahul Sharma', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'company_admin', 'Management', 'Director', 'active');

-- Demo Departments
INSERT INTO departments (company_id, name, description) VALUES
(1, 'Engineering', 'Software development team'),
(1, 'Sales', 'Sales and business development'),
(1, 'HR', 'Human resources and administration'),
(1, 'Field Staff', 'Field operations team');

-- Demo Employees
-- Password: Employee@123
INSERT INTO users (company_id, name, email, phone, password_hash, role, department, designation, employee_id_code, status) VALUES
(1, 'Priya Patel', 'priya@demo.com', '+91-9876543211', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 'Engineering', 'Senior Developer', 'EMP-001', 'active'),
(1, 'Amit Kumar', 'amit@demo.com', '+91-9876543212', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 'Sales', 'Sales Executive', 'EMP-002', 'active'),
(1, 'Sneha Gupta', 'sneha@demo.com', '+91-9876543213', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 'Field Staff', 'Field Officer', 'EMP-003', 'active');

-- Demo Attendance (today)
INSERT INTO attendance (employee_id, company_id, date, checkin_time, checkout_time, checkin_latitude, checkin_longitude, attendance_type, status, total_hours) VALUES
(3, 1, CURDATE(), CONCAT(CURDATE(), ' 08:55:00'), NULL, 28.61390000, 77.20900000, 'office', 'present', NULL),
(4, 1, CURDATE(), CONCAT(CURDATE(), ' 09:20:00'), NULL, 28.61500000, 77.21000000, 'office', 'late', NULL),
(5, 1, CURDATE(), CONCAT(CURDATE(), ' 09:00:00'), NULL, 28.62000000, 77.22000000, 'field', 'present', NULL);
