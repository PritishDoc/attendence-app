<?php
require_once __DIR__ . '/api/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "NO_TABLES";
    } else {
        echo implode(", ", $tables);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
