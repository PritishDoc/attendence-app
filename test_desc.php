<?php
require 'api/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE employee_documents');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
