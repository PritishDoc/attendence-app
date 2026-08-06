<?php
require_once __DIR__ . '/api/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Connected to database successfully.\n";

    // 1. Create designations table
    $sql1 = "
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
    ";
    $db->exec($sql1);
    echo "1. Designations table created or verified.\n";

    // 2. Check if column exists before adding to avoid errors on multiple runs
    $checkCol = $db->query("SHOW COLUMNS FROM users LIKE 'designation_id'");
    if ($checkCol->rowCount() == 0) {
        $sql2 = "ALTER TABLE users ADD COLUMN designation_id INT DEFAULT NULL AFTER department_id;";
        $db->exec($sql2);
        echo "2. Added designation_id column to users table.\n";
        
        $sql3 = "ALTER TABLE users ADD CONSTRAINT fk_users_designation FOREIGN KEY (designation_id) REFERENCES designations(id) ON DELETE SET NULL;";
        $db->exec($sql3);
        echo "3. Added foreign key constraint to users table.\n";
    } else {
        echo "2. Column designation_id already exists in users table.\n";
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
