<?php
/**
 * Application Constants
 */

// Application
define('APP_NAME', 'Attendify');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');
define('API_URL', 'http://localhost/api');

// JWT Configuration
define('JWT_SECRET', 'attendify_secret_key_change_in_production_2024');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400); // 24 hours in seconds
define('JWT_REFRESH_EXPIRY', 604800); // 7 days in seconds

// Attendance Configuration
define('DEFAULT_OFFICE_RADIUS', 200); // meters
define('LATE_THRESHOLD_MINUTES', 15);
define('HALF_DAY_HOURS', 4);
define('FULL_DAY_HOURS', 8);
define('DEFAULT_WORK_START', '09:00:00');
define('DEFAULT_WORK_END', '18:00:00');

// Subscription Plans
define('PLANS', [
    'trial' => [
        'name'          => 'Trial',
        'max_employees' => 5,
        'duration_days' => 14,
        'price'         => 0,
        'features'      => ['attendance', 'basic_reports']
    ],
    'basic' => [
        'name'          => 'Basic',
        'max_employees' => 25,
        'duration_days' => 30,
        'price'         => 999,
        'features'      => ['attendance', 'basic_reports', 'departments']
    ],
    'pro' => [
        'name'          => 'Pro',
        'max_employees' => 100,
        'duration_days' => 30,
        'price'         => 2999,
        'features'      => ['attendance', 'advanced_reports', 'departments', 'tracking', 'export']
    ],
    'enterprise' => [
        'name'          => 'Enterprise',
        'max_employees' => 999999,
        'duration_days' => 30,
        'price'         => 4999,
        'features'      => ['attendance', 'advanced_reports', 'departments', 'tracking', 'export', 'api_access', 'priority_support']
    ]
]);

// Roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_COMPANY_ADMIN', 'company_admin');
define('ROLE_EMPLOYEE', 'employee');

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// File Upload
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Timezone
define('DEFAULT_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set(DEFAULT_TIMEZONE);
