<?php
require_once __DIR__ . '/api/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Read the schema.sql file
    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    
    // Execute the SQL statements
    $db->exec($sql);
    
    echo "SUCCESS_SCHEMA_IMPORTED";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
