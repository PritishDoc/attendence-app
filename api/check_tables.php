<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW TABLES LIKE '%shift%'");
    echo "Shifts:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    $stmt2 = $db->query("SHOW TABLES LIKE '%weekoff%'");
    echo "Weekoffs:\n";
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
