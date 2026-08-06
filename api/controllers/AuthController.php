<?php
/**
 * Auth Controller — Login, Register, Profile
 */

class AuthController
{

    public static function login(): void
    {
        $body = getRequestBody();
        $v = new Validator();
        
        $identifier = $body['identifier'] ?? $body['email'] ?? $body['phone'] ?? '';
        $v->required('identifier (email or phone)', $identifier);
        $v->required('password', $body['password'] ?? '');
        $v->validate();

        $userModel = new User();
        $user = $userModel->findByPhoneOrEmail($identifier);

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error('Account is deactivated. Contact your administrator.', 403);
        }

        // Device Lock validation for employees (anti-scam protection)
        if ($user['role'] === 'employee') {
            $clientDeviceUuid = $body['device_uuid'] ?? null;
            if (empty($clientDeviceUuid)) {
                Response::error('Device signature is missing. Please log in from the Attendify Web App.', 400);
            }

            // If they don't have a registered device yet, bind this device!
            if (empty($user['device_uuid'])) {
                $userModel->update($user['id'], ['device_uuid' => $clientDeviceUuid]);
                $user['device_uuid'] = $clientDeviceUuid;
            } else if ($user['device_uuid'] !== $clientDeviceUuid) {
                Response::error('This account is locked to another mobile device. Please contact your company administrator to reset your registered device.', 403);
            }
        }

        // Check company status for non-super-admin
        if ($user['role'] !== ROLE_SUPER_ADMIN && $user['company_id']) {
            $companyModel = new Company();
            $company = $companyModel->findById($user['company_id']);
            if (!$company || $company['status'] !== 'active') {
                Response::error('Company account is inactive. Contact support.', 403);
            }
        }

        $userModel->updateLastLogin($user['id']);

