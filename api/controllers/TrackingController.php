<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

class TrackingController {

    /**
     * Log a live tracking position for the authenticated employee
     * POST /api/tracking/log
     */
    public static function logPosition() {
        $auth = authenticate();
        requireRole($auth, [ROLE_EMPLOYEE]);

        $input = getRequestBody();

        $validator = new Validator();
        $validator->required('latitude', getParam($input, 'latitude'))
                  ->numeric('latitude', getParam($input, 'latitude'))
                  ->required('longitude', getParam($input, 'longitude'))
                  ->numeric('longitude', getParam($input, 'longitude'))
                  ->validate();

        $lat = (float)getParam($input, 'latitude');
        $lng = (float)getParam($input, 'longitude');
        $accuracy = getParam($input, 'accuracy');
        $speed = getParam($input, 'speed');
        $heading = getParam($input, 'heading');

        $db = Database::getInstance()->getConnection();
        // Insert into live_tracking table
        $stmt = $db->prepare("
            INSERT INTO live_tracking 
            (employee_id, latitude, longitude, accuracy, speed, heading, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        try {
            $stmt->execute([
                $auth['user_id'],
                $lat,
                $lng,
                $accuracy,
                $speed,
                $heading
            ]);

            Response::success(null, 'Position logged successfully');
        } catch (PDOException $e) {
            Response::error('Database error while logging position', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get latest tracking locations for active employees in the company
     * GET /api/tracking/active
     */
    public static function getActiveLocations() {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);

        $db = Database::getInstance()->getConnection();

        try {
            $stmt = $db->prepare("
                SELECT 
                    u.id as employee_id, u.name, u.avatar_url, u.department,
                    a.checkin_time, a.attendance_type,
                    lt.latitude, lt.longitude, lt.accuracy, lt.timestamp
                FROM users u
                JOIN attendance a ON u.id = a.employee_id AND a.date = CURDATE()
                JOIN (
                    SELECT t1.*
                    FROM live_tracking t1
                    INNER JOIN (
                        SELECT employee_id, MAX(timestamp) as max_time
                        FROM live_tracking
                        WHERE DATE(timestamp) = CURDATE()
                        GROUP BY employee_id
                    ) t2 ON t1.employee_id = t2.employee_id AND t1.timestamp = t2.max_time
                ) lt ON u.id = lt.employee_id
                WHERE u.company_id = ? AND a.checkout_time IS NULL
            ");
            $stmt->execute([$auth['company_id']]);
            $locations = $stmt->fetchAll();

            Response::success($locations, 'Active locations retrieved');
        } catch (PDOException $e) {
            Response::error('Database error while fetching active locations', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get historical route locations for a specific employee and date
     * GET /api/tracking/history/{employee_id}?date=YYYY-MM-DD
     */
    public static function getEmployeeHistory($employeeId) {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);

        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $db = Database::getInstance()->getConnection();

        try {
            // Verify employee exists and belongs to the same company
            $empCheck = $db->prepare("SELECT id, company_id FROM users WHERE id = ?");
            $empCheck->execute([$employeeId]);
            $emp = $empCheck->fetch();

            if (!$emp) {
                Response::error('Employee not found', 404);
                return;
            }

            if ($auth['role'] !== ROLE_SUPER_ADMIN && (int)$emp['company_id'] !== (int)$auth['company_id']) {
                Response::error('Unauthorized access to employee tracking data', 403);
                return;
            }

            // Fetch coordinates chronologically by timestamp
            $stmt = $db->prepare("
                SELECT latitude, longitude, accuracy, speed, heading, timestamp 
                FROM live_tracking 
                WHERE employee_id = ? AND DATE(timestamp) = ? 
                ORDER BY timestamp ASC
            ");
            $stmt->execute([$employeeId, $date]);
            $history = $stmt->fetchAll();

            Response::success($history, 'Route history retrieved successfully');
        } catch (PDOException $e) {
            Response::error('Database error while fetching route history', 500, ['error' => $e->getMessage()]);
        }
    }
}

