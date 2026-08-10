<?php
/**
 * Attendance Request Controller
 */

class AttendanceRequestController {

    private static function checkOverlap(PDO $db, int $employeeId, string $startDate, string $endDate, array $requestTypes): void {
        $leaveStmt = $db->prepare("
            SELECT id FROM leaves 
            WHERE employee_id = ? 
            AND status = 'approved' 
            AND start_date <= ? 
            AND end_date >= ?
        ");
        $leaveStmt->execute([$employeeId, $endDate, $startDate]);
        if ($leaveStmt->fetch()) {
            Response::error('Conflict: An approved leave exists for the selected dates.', 409);
        }

        if (!empty($requestTypes)) {
            $inTypes = implode("','", $requestTypes);
            $reqStmt = $db->prepare("
                SELECT id FROM attendance_requests
                WHERE employee_id = ?
                AND request_type IN ('$inTypes')
                AND status IN ('pending', 'approved')
                AND deleted_at IS NULL
                AND (start_date <= ? AND COALESCE(end_date, start_date) >= ?)
            ");
            $reqStmt->execute([$employeeId, $endDate, $startDate]);
            if ($reqStmt->fetch()) {
                Response::error("Conflict: Overlapping WFH or Outdoor request exists.", 409);
            }
        }
    }

    private static function checkDuplicate(PDO $db, int $employeeId, string $requestType, string $startDate, ?string $endDate = null): void {
        $endDate = $endDate ?? $startDate;
        $reqStmt = $db->prepare("
            SELECT id FROM attendance_requests
            WHERE employee_id = ?
            AND request_type = ?
            AND status = 'pending'
            AND deleted_at IS NULL
            AND start_date = ? 
            AND COALESCE(end_date, start_date) = ?
        ");
        $reqStmt->execute([$employeeId, $requestType, $startDate, $endDate]);
        if ($reqStmt->fetch()) {
            Response::error("You already have a pending $requestType request for this date.", 409);
        }
    }

    public static function myRequests(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        
        $status = $_GET['status'] ?? null;
        $type = $_GET['request_type'] ?? null;
        
        $db = Database::getInstance()->getConnection();
        
        $query = "SELECT * FROM attendance_requests WHERE employee_id = ? AND deleted_at IS NULL";
        $params = [$auth['user_id']];
        
        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        if ($type) {
            $query .= " AND request_type = ?";
            $params[] = $type;
        }
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();
        
        foreach ($requests as &$req) {
            if ($req['applied_data']) {
                $req['applied_data'] = json_decode($req['applied_data'], true);
            }
        }
        
        Response::success($requests);
    }

    public static function applyWfh(): void {
        self::applyMultiDay('wfh');
    }

    public static function applyOutdoor(): void {
        self::applyMultiDay('outdoor');
    }

    private static function applyMultiDay(string $type): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $body = getRequestBody();
        
        $startDate = $body['start_date'] ?? null;
        $endDate = $body['end_date'] ?? null;
        $reason = $body['reason'] ?? null;
        
        if (!$startDate || !$endDate || !$reason) {
            Response::error('Missing required fields: start_date, end_date, reason', 400);
        }
        if ($startDate > $endDate) {
            Response::error('start_date must be before or equal to end_date', 400);
        }
        
        $db = Database::getInstance()->getConnection();
        self::checkDuplicate($db, $auth['user_id'], $type, $startDate, $endDate);
        self::checkOverlap($db, $auth['user_id'], $startDate, $endDate, ['wfh', 'outdoor']);
        
        $stmt = $db->prepare("
            INSERT INTO attendance_requests 
            (employee_id, company_id, request_type, start_date, end_date, reason) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $auth['user_id'], 
            $auth['company_id'], 
            $type, 
            $startDate, 
            $endDate, 
            $reason
        ]);
        
        Response::success(null, 'Request submitted successfully', 201);
    }
    
    public static function getDateInfo(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT checkin_time, checkout_time, status, total_hours FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$auth['user_id'], $date]);
        $record = $stmt->fetch();
        
