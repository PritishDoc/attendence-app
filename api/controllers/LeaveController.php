<?php
/**
 * Leave Controller
 */

require_once __DIR__ . '/../models/Leave.php';
require_once __DIR__ . '/../models/Attendance.php';

class LeaveController {

    private static function isWeekend(string $date): bool {
        $day = date('N', strtotime($date));
        return ($day >= 6); // 6 = Sat, 7 = Sun
    }

    private static function calculateLeaveDays(PDO $db, int $companyId, string $startDate, string $endDate, string $duration): array {
        $holidays = (new Leave())->getHolidaysBetween($companyId, $startDate, $endDate);
        
        $curr = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        $totalDays = 0;
        $daysByYear = [];

        while ($curr <= $end) {
            $d = $curr->format('Y-m-d');
            $year = (int)$curr->format('Y');
            
            if (!isset($daysByYear[$year])) {
                $daysByYear[$year] = 0;
            }

            if (!self::isWeekend($d) && !in_array($d, $holidays)) {
                $val = 1.0;
                if ($d === $startDate && in_array($duration, ['half_day_start', 'half_day_end'])) {
                    $val = 0.5;
                }
                if ($d === $endDate && $startDate !== $endDate && in_array($duration, ['half_day_start', 'half_day_end'])) {
                    // Assuming if end date is half day, it might need another enum, 
                    // but for simplicity we treat duration as applying to the whole request. 
                    // Actually, if it's half_day, usually it's just a 1-day leave.
                    // If multi-day with half_day_start, we just take 0.5 off.
                    if ($duration === 'half_day_end') $val = 0.5;
                }
                
                $totalDays += $val;
                $daysByYear[$year] += $val;
            }
            
            $curr->modify('+1 day');
        }
        
        return ['total' => $totalDays, 'by_year' => $daysByYear];
    }

