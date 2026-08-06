<?php
require_once __DIR__ . '/api/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $query = "
        CREATE TABLE IF NOT EXISTS company_document_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            is_required TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_doc (company_id, name),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ";
    
    $db->exec($query);
    echo "Success: Created company_document_types table.\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

