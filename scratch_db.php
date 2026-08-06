<?php
require_once __DIR__ . '/api/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Alter leaves table
    $alterLeavesQuery = "
        ALTER TABLE leaves 
        MODIFY COLUMN status ENUM('pending', 'under_process', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
        ADD COLUMN approved_start_date DATE NULL AFTER end_date,
        ADD COLUMN approved_end_date DATE NULL AFTER approved_start_date,
        ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
    ";
    
    try {
        $db->exec($alterLeavesQuery);
        echo "Success: Altered leaves table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Leaves columns already exist.\n";
        } else {
            echo "Error altering leaves table: " . $e->getMessage() . "\n";
        }
    }

    // 2. Create company_leave_policies
    $db->exec("
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
    ");
    echo "Success: Created company_leave_policies table.\n";

    // 3. Create employee_leave_balances
    $db->exec("
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
    ");
    echo "Success: Created employee_leave_balances table.\n";

    // 4. Create company_holidays
    $db->exec("
        CREATE TABLE IF NOT EXISTS company_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            holiday_date DATE NOT NULL,
            name VARCHAR(100) NOT NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    echo "Success: Created company_holidays table.\n";

    // 5. Create leave_audit_logs
    $db->exec("
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
    ");
    echo "Success: Created leave_audit_logs table.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
