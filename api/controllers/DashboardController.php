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
}
