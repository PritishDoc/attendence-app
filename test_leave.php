<?php
$leaves = [
    ['start_date' => '2026-08-25', 'end_date' => '2026-08-25', 'leave_type' => 'SL'],
    ['start_date' => '2026-08-28', 'end_date' => '2026-08-28', 'leave_type' => 'CL']
];
$startDate = '2026-08-01';
$endDate = '2026-08-31';
$leaveMap = [];
foreach ($leaves as $leave) {
    $currDate = max($leave['start_date'], $startDate);
    $lastDate = min($leave['end_date'], $endDate);
    $current = new DateTime($currDate);
    $last = new DateTime($lastDate);
    while ($current <= $last) {
        $leaveMap[$current->format('Y-m-d')] = $leave;
        $current->modify('+1 day');
    }
}
print_r(array_keys($leaveMap));
