<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require 'api/config/Database.php';
require 'api/controllers/AttendanceController.php';

define('ROLE_EMPLOYEE', 2);

class Response {
    public static function success($data, $message = '') {
        echo json_encode(['success' => true, 'data' => $data, 'message' => $message]);
        exit;
    }
}

function authenticate() {
    return ['user_id' => 14];
}
function requireRole($auth, $roles) {
    return true;
}

$_GET['year'] = '2026';
$_GET['month'] = '08';
AttendanceController::myCalendar();
