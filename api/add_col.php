<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    echo json_encode($db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
