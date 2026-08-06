<?php
/**
 * Dashboard Controller — Stats & Analytics
 */

class DashboardController {

    public static function superAdmin(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN]);

        $companyModel = new Company();
        $userModel = new User();
        $subModel = new Subscription();

        Response::success([
            'total_companies'   => $companyModel->countAll(),
            'active_companies'  => $companyModel->countByStatus('active'),
            'pending_companies' => $companyModel->countByStatus('pending'),
            'total_employees'   => $userModel->countAll(),
            'total_revenue'     => $subModel->getTotalRevenue(),
            'recent_companies'  => $companyModel->findAll(['per_page' => 5])['data']
        ]);
    }

    public static function company(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN]);

        $companyId = $auth['company_id'];
        $userModel = new User();
        $attendanceModel = new Attendance();
        $deptModel = new Department();

        $todayStats = $attendanceModel->getTodayStats($companyId);
        $weeklyTrend = $attendanceModel->getWeeklyTrend($companyId);
        $departments = $deptModel->findByCompany($companyId);

        $recentCheckins = $attendanceModel->getByCompanyAndDate($companyId, date('Y-m-d'));
        $recentCheckins = array_slice($recentCheckins, 0, 10);

        Response::success([
            'today'          => $todayStats,
            'weekly_trend'   => $weeklyTrend,
            'departments'    => $departments,
            'recent_checkins'=> $recentCheckins,
            'employee_count' => $userModel->countByCompany($companyId)
        ]);
    }

    public static function employee(): void {
        $auth = authenticate();

        $attendanceModel = new Attendance();
        $today = $attendanceModel->findTodayByEmployee($auth['user_id']);

        // This month stats
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_days,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                ROUND(AVG(total_hours), 1) as avg_hours
            FROM attendance WHERE employee_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
        ");
        $stmt->execute([$auth['user_id']]);
        $monthStats = $stmt->fetch();

        // This week
        $stmt2 = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY date DESC");
        $stmt2->execute([$auth['user_id']]);

        Response::success([
            'today'       => $today,
            'month_stats' => $monthStats,
            'this_week'   => $stmt2->fetchAll()
        ]);
    }

    public static function absentToday(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN]);
        $companyId = $auth['company_id'];
        
        $db = Database::getInstance()->getConnection();
        // Get all active employees who do NOT have a presence record today
        // Or who have an explicit absent/leave record.
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.phone, u.employee_id_code, d.name as department_name, ds.name as designation_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN designations ds ON u.designation_id = ds.id
            WHERE u.company_id = ? AND u.role = 'employee' AND u.status = 'active'
            AND u.id NOT IN (
                SELECT employee_id FROM attendance 
                WHERE company_id = ? AND date = CURDATE() AND status IN ('present', 'late', 'half_day', 'wfh', 'outdoor')
            )
            AND u.id NOT IN (
                SELECT employee_id FROM leaves
                WHERE company_id = ? AND status = 'approved' AND CURDATE() BETWEEN approved_start_date AND approved_end_date
            )
        ");
        $stmt->execute([$companyId, $companyId, $companyId]);
        $absentUsers = $stmt->fetchAll();
        
        Response::success($absentUsers);
    }

    public static function leaveTrends(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN]);
        $companyId = $auth['company_id'];
        
        $db = Database::getInstance()->getConnection();
        
        // Currently on leave (approved leaves covering today)
        $stmtOnLeave = $db->prepare("
            SELECT COUNT(DISTINCT employee_id) FROM leaves 
            WHERE company_id = ? AND status = 'approved' 
            AND CURDATE() BETWEEN approved_start_date AND approved_end_date
        ");
        $stmtOnLeave->execute([$companyId]);
        $currentlyOnLeave = (int) $stmtOnLeave->fetchColumn();
        
        // Upcoming leaves (approved leaves starting in the future)
        $stmtUpcoming = $db->prepare("
            SELECT l.id, l.leave_type, l.approved_start_date, l.approved_end_date, u.name as employee_name
            FROM leaves l
            JOIN users u ON l.employee_id = u.id
            WHERE l.company_id = ? AND l.status = 'approved'
            AND l.approved_start_date > CURDATE()
            ORDER BY l.approved_start_date ASC
            LIMIT 10
        ");
        $stmtUpcoming->execute([$companyId]);
        $upcomingLeaves = $stmtUpcoming->fetchAll();
        
        Response::success([
            'currently_on_leave' => $currentlyOnLeave,
            'upcoming_leaves' => $upcomingLeaves
        ]);
    }
}
