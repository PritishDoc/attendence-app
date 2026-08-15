<?php
/**
 * API Router — Entry Point
 * Routes requests to appropriate controllers
 */

// Load configuration
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

// Load helpers
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/validation.php';

// Load middleware
require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/middleware/auth.php';

// Load models
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Company.php';
require_once __DIR__ . '/models/Attendance.php';
require_once __DIR__ . '/models/Department.php';
require_once __DIR__ . '/models/Subscription.php';
require_once __DIR__ . '/models/Branch.php';
require_once __DIR__ . '/models/Holiday.php';
require_once __DIR__ . '/models/CompanySetting.php';
require_once __DIR__ . '/models/CompanyLeavePolicy.php';
require_once __DIR__ . '/models/CompanyDocumentType.php';

// Load controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/CompanyController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';
require_once __DIR__ . '/controllers/AttendanceController.php';
require_once __DIR__ . '/controllers/DepartmentController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/TrackingController.php';
require_once __DIR__ . '/controllers/AttendanceRequestController.php';
require_once __DIR__ . '/controllers/LeaveController.php';
require_once __DIR__ . '/controllers/ProfileController.php';
require_once __DIR__ . '/controllers/TeamController.php';
require_once __DIR__ . '/controllers/AdminEmployeeController.php';
require_once __DIR__ . '/controllers/FileProxyController.php';
require_once __DIR__ . '/controllers/PayrollController.php';
require_once __DIR__ . '/controllers/DesignationController.php';
require_once __DIR__ . '/controllers/BranchController.php';
require_once __DIR__ . '/controllers/HolidayController.php';
require_once __DIR__ . '/controllers/CompanySettingController.php';
require_once __DIR__ . '/controllers/CompanyLeavePolicyController.php';
require_once __DIR__ . '/controllers/CompanyDocumentTypeController.php';
require_once __DIR__ . '/controllers/EmployeeDocumentController.php';
require_once __DIR__ . '/controllers/VisitController.php';
// Apply CORS
handleCors();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Remove query string and base path
$uri = parse_url($uri, PHP_URL_PATH);
$uri = str_replace('/api', '', $uri);
$uri = rtrim($uri, '/');

