<?php
require 'api/config/Database.php';

$db = Database::getInstance()->getConnection();
$employeeId = 14;
$startDate = '2026-08-01';
$endDate = '2026-08-31';

$leaveStmt = $db->prepare("SELECT * FROM leaves WHERE employee_id = ? AND status = 'approved' AND start_date <= ? AND end_date >= ?");
$leaveStmt->execute([$employeeId, $endDate, $startDate]);
$leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);
echo "Fetched Leaves: " . count($leaves) . "\n";
foreach ($leaves as $leave) {
    echo "Leave: " . $leave['start_date'] . " to " . $leave['end_date'] . "\n";
}
