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

// Load controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/CompanyController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';
require_once __DIR__ . '/controllers/AttendanceController.php';
require_once __DIR__ . '/controllers/DepartmentController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/TrackingController.php';

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

    // ─── Attendance Routes ───
    elseif ($uri === '/attendance/checkin' && $method === 'POST') {
        AttendanceController::checkin();
    }
    elseif ($uri === '/attendance/checkout' && $method === 'POST') {
        AttendanceController::checkout();
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

    // ─── Health Check ───
    elseif ($uri === '/health' || $uri === '' || $uri === '/') {
        Response::success([
            'app'     => APP_NAME,
            'version' => APP_VERSION,
            'time'    => date('Y-m-d H:i:s'),
            'status'  => 'running'
        ], 'API is running');
    }

    // ─── 404 ───
    else {
        Response::error('Endpoint not found', 404);
    }
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
