<?php
/**
 * Attendance Controller — Check-in/out, reports
 */

class AttendanceController {

    public static function checkin(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);

        $body = getRequestBody();
        $lat = $body['latitude'] ?? null;
        $lng = $body['longitude'] ?? null;
        $type = $body['attendance_type'] ?? 'office';

        $attendanceModel = new Attendance();
        $existing = $attendanceModel->findTodayByEmployee($auth['user_id']);
        if ($existing) Response::error('Already checked in today', 409);

        // 🚨 Prevent Check-in on Approved Leave Day
        $db = Database::getInstance()->getConnection();
        $todayStr = date('Y-m-d');
        $leaveStmt = $db->prepare("SELECT * FROM leaves WHERE employee_id = ? AND status = 'approved' AND start_date <= ? AND end_date >= ?");
        $leaveStmt->execute([$auth['user_id'], $todayStr, $todayStr]);
        if ($leaveStmt->fetch()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'blocked' => true,
                'type' => 'leave',
                'message' => 'Cannot check in on an approved leave day'
            ]);
            exit;
        }

        // Fetch active WFH/Outdoor requests
        $reqStmt = $db->prepare("
            SELECT id, request_type, status FROM attendance_requests 
            WHERE employee_id = ? 
            AND request_type IN ('wfh', 'outdoor') 
            AND status IN ('pending', 'approved')
            AND deleted_at IS NULL
            AND (start_date <= ? AND COALESCE(end_date, start_date) >= ?)
        ");
        $reqStmt->execute([$auth['user_id'], $todayStr, $todayStr]);
        $activeReq = $reqStmt->fetch();
        
        $warningMessage = null;
        
        // 🚨 Require Approved Request for WFH/Outdoor check-ins
        if ($type === 'wfh' || $type === 'outdoor') {
            if (!$activeReq || $activeReq['status'] !== 'approved' || $activeReq['request_type'] !== $type) {
                $typeName = strtoupper($type);
                Response::error("You do not have an approved $typeName request for today. Please login from the office.", 403);
            }
        } else {
            // If they are checking in as office, warn them if they have a pending request
            if ($activeReq && $activeReq['status'] === 'pending') {
                $warningMessage = "You have a pending " . $activeReq['request_type'] . " request for today.";
            }
        }

        $status = 'present';

        // Office attendance — verify GPS radius
        if ($type === 'office') {
            if (!$lat || !$lng) {
                Response::error("Location data is required for office check-ins.", 400);
            }
            
            $companyModel = new Company();
            $company = $companyModel->findById($auth['company_id']);
            if ($company['office_latitude'] && $company['office_longitude']) {
                $distance = Attendance::calculateDistance($lat, $lng, (float)$company['office_latitude'], (float)$company['office_longitude']);
                if ($distance > $company['office_radius']) {
                    Response::error("You are {$distance}m away from office. Must be within {$company['office_radius']}m.", 403);
                }
            }
        }

        // Check if late
        $db = Database::getInstance()->getConnection();
        $settingsStmt = $db->prepare("SELECT * FROM company_settings WHERE company_id = ?");
        $settingsStmt->execute([$auth['company_id']]);
        $settings = $settingsStmt->fetch();

        if ($settings) {
            $workStart = strtotime($settings['work_start_time']);
            $now = strtotime(date('H:i:s'));
            $lateThreshold = $settings['late_threshold_minutes'] ?? LATE_THRESHOLD_MINUTES;
            if ($now > $workStart + ($lateThreshold * 60)) {
                $status = 'late';
            }
        }

        $id = $attendanceModel->checkin([
            'employee_id' => $auth['user_id'],
            'company_id'  => $auth['company_id'],
            'latitude'    => $lat,
            'longitude'   => $lng,
            'attendance_type' => $type,
            'status'      => $status,
            'selfie_data' => $body['selfie_data'] ?? null
        ]);

        $responsePayload = [
            'attendance_id' => $id,
            'status' => $status,
            'checkin_time' => date('Y-m-d H:i:s')
        ];
        
        if ($warningMessage) {
            $responsePayload['warning'] = $warningMessage;
        }

        Response::success($responsePayload, $status === 'late' ? 'Checked in (Late)' : 'Checked in successfully', 201);
    }

    public static function checkout(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);

        $body = getRequestBody();
        $attendanceModel = new Attendance();
        $today = $attendanceModel->findTodayByEmployee($auth['user_id']);
        if (!$today) Response::error('No check-in found for today', 404);
        if ($today['checkout_time']) Response::error('Already checked out', 409);

        $lat = $body['latitude'] ?? null;
        $lng = $body['longitude'] ?? null;
        if ($lat && $lng) {
            $attendanceModel->checkoutWithLocation($today['id'], $lat, $lng);
        } else {
            $attendanceModel->checkout($today['id']);
        }

        $updated = $attendanceModel->findById($today['id']);
        Response::success([
            'checkout_time' => $updated['checkout_time'],
            'total_hours' => $updated['total_hours']
        ], 'Checked out successfully');
    }

    public static function today(): void {
        $auth = authenticate();
        $companyId = ($auth['role'] === ROLE_EMPLOYEE) ? $auth['company_id'] : ($_GET['company_id'] ?? $auth['company_id']);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $companyId);
        $attendanceModel = new Attendance();
        $records = $attendanceModel->getByCompanyAndDate($companyId, date('Y-m-d'), [
            'department' => $_GET['department'] ?? null,
            'status' => $_GET['status'] ?? null
        ]);
        Response::success($records);
    }

    public static function history(): void {
        $auth = authenticate();
        $employeeId = ($auth['role'] === ROLE_EMPLOYEE) ? $auth['user_id'] : ($_GET['employee_id'] ?? null);
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $attendanceModel = new Attendance();
        $records = $attendanceModel->getHistory($employeeId, $startDate, $endDate);
        Response::success($records);
    }

    public static function report(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        $attendanceModel = new Attendance();
        $report = $attendanceModel->getMonthlyReport($companyId, $year, $month);
        Response::success($report);
    }

    public static function status(): void {
        $auth = authenticate();
        $attendanceModel = new Attendance();
        $today = $attendanceModel->findTodayByEmployee($auth['user_id']);
        Response::success([
            'checked_in'  => $today ? true : false,
            'checked_out' => $today && $today['checkout_time'] ? true : false,
            'record'      => $today
        ]);
    }

    private static function getEmployeeHistoryWithLeaves(int $employeeId, string $startDate, string $endDate): array {
        $db = Database::getInstance()->getConnection();
        
        // 1. Fetch Leaves
        $leaveStmt = $db->prepare("SELECT * FROM leaves WHERE employee_id = ? AND status = 'approved' AND start_date <= ? AND end_date >= ?");
        $leaveStmt->execute([$employeeId, $endDate, $startDate]);
        $leaves = $leaveStmt->fetchAll();
        
        // 2. Fetch Attendance
        $attStmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ?");
        $attStmt->execute([$employeeId, $startDate, $endDate]);
        $attendances = $attStmt->fetchAll();
        
        // Map attendance by date
        $attMap = [];
        foreach ($attendances as $att) {
            $attMap[$att['date']] = $att;
        }
        
        // Map leaves by date
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
        
        $history = [];
        $current = new DateTime($startDate);
        $last = new DateTime($endDate);
        
        while ($current <= $last) {
            $dateStr = $current->format('Y-m-d');
            
            $record = [
                'date' => $dateStr,
                'status' => 'absent',
                'leave_type' => null,
                'leave_duration' => null,
                'attendance_data' => null
            ];
            
            if (isset($leaveMap[$dateStr])) {
                $record['status'] = 'leave';
                $record['leave_type'] = $leaveMap[$dateStr]['leave_type'];
                $record['leave_duration'] = $leaveMap[$dateStr]['leave_duration'];
                // optionally attach attendance data if they checked in anyway
                if (isset($attMap[$dateStr])) {
                    $record['attendance_data'] = $attMap[$dateStr];
                }
            } else if (isset($attMap[$dateStr])) {
                $record['status'] = $attMap[$dateStr]['status'];
                $record['attendance_data'] = $attMap[$dateStr];
            }
            
            $history[] = $record;
            $current->modify('+1 day');
        }
        
        return $history;
    }

    public static function myDailyHistory(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $history = self::getEmployeeHistoryWithLeaves($auth['user_id'], $date, $date);
        $record = $history[0];
        
        $db = Database::getInstance()->getConnection();
        $locStmt = $db->prepare("SELECT latitude, longitude, timestamp FROM live_tracking WHERE employee_id = ? AND DATE(timestamp) = ? ORDER BY timestamp ASC");
        $locStmt->execute([$auth['user_id'], $date]);
        $locations = $locStmt->fetchAll();
        
        if ($record['status'] === 'outdoor' && count($locations) < 2) {
            $record['outdoor_warning'] = 'Insufficient tracking data for outdoor duty.';
        }
        
        $record['locations'] = $locations;
        Response::success($record);
    }

    public static function myWeeklyHistory(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-6 days'));
        
        $history = self::getEmployeeHistoryWithLeaves($auth['user_id'], $startDate, $endDate);
        Response::success($history);
    }

    public static function myMonthlyHistory(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $history = self::getEmployeeHistoryWithLeaves($auth['user_id'], $startDate, $endDate);
        Response::success($history);
    }

    public static function myMonthlyHours(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT SUM(total_hours) as total FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ?");
        $stmt->execute([$auth['user_id'], $startDate, $endDate]);
        $result = $stmt->fetch();
        
        Response::success(['total_working_hours' => $result['total'] ?? 0]);
    }

    public static function exportMySummary(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $history = self::getEmployeeHistoryWithLeaves($auth['user_id'], $startDate, $endDate);
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT SUM(total_hours) as total FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ?");
        $stmt->execute([$auth['user_id'], $startDate, $endDate]);
        $hoursRes = $stmt->fetch();
        $totalHours = $hoursRes['total'] ?? 0;
        
        // Aggregate leaves
        $leaveCounts = ['CL'=>0, 'SL'=>0, 'CO'=>0, 'LOP'=>0, 'EL'=>0, 'ML'=>0];
        
        foreach ($history as $day) {
            if ($day['status'] === 'leave' && isset($day['leave_type'])) {
                $type = $day['leave_type'];
                $dur = $day['leave_duration'] ?? 'full_day';
                $val = ($dur === 'full_day') ? 1 : 0.5;
                if (isset($leaveCounts[$type])) {
                    $leaveCounts[$type] += $val;
                }
            }
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="Attendance_Summary_' . $year . '_' . $month . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Attendance Summary', "$year-$month"]);
        fputcsv($output, ['Total Working Hours', $totalHours]);
        fputcsv($output, []);
        
        fputcsv($output, ['Leave Balances/Counts']);
        foreach ($leaveCounts as $type => $count) {
            fputcsv($output, [$type, $count]);
        }
        fputcsv($output, []);
        
        fputcsv($output, ['Date', 'Status', 'Leave Type', 'Duration', 'Check-in', 'Check-out', 'Hours']);
        
        foreach ($history as $day) {
            $checkin = $day['attendance_data']['checkin_time'] ?? '';
            $checkout = $day['attendance_data']['checkout_time'] ?? '';
            $hours = $day['attendance_data']['total_hours'] ?? '';
            
            fputcsv($output, [
                $day['date'],
                $day['status'],
                $day['leave_type'] ?? '',
                $day['leave_duration'] ?? '',
                $checkin,
                $checkout,
                $hours
            ]);
        }
        
        fclose($output);
        exit;
    }
}
