<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/helpers/response.php';
require_once __DIR__ . '/api/controllers/ProfileController.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query('SELECT * FROM users WHERE role = "employee" LIMIT 1');
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "No employee found.\n";
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM employee_addresses WHERE employee_id = :employee_id AND company_id = :company_id AND deleted_at IS NULL");
    $stmt->execute([':employee_id' => $user['id'], ':company_id' => $user['company_id']]);
    echo "Addresses fetched successfully:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error fetching addresses: " . $e->getMessage() . "\n";
}

try {
    $stmt = $db->prepare("SELECT * FROM employee_family WHERE employee_id = :employee_id AND company_id = :company_id AND deleted_at IS NULL");
    $stmt->execute([':employee_id' => $user['id'], ':company_id' => $user['company_id']]);
    echo "Family fetched successfully:\n";
    $family = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($family as &$f) {
        unset($f['aadhaar_no_enc']);
        unset($f['aadhaar_iv']);
    }
    print_r($family);
} catch (Exception $e) {
    echo "Error fetching family: " . $e->getMessage() . "\n";
}