    public static function apply(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $body = getRequestBody();
        
        $leaveType = $body['leave_type'] ?? null;
        $duration = $body['leave_duration'] ?? 'full_day';
        $startDate = $body['start_date'] ?? null;
        $endDate = $body['end_date'] ?? null;
        $reason = $body['reason'] ?? null;
        
        if (!$leaveType || !$startDate || !$endDate || !$reason) {
            Response::error('Missing required fields', 400);
        }
        if ($startDate > $endDate) {
            Response::error('start_date must be before or equal to end_date', 400);
        }
        
        $maxFuture = date('Y-m-d', strtotime('+60 days'));
        if ($startDate > $maxFuture) {
            Response::error('Cannot apply for leave more than 60 days in advance', 400);
        }

        $db = Database::getInstance()->getConnection();
        $leaveModel = new Leave();
        
        // 1. Overlap Check
        $overlaps = $leaveModel->getOverlappingLeaves($auth['user_id'], $startDate, $endDate);
        if (count($overlaps) > 0) {
            Response::error('You already have a pending or approved leave during these dates.', 409);
        }

        // 2. WFH/Outdoor Conflict
        $reqStmt = $db->prepare("
            SELECT id FROM attendance_requests
            WHERE employee_id = ?
            AND request_type IN ('wfh','outdoor')
            AND status IN ('pending', 'approved')
            AND deleted_at IS NULL
            AND (start_date <= ? AND COALESCE(end_date, start_date) >= ?)
        ");
        $reqStmt->execute([$auth['user_id'], $endDate, $startDate]);
        if ($reqStmt->fetch()) {
            Response::error("Conflict: Overlapping WFH or Outdoor request exists.", 409);
        }

        // 3. Balance Check
        $calc = self::calculateLeaveDays($db, $auth['company_id'], $startDate, $endDate, $duration);
        if ($calc['total'] <= 0) {
            Response::error('No valid working days in the selected range (all weekends/holidays).', 400);
        }

        if ($leaveType !== 'LOP') {
            foreach ($calc['by_year'] as $year => $days) {
                if ($days > 0) {
                    $balances = $leaveModel->getEmployeeBalances($auth['user_id'], $auth['company_id'], $year);
                    $bal = array_filter($balances, fn($b) => $b['leave_type'] === $leaveType);
                    $bal = reset($bal);
                    
                    if (!$bal || $bal['remaining_days'] < $days) {
                        Response::error("Insufficient $leaveType balance for year $year. Required: $days, Available: " . ($bal ? $bal['remaining_days'] : 0), 400);
                    }
                }
            }
        }

        $body['employee_id'] = $auth['user_id'];
        $body['company_id'] = $auth['company_id'];
        
        $leaveId = $leaveModel->applyLeave($body);
        Response::success(['id' => $leaveId], 'Leave applied successfully', 201);
    }

    public static function myHistory(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        
        $leaveModel = new Leave();
        $leaves = $leaveModel->getLeavesByEmployee($auth['user_id']);
        Response::success($leaves);
    }

    public static function myBalances(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        $year = $_GET['year'] ?? date('Y');
        
        $leaveModel = new Leave();
        $balances = $leaveModel->getEmployeeBalances($auth['user_id'], $auth['company_id'], $year);
        Response::success($balances);
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        
        $leaveModel = new Leave();
        $leave = $leaveModel->findById($id);
        
        if (!$leave || $leave['employee_id'] !== $auth['user_id']) {
            Response::error('Not found or unauthorized', 404);
        }
        
        if ($leave['status'] !== 'pending') {
            Response::error('Only pending leaves can be deleted', 400);
        }
        
        if ($leaveModel->softDelete($id, $auth['user_id'])) {
            Response::success(null, 'Leave deleted successfully');
        } else {
            Response::error('Failed to delete leave', 500);
        }
    }

    public static function cancel(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);
        
        $db = Database::getInstance()->getConnection();
        $leaveModel = new Leave();
        
        $db->beginTransaction();
        try {
            $leave = $leaveModel->findById($id);
            
            if (!$leave || $leave['employee_id'] !== $auth['user_id']) {
                throw new Exception('Not found or unauthorized', 404);
            }
            if ($leave['status'] !== 'approved') {
                throw new Exception('Only approved leaves can be cancelled', 400);
            }
            if ($leave['start_date'] < date('Y-m-d')) {
                throw new Exception('Cannot cancel past or ongoing leaves', 400);
            }

            if (!$leaveModel->cancelLeave($id, $auth['user_id'])) {
                throw new Exception('Failed to cancel leave', 500);
            }

            // Restore Balances
            $calc = self::calculateLeaveDays($db, $auth['company_id'], $leave['approved_start_date'] ?? $leave['start_date'], $leave['approved_end_date'] ?? $leave['end_date'], $leave['leave_duration']);
            if ($leave['leave_type'] !== 'LOP') {
                foreach ($calc['by_year'] as $year => $days) {
                    if ($days > 0) {
                        $leaveModel->updateEmployeeBalance($auth['user_id'], $leave['leave_type'], $year, -$days);
                    }
                }
            }

            // Revert Attendance
            $stmt = $db->prepare("UPDATE attendance SET status = 'absent', source = 'leave_cancelled' WHERE employee_id = ? AND date BETWEEN ? AND ? AND status = 'leave'");
            $stmt->execute([$auth['user_id'], $leave['approved_start_date'] ?? $leave['start_date'], $leave['approved_end_date'] ?? $leave['end_date']]);

            $db->commit();
            Response::success(null, 'Leave cancelled successfully');
        } catch (Exception $e) {
            $db->rollBack();
            $code = $e->getCode() ?: 400;
            if ($code < 400 || $code > 599) $code = 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public static function adminAll(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

        if (!$companyId) {
            Response::error('Company ID is required for super admin', 400);
        }

        $leaveModel = new Leave();
        $leaves = $leaveModel->getAdminLeaves($companyId, $page, $limit);
        Response::success($leaves);
    }

    public static function updateStatus(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        $body = getRequestBody();
        
        $status = $body['status'] ?? null;
        $approvedStart = $body['approved_start_date'] ?? null;
        $approvedEnd = $body['approved_end_date'] ?? null;
        
        if (!in_array($status, ['under_process', 'approved', 'rejected'])) {
            Response::error('Invalid status', 400);
        }

        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare("SELECT * FROM leaves WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $leave = $stmt->fetch();
            
            if (!$leave || !in_array($leave['status'], ['pending', 'under_process'])) {
                throw new Exception('Leave not found or cannot be modified', 400);
            }

            $leaveModel = new Leave();
            
            if ($status === 'approved') {
                $finalStart = $approvedStart ?: $leave['start_date'];
                $finalEnd = $approvedEnd ?: $leave['end_date'];

                // Re-check overlaps inside transaction
                $overlaps = $leaveModel->getOverlappingLeaves($leave['employee_id'], $finalStart, $finalEnd);
                // Exclude current leave from overlaps
                $overlaps = array_filter($overlaps, fn($o) => $o['id'] !== $leave['id']);
                if (count($overlaps) > 0) {
                    throw new Exception('Conflict: Approved leave exists for these dates.', 409);
                }

                // Deduct Balance
                $calc = self::calculateLeaveDays($db, $leave['company_id'], $finalStart, $finalEnd, $leave['leave_duration']);
                if ($leave['leave_type'] !== 'LOP') {
                    foreach ($calc['by_year'] as $year => $days) {
                        if ($days > 0) {
                            $balances = $leaveModel->getEmployeeBalances($leave['employee_id'], $leave['company_id'], $year);
                            $bal = reset(array_filter($balances, fn($b) => $b['leave_type'] === $leave['leave_type']));
                            
                            if (!$bal || $bal['remaining_days'] < $days) {
                                throw new Exception("Insufficient balance for year $year. Need $days.", 400);
                            }
                            $leaveModel->updateEmployeeBalance($leave['employee_id'], $leave['leave_type'], $year, $days);
                        }
                    }
                }

                // Update Attendance
                $curr = new DateTime($finalStart);
                $end = new DateTime($finalEnd);
                while ($curr <= $end) {
                    $d = $curr->format('Y-m-d');
                    $attStmt = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
                    $attStmt->execute([$leave['employee_id'], $d]);
                    $att = $attStmt->fetch();
                    
                    if ($att) {
                        $db->prepare("UPDATE attendance SET status = 'leave', source = 'leave_approved' WHERE id = ?")
                           ->execute([$att['id']]);
                    } else {
                        $db->prepare("INSERT INTO attendance (employee_id, company_id, date, status, attendance_type, source) VALUES (?, ?, ?, 'leave', 'office', 'leave_approved')")
                           ->execute([$leave['employee_id'], $leave['company_id'], $d]);
                    }
                    $curr->modify('+1 day');
                }
                
                $leaveModel->updateStatus($id, $status, $auth['user_id'], $finalStart, $finalEnd);
            } else {
                $leaveModel->updateStatus($id, $status, $auth['user_id']);
            }
            
            $db->commit();
            Response::success(null, "Leave status updated to $status");
            
        } catch (Exception $e) {
            $db->rollBack();
            $code = $e->getCode() ?: 500;
            if ($code < 400 || $code > 599) $code = 400;
            Response::error($e->getMessage(), $code);
        }
    }
}