        $accessToken = JWT::generate([
            'user_id' => $user['id'],
            'company_id' => $user['company_id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'token_version' => $user['token_version'],
            'type' => 'access'
        ], JWT_EXPIRY);

        $refreshToken = JWT::generate([
            'user_id' => $user['id'],
            'type' => 'refresh',
            'absolute_exp' => time() + JWT_ABSOLUTE_EXPIRY
        ], JWT_REFRESH_EXPIRY);
        
        $userModel->update($user['id'], [
            'refresh_token_hash' => password_hash($refreshToken, PASSWORD_BCRYPT),
            'previous_refresh_token_hash' => null,
            'grace_period_expires_at' => null
        ]);

        $isSecure = !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        
        setcookie('refresh_token', $refreshToken, [
            'expires' => time() + JWT_REFRESH_EXPIRY,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        Response::success([
            'token' => $accessToken,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'company_id' => $user['company_id'],
                'department' => $user['department'],
                'avatar_url' => $user['avatar_url'],
                'is_first_login' => $user['is_first_login'] ?? 1
            ]
        ], 'Login successful');
    }

    public static function register(): void
    {
        $body = getRequestBody();
        $v = new Validator();
        $v->required('company_name', $body['company_name'] ?? '');
        $v->required('name', $body['name'] ?? '');
        $v->required('email', $body['email'] ?? '')->email('email', $body['email'] ?? '');
        $v->required('password', $body['password'] ?? '')->minLength('password', $body['password'] ?? '', 6);
        $v->validate();

        $userModel = new User();
        if ($userModel->findByEmail($body['email'])) {
            Response::error('Email already registered', 409);
        }

        $companyModel = new Company();
        $companyId = $companyModel->create([
            'company_name' => $body['company_name'],
            'email' => $body['email'],
            'phone' => $body['phone'] ?? null,
            'address' => $body['address'] ?? null,
            'office_latitude' => $body['office_latitude'] ?? null,
            'office_longitude' => $body['office_longitude'] ?? null,
            'status' => 'active'
        ]);

        // Create company settings
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO company_settings (company_id) VALUES (?)");
        $stmt->execute([$companyId]);

        // Create default departments
        $deptModel = new Department();
        foreach (['General', 'HR', 'Engineering', 'Sales', 'Field Staff'] as $dept) {
            $deptModel->create(['company_id' => $companyId, 'name' => $dept]);
        }

        $userId = $userModel->create([
            'company_id' => $companyId,
            'name' => $body['name'],
            'email' => $body['email'],
            'password_hash' => password_hash($body['password'], PASSWORD_BCRYPT),
            'role' => ROLE_COMPANY_ADMIN,
            'department' => 'Management',
            'designation' => 'Admin'
        ]);

        $accessToken = JWT::generate([
            'user_id' => $userId,
            'company_id' => $companyId,
            'role' => ROLE_COMPANY_ADMIN,
            'email' => $body['email'],
            'token_version' => 1,
            'type' => 'access'
        ], JWT_EXPIRY);

        $refreshToken = JWT::generate([
            'user_id' => $userId,
            'type' => 'refresh',
            'absolute_exp' => time() + JWT_ABSOLUTE_EXPIRY
        ], JWT_REFRESH_EXPIRY);
        
        $userModel->update($userId, [
            'refresh_token_hash' => password_hash($refreshToken, PASSWORD_BCRYPT),
            'previous_refresh_token_hash' => null,
            'grace_period_expires_at' => null
        ]);

        $isSecure = !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        
        setcookie('refresh_token', $refreshToken, [
            'expires' => time() + JWT_REFRESH_EXPIRY,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        Response::success([
            'token' => $accessToken,
            'user' => ['id' => $userId, 'name' => $body['name'], 'email' => $body['email'], 'role' => ROLE_COMPANY_ADMIN, 'company_id' => $companyId],
            'company_id' => $companyId
        ], 'Registration successful', 201);
    }

    public static function me(): void
    {
        $auth = authenticate();
        $userModel = new User();
        $user = $userModel->findById($auth['user_id']);
        if (!$user)
            Response::error('User not found', 404);

        $data = $user;
        if ($user['company_id']) {
            $companyModel = new Company();
            $data['company'] = $companyModel->findById($user['company_id']);
        }
        Response::success($data);
    }

    public static function changeInitialPassword(): void
    {
        $auth = authenticate();
        $body = getRequestBody();
        $v = new Validator();
        $v->required('new_password', $body['new_password'] ?? '')->minLength('new_password', $body['new_password'] ?? '', 6);
        $v->validate();

        $userModel = new User();
        $user = $userModel->findById($auth['user_id']);
        if (!$user) {
            Response::error('User not found', 404);
        }

        if (!$user['is_first_login']) {
            Response::error('Password has already been changed or not required.', 400);
        }

        $hashedPassword = password_hash($body['new_password'], PASSWORD_BCRYPT);
        $userModel->updatePassword($auth['user_id'], $hashedPassword);
        $userModel->update($auth['user_id'], ['is_first_login' => 0]);

        Response::success(null, 'Password updated successfully');
    }

    public static function refreshToken(): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;
        if (!$refreshToken) {
            Response::error('No refresh token provided', 401);
        }

        $payload = JWT::verify($refreshToken);
        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            Response::error('Invalid token', 401);
        }

        if (isset($payload['absolute_exp']) && time() > $payload['absolute_exp']) {
            Response::error('Session expired completely. Please log in again.', 401);
        }

        $userModel = new User();
        $user = $userModel->findById($payload['user_id']);
        if (!$user || $user['status'] !== 'active') {
            Response::error('User inactive or not found', 401);
        }

        // Check Race Condition
        if ($user['previous_refresh_token_hash'] && password_verify($refreshToken, $user['previous_refresh_token_hash'])) {
            if ($user['grace_period_expires_at'] && strtotime($user['grace_period_expires_at']) > time()) {
                // Innocent race condition: grant new access token, but do NOT rotate refresh token
                $accessToken = JWT::generate([
                    'user_id' => $user['id'],
                    'company_id' => $user['company_id'],
                    'role' => $user['role'],
                    'email' => $user['email'],
                    'token_version' => $user['token_version'],
                    'type' => 'access'
                ], JWT_EXPIRY);
                Response::success(['token' => $accessToken], 'Access token refreshed (Grace)');
            }
        }

        // Check if token matches current active hash
        if (!$user['refresh_token_hash'] || !password_verify($refreshToken, $user['refresh_token_hash'])) {
            // Token Reuse Attack Detected!
            $userModel->update($user['id'], [
                'refresh_token_hash' => null,
                'previous_refresh_token_hash' => null,
                'grace_period_expires_at' => null
            ]);
            Response::error('Suspicious activity detected. You have been logged out of all devices.', 401);
        }

        // Normal Rotation
        $accessToken = JWT::generate([
            'user_id' => $user['id'],
            'company_id' => $user['company_id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'token_version' => $user['token_version'],
            'type' => 'access'
        ], JWT_EXPIRY);

        $newRefreshToken = JWT::generate([
            'user_id' => $user['id'],
            'type' => 'refresh',
            'absolute_exp' => $payload['absolute_exp'] ?? (time() + JWT_ABSOLUTE_EXPIRY)
        ], JWT_REFRESH_EXPIRY);
        
        $userModel->update($user['id'], [
            'previous_refresh_token_hash' => $user['refresh_token_hash'],
            'grace_period_expires_at' => date('Y-m-d H:i:s', time() + 60), // 60 seconds grace
            'refresh_token_hash' => password_hash($newRefreshToken, PASSWORD_BCRYPT)
        ]);

        $isSecure = !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        
        setcookie('refresh_token', $newRefreshToken, [
            'expires' => time() + JWT_REFRESH_EXPIRY,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        Response::success(['token' => $accessToken], 'Tokens refreshed successfully');
    }

    public static function logout(): void
    {
        $userId = null;
        $userModel = new User();

        // 1. Try extracting from Access Token (Authorization header)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $accessPayload = JWT::verify($matches[1]);
            if ($accessPayload && isset($accessPayload['user_id'])) {
                $userId = $accessPayload['user_id'];
            }
        }

        // 2. Try extracting and strictly validating from Refresh Token Cookie
        $refreshToken = $_COOKIE['refresh_token'] ?? null;
        if ($refreshToken) {
            $refreshPayload = JWT::verify($refreshToken);
            if ($refreshPayload && isset($refreshPayload['user_id'])) {
                $user = $userModel->findById($refreshPayload['user_id']);
                // Guard: Only trust if user exists and token matches active DB hash
                if ($user && $user['refresh_token_hash'] && password_verify($refreshToken, $user['refresh_token_hash'])) {
                    $userId = $refreshPayload['user_id'];
                }
            }
        }
        
        // 3. If a valid identity was proven, wipe the DB session
        if ($userId) {
            $userModel->update($userId, [
                'refresh_token_hash' => null,
                'previous_refresh_token_hash' => null,
                'grace_period_expires_at' => null
            ]);
        }

        $isSecure = !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        
        // 4. Unconditionally destroy the secure cookie
        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        // 5. Always return success to allow graceful frontend teardown
        Response::success(null, 'Logged out successfully');
    }
}
