SET FOREIGN_KEY_CHECKS=0;\n\n-- ============================================
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
    department_id INT DEFAULT NULL,
    designation_id INT DEFAULT NULL,
    manager_id INT DEFAULT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    employee_id_code VARCHAR(50) DEFAULT NULL COMMENT 'Company-specific employee ID like EMP-001',
    device_uuid VARCHAR(255) DEFAULT NULL COMMENT 'Locked hardware device signature for PWA anti-buddy login protection',
    is_first_login TINYINT(1) DEFAULT 1 COMMENT 'Flag to force password change on first login',
    refresh_token_hash VARCHAR(255) DEFAULT NULL,
    previous_refresh_token_hash VARCHAR(255) DEFAULT NULL,
    grace_period_expires_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL DEFAULT NULL,
    token_version INT DEFAULT 1,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
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
    status ENUM('present','absent','late','half_day','leave','wfh','outdoor') DEFAULT 'present',
    total_hours DECIMAL(5,2) DEFAULT NULL COMMENT 'Auto-calculated on checkout',
    selfie_data LONGTEXT DEFAULT NULL COMMENT 'Base64 captured front-camera selfie on checkin',
    notes TEXT,
    source VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (employee_id, date),
    INDEX idx_company_date (company_id, date),
    INDEX idx_employee_date (employee_id, date),
    INDEX idx_status (status),
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 3.5. Leaves
-- ============================================
CREATE TABLE IF NOT EXISTS leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    company_id INT NOT NULL,
    leave_type ENUM('CL', 'SL', 'CO', 'LOP', 'EL', 'ML') NOT NULL,
    leave_duration ENUM('full_day', 'half_day_start', 'half_day_end') DEFAULT 'full_day',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    approved_start_date DATE NULL,
    approved_end_date DATE NULL,
    reason TEXT,
    status ENUM('pending', 'under_process', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_leave_employee ON leaves(employee_id, start_date, end_date);

-- ============================================
-- 3.6. Leave Policies & Balances
-- ============================================
CREATE TABLE IF NOT EXISTS company_leave_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    leave_type ENUM('CL', 'SL', 'CO', 'LOP', 'EL', 'ML') NOT NULL,
    leave_year INT NOT NULL COMMENT 'The year these policies apply to',
    allocated_days DECIMAL(5,1) NOT NULL DEFAULT 0,
    is_paid BOOLEAN DEFAULT TRUE COMMENT 'Whether the leave type is paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_company_leave (company_id, leave_type, leave_year),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    company_id INT NOT NULL,
    leave_type ENUM('CL', 'SL', 'CO', 'LOP', 'EL', 'ML') NOT NULL,
    leave_year INT NOT NULL,
    allocated_days DECIMAL(5,1) NOT NULL DEFAULT 0,
    used_days DECIMAL(5,1) NOT NULL DEFAULT 0,
    remaining_days DECIMAL(5,1) NOT NULL DEFAULT 0,
    UNIQUE KEY unique_employee_balance (employee_id, leave_type, leave_year),
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS company_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leave_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_id INT NOT NULL,
    action ENUM('created', 'updated', 'approved', 'rejected', 'cancelled', 'deleted') NOT NULL,
    actor_id INT NOT NULL COMMENT 'User ID of who performed the action',
    changes JSON NULL COMMENT 'Details of what changed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_id) REFERENCES leaves(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
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

-- ============================================\n-- 4.5. Designations\n-- ============================================\nCREATE TABLE IF NOT EXISTS designations (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    company_id INT NOT NULL,\n    name VARCHAR(100) NOT NULL,\n    description TEXT DEFAULT NULL,\n    status ENUM('active','inactive') DEFAULT 'active',\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n    UNIQUE KEY unique_designation (company_id, name),\n    INDEX idx_company (company_id),\n    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE\n) ENGINE=InnoDB;\n\n-- ============================================
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

-- ============================================\n-- 9. Payroll\n-- ============================================\nCREATE TABLE IF NOT EXISTS payroll_salary_structures (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    employee_id INT NOT NULL UNIQUE,\n    company_id INT NOT NULL,\n    base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n    effective_date DATE NOT NULL,\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,\n    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE\n) ENGINE=InnoDB;\n\nCREATE TABLE IF NOT EXISTS payroll_allowances (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    structure_id INT NOT NULL,\n    name VARCHAR(100) NOT NULL,\n    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n    type ENUM('fixed', 'percentage') DEFAULT 'fixed',\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    FOREIGN KEY (structure_id) REFERENCES payroll_salary_structures(id) ON DELETE CASCADE\n) ENGINE=InnoDB;\n\nCREATE TABLE IF NOT EXISTS payroll_deductions (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    structure_id INT NOT NULL,\n    name VARCHAR(100) NOT NULL,\n    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n    type ENUM('fixed', 'percentage') DEFAULT 'fixed',\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    FOREIGN KEY (structure_id) REFERENCES payroll_salary_structures(id) ON DELETE CASCADE\n) ENGINE=InnoDB;\n\nCREATE TABLE IF NOT EXISTS payroll_payslips (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    employee_id INT NOT NULL,\n    company_id INT NOT NULL,\n    month TINYINT NOT NULL,\n    year YEAR NOT NULL,\n    basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n    total_allowances DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n    total_deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n    net_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n    status ENUM('draft', 'generated', 'paid') DEFAULT 'draft',\n    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    UNIQUE KEY unique_payslip (employee_id, month, year),\n    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,\n    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE\n) ENGINE=InnoDB;\n\n-- ============================================
-- 5.5. Designations
-- ============================================
CREATE TABLE IF NOT EXISTS designations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_designation (company_id, name),
    INDEX idx_company (company_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Add Foreign Keys to Users
-- ============================================
ALTER TABLE users 
    ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_users_designation FOREIGN KEY (designation_id) REFERENCES designations(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_users_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- ============================================
-- 9. Payroll
-- ============================================
CREATE TABLE IF NOT EXISTS salary_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    company_id INT NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_frequency ENUM('monthly', 'weekly', 'biweekly') DEFAULT 'monthly',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salary_allowances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salary_structure_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salary_structure_id) REFERENCES salary_structures(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salary_deductions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salary_structure_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salary_structure_id) REFERENCES salary_structures(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    company_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    basic_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_allowances DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft', 'generated', 'paid') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payslip (employee_id, month, year),
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
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

-- Demo Departments\nINSERT INTO departments (company_id, name, description) VALUES\n(1, 'Engineering', 'Software development team'),\n(1, 'Sales', 'Sales and business development'),\n(1, 'HR', 'Human resources and administration'),\n(1, 'Field Staff', 'Field operations team'),\n(1, 'Management', 'Company Management');\n\n-- Demo Designations\nINSERT INTO designations (company_id, name, description) VALUES\n(1, 'Director', 'Company Director'),\n(1, 'Senior Developer', 'Senior Software Developer'),\n(1, 'Sales Executive', 'Sales Executive'),\n(1, 'Field Officer', 'Field Officer');\n\n-- Demo Departments
INSERT INTO departments (company_id, name, description) VALUES
(1, 'Management', 'Management team'),
(1, 'Engineering', 'Software development team'),
(1, 'Sales', 'Sales and business development'),
(1, 'HR', 'Human resources and administration'),
(1, 'Field Staff', 'Field operations team');

-- Demo Designations
INSERT INTO designations (company_id, name, description) VALUES
(1, 'Director', 'Company Director'),
(1, 'Senior Developer', 'Senior Software Developer'),
(1, 'Sales Executive', 'Sales Executive'),
(1, 'Field Officer', 'Field Officer');

-- Demo Company Admin
-- Password: Company@123
INSERT INTO users (company_id, name, email, password_hash, role, department_id, designation_id, status) VALUES
(1, 'Rahul Sharma', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'company_admin', 1, 1, 'active');

-- Demo Employees
-- Password: Employee@123
INSERT INTO users (company_id, name, email, phone, password_hash, role, department_id, designation_id, manager_id, employee_id_code, status) VALUES
(1, 'Priya Patel', 'priya@demo.com', '+91-9876543211', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 2, 2, 2, 'EMP-001', 'active'),
(1, 'Amit Kumar', 'amit@demo.com', '+91-9876543212', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 3, 3, 2, 'EMP-002', 'active'),
(1, 'Sneha Gupta', 'sneha@demo.com', '+91-9876543213', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 5, 4, 2, 'EMP-003', 'active');

-- Demo Attendance (today)
INSERT INTO attendance (employee_id, company_id, date, checkin_time, checkout_time, checkin_latitude, checkin_longitude, attendance_type, status, total_hours) VALUES
(3, 1, CURDATE(), CONCAT(CURDATE(), ' 08:55:00'), NULL, 28.61390000, 77.20900000, 'office', 'present', NULL),
(4, 1, CURDATE(), CONCAT(CURDATE(), ' 09:20:00'), NULL, 28.61500000, 77.21000000, 'office', 'late', NULL),
(5, 1, CURDATE(), CONCAT(CURDATE(), ' 09:00:00'), NULL, 28.62000000, 77.22000000, 'field', 'present', NULL);

--
-- Table structure for table `attendance_requests`
--

CREATE TABLE `attendance_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `request_type` enum('wfh','outdoor','time_correction','status_correction') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `original_time_in` datetime DEFAULT NULL,
  `original_time_out` datetime DEFAULT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `corrected_status` enum('present','absent','half_day') DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `applied_data` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `attendance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_att_req_emp_dates` (`employee_id`,`start_date`,`end_date`),
  ADD KEY `idx_att_req_dup_check` (`employee_id`,`request_type`,`status`),
  ADD KEY `idx_att_req_status` (`company_id`,`status`),
  ADD KEY `idx_att_req_history` (`employee_id`,`start_date`,`status`,`request_type`);

ALTER TABLE `attendance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `attendance_requests`
  ADD CONSTRAINT `attendance_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_requests_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
