<?php
require_once __DIR__ . '/api/config/Database.php';

$db = Database::getInstance()->getConnection();

echo "Starting Phase 3 Database Migration...\n\n";

try {
    // 1. Files & File Access Logs
    $sql_files = "
    CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL UNIQUE,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        uploaded_by INT NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(512) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size INT NOT NULL,
        is_sensitive BOOLEAN DEFAULT 1,
        status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
        version_number INT DEFAULT 1,
        parent_file_uuid VARCHAR(36) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (parent_file_uuid, version_number),
        INDEX (company_id, deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_files);
    echo "Files table created.\n";

    $sql_file_access_logs = "
    CREATE TABLE IF NOT EXISTS file_access_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NOT NULL,
        accessed_by INT NOT NULL,
        accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (file_id),
        INDEX (accessed_by),
        INDEX (accessed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_file_access_logs);
    echo "File access logs table created.\n";

    // 2. Profile Tables
    $sql_addresses = "
    CREATE TABLE IF NOT EXISTS employee_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        house_no VARCHAR(255),
        landmark VARCHAR(255),
        area VARCHAR(255),
        country_id INT,
        state_id INT,
        city_id INT,
        zip_code VARCHAR(20),
        address_type ENUM('permanent', 'temporary') NOT NULL,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_addresses);
    echo "Employee Addresses table created.\n";

    $sql_experience = "
    CREATE TABLE IF NOT EXISTS employee_experience (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        organization VARCHAR(255) NOT NULL,
        designation VARCHAR(255) NOT NULL,
        from_date DATE NOT NULL,
        to_date DATE,
        responsibility TEXT,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_experience);
    echo "Employee Experience table created.\n";

    $sql_education = "
    CREATE TABLE IF NOT EXISTS employee_education (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        qualification VARCHAR(255) NOT NULL,
        year_of_passing INT NOT NULL,
        grade VARCHAR(50),
        percentage DECIMAL(5,2),
        institute VARCHAR(255),
        university_board VARCHAR(255),
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_education);
    echo "Employee Education table created.\n";

    $sql_family = "
    CREATE TABLE IF NOT EXISTS employee_family (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        dob DATE,
        phone VARCHAR(20),
        relation VARCHAR(100),
        gender ENUM('Male', 'Female', 'Other'),
        aadhaar_no_enc TEXT,
        aadhaar_iv VARCHAR(255),
        aadhaar_last4 VARCHAR(4),
        aadhaar_front_file_uuid VARCHAR(36),
        aadhaar_back_file_uuid VARCHAR(36),
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_family);
    echo "Employee Family table created.\n";

    // 3. Modify Users Table
    // Check if columns exist before adding (basic check to prevent errors on re-run)
    $columns_to_add = [
        'manager_id' => 'INT NULL',
        'org_path' => 'VARCHAR(512) NULL',
        'branch_id' => 'INT NULL',
        'department_id' => 'INT NULL',
        'shift_id' => 'INT NULL',
        'weekoff_policy_id' => 'INT NULL',
        'employee_code' => 'VARCHAR(50) NULL',
        'dob' => 'DATE NULL',
        'joining_date' => 'DATE NULL',
        'aadhaar_no_enc' => 'TEXT NULL',
        'aadhaar_iv' => 'VARCHAR(255) NULL',
        'aadhaar_last4' => 'VARCHAR(4) NULL',
        'pan_no_enc' => 'TEXT NULL',
        'pan_iv' => 'VARCHAR(255) NULL',
        'pan_last4' => 'VARCHAR(4) NULL',
        'esic_no_enc' => 'TEXT NULL',
        'esic_iv' => 'VARCHAR(255) NULL',
        'pf_no_enc' => 'TEXT NULL',
        'pf_iv' => 'VARCHAR(255) NULL',
        'bank_name_enc' => 'TEXT NULL',
        'bank_name_iv' => 'VARCHAR(255) NULL',
        'ifsc_code_enc' => 'TEXT NULL',
        'ifsc_code_iv' => 'VARCHAR(255) NULL'
    ];

    foreach ($columns_to_add as $col => $def) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN $col $def");
            echo "Added $col to users table.\n";
        } catch (PDOException $e) {
            if ($e->getCode() == '42S21') { // Duplicate column
                echo "Column $col already exists in users table, skipping.\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Add org_path index to users if not exists
    try {
        $db->exec("CREATE INDEX idx_users_org_path ON users (org_path)");
        echo "Created index on users.org_path.\n";
    } catch (PDOException $e) {
        // Ignore duplicate key error
    }

    // 4. Documents & History
    $sql_documents = "
    CREATE TABLE IF NOT EXISTS employee_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        document_file_uuid VARCHAR(36) NOT NULL,
        version_number INT DEFAULT 1,
        is_active BOOLEAN DEFAULT 1,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        UNIQUE (employee_id, document_name, is_active, deleted_at),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_documents);
    echo "Employee Documents table created.\n";

    $sql_shift_history = "
    CREATE TABLE IF NOT EXISTS employee_shift_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        shift_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE (company_id, uuid),
        INDEX (company_id, employee_id),
        INDEX (employee_id, start_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_shift_history);
    echo "Employee Shift History table created.\n";

    $sql_policy_history = "
    CREATE TABLE IF NOT EXISTS employee_policy_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        weekoff_policy_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE (company_id, uuid),
        INDEX (company_id, employee_id),
        INDEX (employee_id, start_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_policy_history);
    echo "Employee Policy History table created.\n";

    // 5. Finance
    $sql_expenses = "
    CREATE TABLE IF NOT EXISTS employee_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        expense_date DATE NOT NULL,
        expense_type VARCHAR(255) NOT NULL,
        expense_category VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        attachment_file_uuid VARCHAR(36),
        status ENUM('pending', 'manager_approved', 'finance_approved', 'paid') DEFAULT 'pending',
        approved_by INT,
        approved_at TIMESTAMP NULL,
        paid_by INT,
        paid_at TIMESTAMP NULL,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_expenses);
    echo "Employee Expenses table created.\n";

    $sql_expense_status_logs = "
    CREATE TABLE IF NOT EXISTS expense_status_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_id INT NOT NULL,
        status_from VARCHAR(50),
        status_to VARCHAR(50) NOT NULL,
        changed_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (expense_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_expense_status_logs);
    echo "Expense Status Logs table created.\n";

    $sql_advances = "
    CREATE TABLE IF NOT EXISTS employee_advances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        remark TEXT,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_advances);
    echo "Employee Advances table created.\n";

    $sql_incentives = "
    CREATE TABLE IF NOT EXISTS employee_incentives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reason TEXT,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_incentives);
    echo "Employee Incentives table created.\n";

    // 6. Offboarding
    $sql_resignations = "
    CREATE TABLE IF NOT EXISTS employee_resignations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        reason TEXT,
        notice_period_days INT DEFAULT 0,
        preferred_lwd DATE,
        attachment_file_uuid VARCHAR(36),
        status ENUM('pending', 'manager_approved', 'rejected', 'completed') DEFAULT 'pending',
        approved_by INT,
        rejection_reason TEXT,
        final_lwd DATE,
        exit_interview_notes TEXT,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        UNIQUE (company_id, uuid),
        INDEX (company_id, deleted_at),
        INDEX (company_id, employee_id),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_resignations);
    echo "Employee Resignations table created.\n";

    $sql_resignation_status_logs = "
    CREATE TABLE IF NOT EXISTS resignation_status_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resignation_id INT NOT NULL,
        status_from VARCHAR(50),
        status_to VARCHAR(50) NOT NULL,
        changed_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (resignation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_resignation_status_logs);
    echo "Resignation Status Logs table created.\n";

    // 7. System Audit Logs
    $sql_audit_logs = "
    CREATE TABLE IF NOT EXISTS system_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(100) NOT NULL,
        entity_id INT NOT NULL,
        old_data JSON,
        new_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (company_id),
        INDEX (user_id),
        INDEX (entity_type, entity_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_audit_logs);
    echo "System Audit Logs table created.\n";

    echo "\nPhase 3 Database Migration Completed Successfully!\n";

} catch (PDOException $e) {
    echo "\nError during migration: " . $e->getMessage() . "\n";
}
