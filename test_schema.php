<?php
require 'api/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE files');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
