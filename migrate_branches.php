<?php
require_once __DIR__ . '/api/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Create branches table
    $createBranchesQuery = "
        CREATE TABLE IF NOT EXISTS branches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            location VARCHAR(255) DEFAULT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_branch (company_id, name),
            INDEX idx_company (company_id),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ";
    
    $db->exec($createBranchesQuery);
    echo "Success: Created branches table.\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
