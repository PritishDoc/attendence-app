<?php
$startDate = '2026-08-01';
$endDate = '2026-08-31';
$todayStr = '2026-08-14';
$current = new DateTime($startDate);
$last = new DateTime($endDate);
$leaveMap = ['2026-08-25' => ['leave_type' => 'SL']];

while ($current <= $last) {
    $dateStr = $current->format('Y-m-d');
    
    if ($dateStr <= $todayStr) {
        $badge = 'A';
    }
    
    if (isset($leaveMap[$dateStr])) {
        $badge = $leaveMap[$dateStr]['leave_type'];
    }
    
    if ($dateStr > $todayStr && $badge === 'A') {
        $badge = null;
    }
    
    echo "$dateStr: $badge\n";
    
    $current->modify('+1 day');
}