// Simple router
try {
    // ─── Auth Routes ───
    if ($uri === '/auth/login' && $method === 'POST') {
        AuthController::login();
    }
    elseif ($uri === '/auth/register' && $method === 'POST') {
        AuthController::register();
    }
    elseif ($uri === '/auth/me' && $method === 'GET') {
        AuthController::me();
    }
    elseif ($uri === '/auth/change-initial-password' && $method === 'POST') {
        AuthController::changeInitialPassword();
    }
    elseif ($uri === '/auth/refresh-token' && $method === 'POST') {
        AuthController::refreshToken();
    }
    elseif ($uri === '/auth/logout' && $method === 'POST') {
        AuthController::logout();
    }

    // ─── Company Routes ───
    elseif ($uri === '/companies' && $method === 'GET') {
        CompanyController::index();
    }
    elseif (preg_match('/^\/companies\/(\d+)$/', $uri, $m) && $method === 'GET') {
        CompanyController::show((int)$m[1]);
    }
    elseif (preg_match('/^\/companies\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        CompanyController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/companies\/(\d+)\/status$/', $uri, $m) && $method === 'PATCH') {
        CompanyController::updateStatus((int)$m[1]);
    }
    elseif (preg_match('/^\/companies\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        CompanyController::delete((int)$m[1]);
    }

    // ─── Branch Routes ───
    elseif ($uri === '/branches' && $method === 'GET') {
        BranchController::index();
    }
    elseif ($uri === '/branches' && $method === 'POST') {
        BranchController::create();
    }
    elseif (preg_match('/^\/branches\/(\d+)$/', $uri, $m) && $method === 'GET') {
        BranchController::show((int)$m[1]);
    }
    elseif (preg_match('/^\/branches\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        BranchController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/branches\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        BranchController::delete((int)$m[1]);
    }

    // ─── Holiday Routes ───
    elseif ($uri === '/holidays' && $method === 'GET') {
        HolidayController::index();
    }
    elseif ($uri === '/holidays' && $method === 'POST') {
        HolidayController::create();
    }
    elseif (preg_match('/^\/holidays\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        HolidayController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/holidays\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        HolidayController::delete((int)$m[1]);
    }

    // ─── Settings Routes ───
    elseif ($uri === '/settings' && $method === 'GET') {
        CompanySettingController::show();
    }
    elseif ($uri === '/settings' && $method === 'PUT') {
        CompanySettingController::update();
    }

    // ─── Leave Policy Routes ───
    elseif ($uri === '/leave-policies' && $method === 'GET') {
        CompanyLeavePolicyController::index();
    }
    elseif ($uri === '/leave-policies' && $method === 'POST') {
        CompanyLeavePolicyController::create();
    }
    elseif (preg_match('/^\/leave-policies\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        CompanyLeavePolicyController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/leave-policies\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        CompanyLeavePolicyController::delete((int)$m[1]);
    }

    // ─── Document Types Routes ───
    elseif ($uri === '/document-types' && $method === 'GET') {
        CompanyDocumentTypeController::index();
    }
    elseif ($uri === '/document-types' && $method === 'POST') {
        CompanyDocumentTypeController::create();
    }
    elseif (preg_match('/^\/document-types\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        CompanyDocumentTypeController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/document-types\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        CompanyDocumentTypeController::delete((int)$m[1]);
    }

    // ─── Employee Routes ───
    elseif ($uri === '/employees' && $method === 'GET') {
        EmployeeController::index();
    }
    elseif ($uri === '/employees' && $method === 'POST') {
        EmployeeController::create();
    }
    elseif (preg_match('/^\/employees\/(\d+)$/', $uri, $m) && $method === 'GET') {
        EmployeeController::show((int)$m[1]);
    }
    elseif (preg_match('/^\/employees\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        EmployeeController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/employees\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        EmployeeController::delete((int)$m[1]);
    }
    elseif (preg_match('/^\/employees\/(\d+)\/reset-device$/', $uri, $m) && $method === 'POST') {
        EmployeeController::resetDevice((int)$m[1]);
    }
    elseif (preg_match('/^\/employees\/(\d+)\/activate$/', $uri, $m) && $method === 'PATCH') {
        EmployeeController::activate((int)$m[1]);
    }
    elseif (preg_match('/^\/employees\/(\d+)\/deactivate$/', $uri, $m) && $method === 'PATCH') {
        EmployeeController::deactivate((int)$m[1]);
    }

    // ─── Attendance Routes ───
    elseif ($uri === '/attendance/checkin' && $method === 'POST') {
        AttendanceController::checkin();
    }
    elseif ($uri === '/attendance/checkout' && $method === 'POST') {
        AttendanceController::checkout();
    }
    elseif ($uri === '/attendance/checkout-out-of-bounds' && $method === 'POST') {
        AttendanceController::requestOutdoorCheckout();
    }
    elseif ($uri === '/attendance/today' && $method === 'GET') {
        AttendanceController::today();
    }
    elseif ($uri === '/attendance/history' && $method === 'GET') {
        AttendanceController::history();
    }
    elseif ($uri === '/attendance/report' && $method === 'GET') {
        AttendanceController::report();
    }
    elseif ($uri === '/attendance/status' && $method === 'GET') {
        AttendanceController::status();
    }
    elseif ($uri === '/attendance/my-history/daily' && $method === 'GET') {
        AttendanceController::myDailyHistory();
    }
    elseif ($uri === '/attendance/my-history/weekly' && $method === 'GET') {
        AttendanceController::myWeeklyHistory();
    }
    elseif ($uri === '/attendance/my-history/monthly' && $method === 'GET') {
        AttendanceController::myMonthlyHistory();
    }
    elseif ($uri === '/attendance/my-summary/hours' && $method === 'GET') {
        AttendanceController::myMonthlyHours();
    }
    elseif ($uri === '/attendance/my-summary/export' && $method === 'GET') {
        AttendanceController::exportMySummary();
    }
    elseif ($uri === '/attendance/date-info' && $method === 'GET') {
        AttendanceRequestController::getDateInfo();
    }
    elseif ($uri === '/attendance/calendar' && $method === 'GET') {
        AttendanceController::myCalendar();
    }
    
    // ─── Attendance Requests Routes ───
    elseif ($uri === '/attendance-requests/my-requests' && $method === 'GET') {
        AttendanceRequestController::myRequests();
    }
    elseif ($uri === '/attendance-requests/wfh' && $method === 'POST') {
        AttendanceRequestController::applyWfh();
    }
    elseif ($uri === '/attendance-requests/outdoor' && $method === 'POST') {
        AttendanceRequestController::applyOutdoor();
    }
    elseif ($uri === '/attendance-requests/time-correction' && $method === 'POST') {
        AttendanceRequestController::applyTimeCorrection();
    }
    elseif ($uri === '/attendance-requests/status-correction' && $method === 'POST') {
        AttendanceRequestController::applyStatusCorrection();
    }
    elseif ($uri === '/attendance-requests/admin/all' && $method === 'GET') {
        AttendanceRequestController::adminAllRequests();
    }
    elseif (preg_match('#^/attendance-requests/admin/approve/(\d+)$#', $uri, $matches) && $method === 'POST') {
        AttendanceRequestController::adminApprove((int)$matches[1]);
    }
    elseif (preg_match('#^/attendance-requests/admin/reject/(\d+)$#', $uri, $matches) && $method === 'POST') {
        AttendanceRequestController::adminReject((int)$matches[1]);
    }

    // ─── Leave Routes ───
    elseif ($uri === '/leaves/apply' && $method === 'POST') {
        LeaveController::apply();
    }
    elseif ($uri === '/leaves/history' && $method === 'GET') {
        LeaveController::myHistory();
    }
    elseif ($uri === '/leaves/balances' && $method === 'GET') {
        LeaveController::myBalances();
    }
    elseif ($uri === '/leaves/admin/all' && $method === 'GET') {
        LeaveController::adminAll();
    }
    elseif (preg_match('#^/leaves/(\d+)/cancel$#', $uri, $m) && $method === 'POST') {
        LeaveController::cancel((int)$m[1]);
    }
    elseif (preg_match('#^/leaves/(\d+)/status$#', $uri, $m) && $method === 'PUT') {
        LeaveController::updateStatus((int)$m[1]);
    }
    elseif (preg_match('#^/leaves/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        LeaveController::delete((int)$m[1]);
    }

    // ─── Tracking Routes ───
    elseif ($uri === '/tracking/log' && $method === 'POST') {
        TrackingController::logPosition();
    }
    elseif ($uri === '/tracking/active' && $method === 'GET') {
        TrackingController::getActiveLocations();
    }
    elseif (preg_match('/^\/tracking\/history\/(\d+)$/', $uri, $m) && $method === 'GET') {
        TrackingController::getEmployeeHistory((int)$m[1]);
    }

    // ─── Profile Routes ───
    elseif ($uri === '/profile/documents' && $method === 'GET') {
        $auth = requireAuth(['employee']);
        EmployeeDocumentController::list($auth['id']);
    }
    elseif ($uri === '/profile/documents' && $method === 'POST') {
        $auth = requireAuth(['employee']);
        EmployeeDocumentController::upload($auth['id']);
    }
    elseif ($uri === '/profile/work-details' && $method === 'GET') {
        ProfileController::getWorkDetails();
    }
    elseif ($uri === '/profile/address' && $method === 'GET') {
        ProfileController::getAddress();
    }
    elseif ($uri === '/profile/address' && $method === 'POST') {
        ProfileController::createAddress();
    }
    elseif (preg_match('#^/profile/address/([a-f0-9\-]+)$#', $uri, $m) && $method === 'PUT') {
        ProfileController::updateAddress($m[1]);
    }
    elseif (preg_match('#^/profile/address/([a-f0-9\-]+)$#', $uri, $m) && $method === 'DELETE') {
        ProfileController::deleteAddress($m[1]);
    }
    elseif ($uri === '/profile/experience' && $method === 'GET') {
        ProfileController::getExperience();
    }
    elseif ($uri === '/profile/experience' && $method === 'POST') {
        ProfileController::createExperience();
    }
    elseif (preg_match('#^/profile/experience/([a-f0-9\-]+)$#', $uri, $m) && $method === 'DELETE') {
        ProfileController::deleteExperience($m[1]);
    }
    elseif ($uri === '/profile/education' && $method === 'GET') {
        ProfileController::getEducation();
    }
    elseif ($uri === '/profile/education' && $method === 'POST') {
        ProfileController::createEducation();
    }
    elseif (preg_match('#^/profile/education/([a-f0-9\-]+)$#', $uri, $m) && $method === 'DELETE') {
        ProfileController::deleteEducation($m[1]);
    }
    elseif ($uri === '/profile/family' && $method === 'GET') {
        ProfileController::getFamily();
    }
    elseif ($uri === '/profile/family' && $method === 'POST') {
        ProfileController::createFamily();
    }
    elseif (preg_match('#^/profile/family/([a-f0-9\-]+)$#', $uri, $m) && $method === 'DELETE') {
        ProfileController::deleteFamily($m[1]);
    }

    // ─── Team Routes ───
    elseif ($uri === '/team' && $method === 'GET') {
        TeamController::getTeam();
    }
    elseif ($uri === '/team/structure' && $method === 'GET') {
        TeamController::getStructure();
    }

    // ─── Admin Employee Routes ───
    elseif (preg_match('#^/admin/employees/(\d+)/joining-details$#', $uri, $m) && $method === 'PUT') {
        AdminEmployeeController::updateJoiningDetails((int)$m[1]);
    }
    elseif (preg_match('#^/admin/employees/(\d+)/joining-details$#', $uri, $m) && $method === 'GET') {
        AdminEmployeeController::getJoiningDetails((int)$m[1]);
    }
    elseif (preg_match('#^/admin/employees/(\d+)/documents$#', $uri, $m) && $method === 'GET') {
        EmployeeDocumentController::list((int)$m[1]);
    }
    elseif (preg_match('#^/admin/employees/(\d+)/documents$#', $uri, $m) && $method === 'POST') {
        EmployeeDocumentController::upload((int)$m[1]);
    }
    elseif (preg_match('#^/admin/employees/documents/([a-f0-9\-]+)$#', $uri, $m) && $method === 'DELETE') {
        EmployeeDocumentController::delete($m[1]);
    }
    elseif ($uri === '/admin/documents/pending' && $method === 'GET') {
        EmployeeDocumentController::pendingVerifications();
    }
    elseif (preg_match('#^/admin/documents/([a-f0-9\-]+)/verify$#', $uri, $m) && $method === 'POST') {
        EmployeeDocumentController::verifyDocument($m[1]);
    }

    // ─── File Proxy ───
    elseif (preg_match('#^/files/([a-f0-9\-]+)$#', $uri, $m) && $method === 'GET') {
        FileProxyController::getFile($m[1]);
    }


    // ─── Payroll Routes ───
    elseif (preg_match('#^/payroll/structure/(\d+)$#', $uri, $m) && $method === 'GET') {
        PayrollController::getStructure((int)$m[1]);
    }
    elseif (preg_match('#^/payroll/structure/(\d+)$#', $uri, $m) && $method === 'POST') {
        PayrollController::saveStructure((int)$m[1]);
    }
    elseif ($uri === '/payroll/generate-payslip' && $method === 'POST') {
        PayrollController::generatePayslip();
    }
    elseif (preg_match('#^/payroll/payslips/(\d+)$#', $uri, $m) && $method === 'GET') {
        PayrollController::viewPayslips((int)$m[1]);
    }
    elseif (preg_match('#^/payroll/payslip/(\d+)$#', $uri, $m) && $method === 'GET') {
        PayrollController::getSinglePayslip((int)$m[1]);
    }
    elseif ($uri === '/my/payslips' && $method === 'GET') {
        PayrollController::myPayslips();
    }

    // ─── Designation Routes ───
    elseif ($uri === '/designations' && $method === 'GET') {
        DesignationController::index();
    }
    elseif ($uri === '/designations' && $method === 'POST') {
        DesignationController::create();
    }
    elseif (preg_match('/^\/designations\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        DesignationController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/designations\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        DesignationController::delete((int)$m[1]);
    }

    // ─── Department Routes ───
    elseif ($uri === '/departments' && $method === 'GET') {
        DepartmentController::index();
    }
    elseif ($uri === '/departments' && $method === 'POST') {
        DepartmentController::create();
    }
    elseif (preg_match('/^\/departments\/(\d+)$/', $uri, $m) && $method === 'PUT') {
        DepartmentController::update((int)$m[1]);
    }
    elseif (preg_match('/^\/departments\/(\d+)$/', $uri, $m) && $method === 'DELETE') {
        DepartmentController::delete((int)$m[1]);
    }

    // ─── Dashboard Routes ───
    elseif ($uri === '/dashboard/super-admin' && $method === 'GET') {
        DashboardController::superAdmin();
    }
    elseif ($uri === '/dashboard/company' && $method === 'GET') {
        DashboardController::company();
    }
    elseif ($uri === '/dashboard/employee' && $method === 'GET') {
        DashboardController::employee();
    }
    elseif ($uri === '/dashboard/company/absent-today' && $method === 'GET') {
        DashboardController::absentToday();
    }
    elseif ($uri === '/dashboard/company/leave-trends' && $method === 'GET') {
        DashboardController::leaveTrends();
    }

    // ─── Health Check ───
    elseif ($uri === '/health' || $uri === '' || $uri === '/') {
        Response::success([
            'app'     => APP_NAME,
            'version' => APP_VERSION,
            'time'    => date('Y-m-d H:i:s'),
            'status'  => 'running'
        ], 'API is running');
    }

    // ─── Visits Routes ───
    elseif ($uri === '/visits' && $method === 'POST') {
        VisitController::createVisit();
    }
    elseif ($uri === '/visits' && $method === 'GET') {
        VisitController::getAllVisits();
    }
    elseif (preg_match('/^\/visits\/(\d+)\/checkin$/', $uri, $m) && $method === 'POST') {
        VisitController::checkIn((int)$m[1]);
    }
    elseif (preg_match('/^\/visits\/(\d+)\/checkout$/', $uri, $m) && $method === 'POST') {
        VisitController::checkOut((int)$m[1]);
    }
    elseif ($uri === '/visits/stats' && $method === 'GET') {
        VisitController::getStats();
    }
    elseif ($uri === '/visits/completed' && $method === 'GET') {
        VisitController::getCompletedVisits();
    }

    // ─── 404 ───
    else {
        Response::error('Endpoint not found', 404);
    }
} catch (Throwable $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
