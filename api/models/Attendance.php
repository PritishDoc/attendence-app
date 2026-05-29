<?php
/**
 * Attendance Model
 */

class Attendance {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT a.*, u.name as employee_name, u.department, u.employee_id_code FROM attendance a JOIN users u ON a.employee_id = u.id WHERE a.id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function findTodayByEmployee(int $employeeId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
        $stmt->execute([$employeeId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function checkin(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO attendance (employee_id, company_id, date, checkin_time, checkin_latitude, checkin_longitude, attendance_type, status) VALUES (?, ?, CURDATE(), NOW(), ?, ?, ?, ?)");
        $stmt->execute([
            $data['employee_id'], $data['company_id'],
            $data['latitude'] ?? null, $data['longitude'] ?? null,
            $data['attendance_type'] ?? 'office', $data['status'] ?? 'present'
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function checkout(int $id): bool {
        $stmt = $this->db->prepare("UPDATE attendance SET checkout_time = NOW(), total_hours = TIMESTAMPDIFF(MINUTE, checkin_time, NOW()) / 60.0 WHERE id = ? AND checkout_time IS NULL");
        return $stmt->execute([$id]);
    }

    public function checkoutWithLocation(int $id, float $lat, float $lng): bool {
        $stmt = $this->db->prepare("UPDATE attendance SET checkout_time = NOW(), checkout_latitude = ?, checkout_longitude = ?, total_hours = TIMESTAMPDIFF(MINUTE, checkin_time, NOW()) / 60.0 WHERE id = ? AND checkout_time IS NULL");
        return $stmt->execute([$lat, $lng, $id]);
    }

    public function getByCompanyAndDate(int $companyId, string $date, array $filters = []): array {
        $where = "a.company_id = ? AND a.date = ?";
        $params = [$companyId, $date];
        if (!empty($filters['department'])) {
            $where .= " AND u.department = ?";
            $params[] = $filters['department'];
        }
        if (!empty($filters['status'])) {
            $where .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        $stmt = $this->db->prepare("SELECT a.*, u.name as employee_name, u.department, u.employee_id_code FROM attendance a JOIN users u ON a.employee_id = u.id WHERE $where ORDER BY a.checkin_time ASC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getHistory(int $employeeId, string $startDate, string $endDate): array {
        $stmt = $this->db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date BETWEEN ? ? ORDER BY date DESC");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }

    public function getMonthlyReport(int $companyId, int $year, int $month): array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.department, u.employee_id_code,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_days,
                ROUND(AVG(a.total_hours), 1) as avg_hours
            FROM users u
            LEFT JOIN attendance a ON u.id = a.employee_id AND YEAR(a.date) = ? AND MONTH(a.date) = ?
            WHERE u.company_id = ? AND u.role = 'employee' AND u.status = 'active'
            GROUP BY u.id ORDER BY u.name
        ");
        $stmt->execute([$year, $month, $companyId]);
        return $stmt->fetchAll();
    }

    public function getTodayStats(int $companyId): array {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(CASE WHEN a.status IN ('present','late') THEN 1 END) as present,
                COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
                COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_day,
                (SELECT COUNT(*) FROM users WHERE company_id = ? AND role = 'employee' AND status = 'active') as total_employees
            FROM attendance a WHERE a.company_id = ? AND a.date = CURDATE()
        ");
        $stmt->execute([$companyId, $companyId]);
        $stats = $stmt->fetch();
        $stats['absent'] = $stats['total_employees'] - $stats['present'];
        return $stats;
    }

    public function getWeeklyTrend(int $companyId): array {
        $stmt = $this->db->prepare("
            SELECT a.date,
                COUNT(CASE WHEN a.status IN ('present','late') THEN 1 END) as present,
                COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent
            FROM attendance a
            WHERE a.company_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY a.date ORDER BY a.date
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    /**
     * Haversine distance in meters
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $R = 6371000; // Earth radius in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}
