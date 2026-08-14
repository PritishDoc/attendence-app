<?php
require_once __DIR__ . '/api/config/database.php';
$_SERVER['SERVER_NAME'] = '192.168.1.100'; // Mock LAN IP so it uses localhost database
$db = Database::getInstance()->getConnection();
print_r($db->query('DESCRIBE employee_addresses')->fetchAll(PDO::FETCH_ASSOC));