        Response::success($record ?: null);
    }

    public static function applyTimeCorrection(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $body = getRequestBody();
        
        $date = $body['date'] ?? null; 
        $timeIn = $body['time_in'] ?? null; 
        $timeOut = $body['time_out'] ?? null;
        $reason = $body['reason'] ?? null;
        
        if (!$date || !$reason || (!$timeIn && !$timeOut)) {
            Response::error('Missing required fields', 400);
        }
        
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
        if ($date < $sevenDaysAgo || $date > date('Y-m-d')) {
            Response::error('Time correction is only allowed for the past 7 days', 403);
        }
        
        $db = Database::getInstance()->getConnection();
        
        $dupStmt = $db->prepare("SELECT id FROM attendance_requests WHERE employee_id = ? AND request_type = 'time_correction' AND start_date = ? AND status IN ('pending', 'approved') AND deleted_at IS NULL");
        $dupStmt->execute([$auth['user_id'], $date]);
        if ($dupStmt->fetch()) {
            Response::error('A time correction request already exists for this date', 409);
        }
        
        self::checkOverlap($db, $auth['user_id'], $date, $date, []);
        
        $attStmt = $db->prepare("SELECT checkin_time, checkout_time FROM attendance WHERE employee_id = ? AND date = ?");
        $attStmt->execute([$auth['user_id'], $date]);
        $att = $attStmt->fetch();
        
        $originalTimeIn = $att['checkin_time'] ?? null;
        $originalTimeOut = $att['checkout_time'] ?? null;
        
        $finalTimeIn = $timeIn ?: $originalTimeIn;
        $finalTimeOut = $timeOut ?: $originalTimeOut;
        
        if ($finalTimeIn && $finalTimeOut && $finalTimeIn >= $finalTimeOut) {
            Response::error('time_in must be before time_out', 400);
        }
        
        $stmt = $db->prepare("
            INSERT INTO attendance_requests 
            (employee_id, company_id, request_type, start_date, original_time_in, original_time_out, time_in, time_out, reason) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $auth['user_id'], 
            $auth['company_id'], 
            'time_correction', 
            $date, 
            $originalTimeIn, 
            $originalTimeOut, 
            $finalTimeIn, 
            $finalTimeOut, 
            $reason
        ]);
        
        Response::success(null, 'Time correction requested', 201);
    }

    public static function applyStatusCorrection(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $body = getRequestBody();
        
        $date = $body['date'] ?? null;
        $status = $body['status'] ?? null;
        $reason = $body['reason'] ?? null;
        
        if (!$date || !$status || !$reason) {
            Response::error('Missing fields', 400);
        }
        if (!in_array($status, ['present', 'absent', 'half_day'])) {
            Response::error('Invalid corrected status', 400);
        }
        
        $db = Database::getInstance()->getConnection();
        self::checkDuplicate($db, $auth['user_id'], 'status_correction', $date);
        self::checkOverlap($db, $auth['user_id'], $date, $date, []);
        
        $stmt = $db->prepare("
            INSERT INTO attendance_requests 
            (employee_id, company_id, request_type, start_date, corrected_status, reason) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $auth['user_id'], 
            $auth['company_id'], 
            'status_correction', 
            $date, 
            $status, 
            $reason
        ]);
        
        Response::success(null, 'Status correction requested', 201);
    }

    public static function adminAllRequests(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        $db = Database::getInstance()->getConnection();
        
        $query = "SELECT r.*, u.first_name, u.last_name FROM attendance_requests r JOIN users u ON r.employee_id = u.id WHERE r.deleted_at IS NULL";
        $params = [];
        
        if ($companyId) {
            $query .= " AND r.company_id = ?";
            $params[] = $companyId;
        }
        
        $query .= " ORDER BY r.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $reqs = $stmt->fetchAll();
        
        foreach ($reqs as &$r) {
            if ($r['applied_data']) $r['applied_data'] = json_decode($r['applied_data'], true);
        }
        Response::success($reqs);
    }

    public static function adminReject(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $body = getRequestBody();
        $overrideTimeOut = $body['override_time_out'] ?? null;
        
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare("SELECT * FROM attendance_requests WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $req = $stmt->fetch();
            
            if (!$req || $req['status'] !== 'pending' || $req['deleted_at'] !== null) {
                throw new Exception('Request not found or already processed', 400);
            }
            if ($auth['role'] === ROLE_COMPANY_ADMIN && $req['company_id'] !== $auth['company_id']) {
                throw new Exception('Forbidden', 403);
            }

            $uReq = $db->prepare("UPDATE attendance_requests SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
            $uReq->execute([$auth['user_id'], $id]);
            
            if ($req['request_type'] === 'out_of_bounds_checkout' && $overrideTimeOut) {
                $d = $req['start_date'];
                $cStmt = $db->prepare("SELECT id, checkin_time FROM attendance WHERE employee_id = ? AND date = ?");
                $cStmt->execute([$req['employee_id'], $d]);
                $att = $cStmt->fetch();
                
                if ($att) {
                    $totalHours = null;
                    if ($att['checkin_time'] && $overrideTimeOut) {
                        $totalHours = round((strtotime($overrideTimeOut) - strtotime($att['checkin_time'])) / 3600, 2);
                    }
                    $uStmt = $db->prepare("UPDATE attendance SET checkout_time = ?, total_hours = ?, source = 'manager_override' WHERE id = ?");
                    $uStmt->execute([$overrideTimeOut, $totalHours, $att['id']]);
                }
            }
            
            $db->commit();
            Response::success(null, 'Request rejected');
        } catch (Exception $e) {
            $db->rollBack();
            $code = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $code === 400 || $code === 403 || $code === 409 ? $code : 500);
        }
    }

    public static function adminApprove(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare("SELECT * FROM attendance_requests WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $req = $stmt->fetch();
            
            if (!$req || $req['status'] !== 'pending' || $req['deleted_at'] !== null) {
                throw new Exception('Request not found or already processed', 400);
            }
            if ($auth['role'] === ROLE_COMPANY_ADMIN && $req['company_id'] !== $auth['company_id']) {
                throw new Exception('Forbidden', 403);
            }

            // 🚨 Re-check conflicts INSIDE approval transaction
            $leaveStmt = $db->prepare("
                SELECT id FROM leaves 
                WHERE employee_id = ? 
                AND status = 'approved' 
                AND start_date <= ? 
                AND end_date >= ?
            ");
            $endDate = $req['end_date'] ?? $req['start_date'];
            $leaveStmt->execute([$req['employee_id'], $endDate, $req['start_date']]);
            if ($leaveStmt->fetch()) {
                throw new Exception('Conflict: An approved leave exists for the selected dates.', 409);
            }

            if ($req['request_type'] === 'wfh' || $req['request_type'] === 'outdoor') {
                $reqStmt = $db->prepare("
                    SELECT id FROM attendance_requests
                    WHERE employee_id = ?
                    AND request_type IN ('wfh','outdoor')
                    AND status = 'approved'
                    AND deleted_at IS NULL
                    AND id != ?
                    AND (start_date <= ? AND COALESCE(end_date, start_date) >= ?)
                ");
                $reqStmt->execute([$req['employee_id'], $req['id'], $req['end_date'], $req['start_date']]);
                if ($reqStmt->fetch()) {
                    throw new Exception("Conflict: Overlapping WFH or Outdoor request exists.", 409);
                }
            }

            $appliedData = [];

            if ($req['request_type'] === 'wfh' || $req['request_type'] === 'outdoor') {
                $statusApp = $req['request_type'];
                $curr = new DateTime($req['start_date']);
                $end = new DateTime($req['end_date']);
                $datesApplied = [];
                
                while ($curr <= $end) {
                    $datesApplied[] = $curr->format('Y-m-d');
                    $curr->modify('+1 day');
                }
                $appliedData = [
                    'type' => $statusApp,
                    'dates' => $datesApplied,
                    'status_applied' => 'pending_checkin' // Attendance created via daily checkin
                ];
            }
            elseif ($req['request_type'] === 'time_correction') {
                $d = $req['start_date'];
                $cStmt = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
                $cStmt->execute([$req['employee_id'], $d]);
                $att = $cStmt->fetch();
                
                $totalHours = null;
                if ($req['time_in'] && $req['time_out']) {
                    $totalHours = round((strtotime($req['time_out']) - strtotime($req['time_in'])) / 3600, 2);
                }
                
                if ($att) {
                    $uStmt = $db->prepare("UPDATE attendance SET checkin_time = ?, checkout_time = ?, total_hours = ?, source = 'system_correction' WHERE id = ?");
                    $uStmt->execute([$req['time_in'], $req['time_out'], $totalHours, $att['id']]);
                } else {
                    $iStmt = $db->prepare("INSERT INTO attendance (employee_id, company_id, date, checkin_time, checkout_time, total_hours, status, attendance_type, source) VALUES (?, ?, ?, ?, ?, ?, 'present', 'office', 'system_correction')");
                    $iStmt->execute([$req['employee_id'], $req['company_id'], $d, $req['time_in'], $req['time_out'], $totalHours]);
                }
                
                $appliedData = [
                    'type' => 'time_correction',
                    'old' => ['time_in' => $req['original_time_in'], 'time_out' => $req['original_time_out']],
                    'new' => ['time_in' => $req['time_in'], 'time_out' => $req['time_out']]
                ];
            }
            elseif ($req['request_type'] === 'status_correction') {
                $d = $req['start_date'];
                $cStmt = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
                $cStmt->execute([$req['employee_id'], $d]);
                $att = $cStmt->fetch();
                
                if ($att) {
                    $uStmt = $db->prepare("UPDATE attendance SET status = ?, source = 'system_correction' WHERE id = ?");
                    $uStmt->execute([$req['corrected_status'], $att['id']]);
                } else {
                    $iStmt = $db->prepare("INSERT INTO attendance (employee_id, company_id, date, status, attendance_type, source) VALUES (?, ?, ?, ?, 'office', 'system_correction')");
                    $iStmt->execute([$req['employee_id'], $req['company_id'], $d, $req['corrected_status']]);
                }
                $appliedData = [
                    'type' => 'status_correction',
                    'date' => $d,
                    'status_applied' => $req['corrected_status']
                ];
            }
            elseif ($req['request_type'] === 'out_of_bounds_checkout') {
                $d = $req['start_date'];
                $cStmt = $db->prepare("SELECT id, checkin_time FROM attendance WHERE employee_id = ? AND date = ?");
                $cStmt->execute([$req['employee_id'], $d]);
                $att = $cStmt->fetch();
                
                if ($att) {
                    $totalHours = null;
                    if ($att['checkin_time'] && $req['time_out']) {
                        $totalHours = round((strtotime($req['time_out']) - strtotime($att['checkin_time'])) / 3600, 2);
                    }
                    
                    $locData = json_decode($req['reason'], true);
                    $lat = $locData['latitude'] ?? null;
                    $lng = $locData['longitude'] ?? null;
                    
                    if ($lat && $lng) {
                        $uStmt = $db->prepare("UPDATE attendance SET checkout_time = ?, checkout_latitude = ?, checkout_longitude = ?, total_hours = ?, source = 'approved_request' WHERE id = ?");
                        $uStmt->execute([$req['time_out'], $lat, $lng, $totalHours, $att['id']]);
                    } else {
                        $uStmt = $db->prepare("UPDATE attendance SET checkout_time = ?, total_hours = ?, source = 'approved_request' WHERE id = ?");
                        $uStmt->execute([$req['time_out'], $totalHours, $att['id']]);
                    }
                }
                
                $appliedData = [
                    'type' => 'out_of_bounds_checkout',
                    'time_out' => $req['time_out']
                ];
            }

            $uReq = $db->prepare("UPDATE attendance_requests SET status = 'approved', approved_by = ?, approved_at = NOW(), applied_data = ? WHERE id = ? AND status = 'pending'");
            $uReq->execute([$auth['user_id'], json_encode($appliedData), $id]);
            
            $db->commit();
            Response::success(null, 'Request approved successfully');
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Failed to approve request $id: " . $e->getMessage());
            $code = $e->getCode() ?: 500;
            if ($code < 400 || $code > 599) $code = 400;
            Response::error($e->getMessage(), $code);
        }
    }
}
