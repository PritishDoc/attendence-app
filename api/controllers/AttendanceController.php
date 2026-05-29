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

        $status = 'present';

        // Office attendance — verify GPS radius
        if ($type === 'office' && $lat && $lng) {
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

        Response::success([
            'attendance_id' => $id,
            'status' => $status,
            'checkin_time' => date('Y-m-d H:i:s')
        ], $status === 'late' ? 'Checked in (Late)' : 'Checked in successfully', 201);
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
}
