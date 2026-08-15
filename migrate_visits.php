<?php
$_SERVER['SERVER_NAME'] = 'localhost'; // Force remote DB connection for CLI
require_once __DIR__ . '/api/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Create visits table
    $createVisitsQuery = "
        CREATE TABLE IF NOT EXISTS visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            assignee_id INT NOT NULL,           -- The employee doing the visit
            co_assignee_id INT DEFAULT NULL,    -- Optional co-employee
            assigned_by INT NOT NULL,           -- Manager, Admin, or the Employee themselves
            customer_name VARCHAR(255) NOT NULL,
            address TEXT,                       -- Customer address
            visit_purpose TEXT,
            product VARCHAR(255),
            visit_date DATE NOT NULL,
            visit_time TIME NOT NULL,
            
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            
            -- Check-in Info
            checkin_time DATETIME DEFAULT NULL,
            checkin_lat DECIMAL(10,8) DEFAULT NULL,
            checkin_lng DECIMAL(11,8) DEFAULT NULL,
            checkin_selfie VARCHAR(255) DEFAULT NULL,
            
            -- Check-out Info
            checkout_time DATETIME DEFAULT NULL,
            checkout_lat DECIMAL(10,8) DEFAULT NULL,
            checkout_lng DECIMAL(11,8) DEFAULT NULL,
            checkout_selfie VARCHAR(255) DEFAULT NULL,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (co_assignee_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ";
    
    $db->exec($createVisitsQuery);
    echo "Success: Created visits table.\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
