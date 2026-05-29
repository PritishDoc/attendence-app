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
}
