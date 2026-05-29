<?php
/**
 * Auth Controller — Login, Register, Profile
 */

class AuthController {

    public static function login(): void {
        $body = getRequestBody();
        $v = new Validator();
        $v->required('email', $body['email'] ?? '')->email('email', $body['email'] ?? '');
        $v->required('password', $body['password'] ?? '');
        $v->validate();

        $userModel = new User();
        $user = $userModel->findByEmail($body['email']);

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            Response::error('Invalid email or password', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error('Account is deactivated. Contact your administrator.', 403);
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

        $token = JWT::generate([
            'user_id'    => $user['id'],
            'company_id' => $user['company_id'],
            'role'       => $user['role'],
            'email'      => $user['email']
        ]);

        Response::success([
            'token' => $token,
            'user'  => [
                'id'         => $user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'company_id' => $user['company_id'],
                'department' => $user['department'],
                'avatar_url' => $user['avatar_url']
            ]
        ], 'Login successful');
    }

    public static function register(): void {
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
            'company_id'    => $companyId,
            'name'          => $body['name'],
            'email'         => $body['email'],
            'password_hash' => password_hash($body['password'], PASSWORD_BCRYPT),
            'role'          => ROLE_COMPANY_ADMIN,
            'department'    => 'Management',
            'designation'   => 'Admin'
        ]);

        $token = JWT::generate([
            'user_id' => $userId, 'company_id' => $companyId,
            'role' => ROLE_COMPANY_ADMIN, 'email' => $body['email']
        ]);

        Response::success([
            'token'      => $token,
            'user'       => ['id' => $userId, 'name' => $body['name'], 'email' => $body['email'], 'role' => ROLE_COMPANY_ADMIN, 'company_id' => $companyId],
            'company_id' => $companyId
        ], 'Registration successful', 201);
    }

    public static function me(): void {
        $auth = authenticate();
        $userModel = new User();
        $user = $userModel->findById($auth['user_id']);
        if (!$user) Response::error('User not found', 404);

        $data = $user;
        if ($user['company_id']) {
            $companyModel = new Company();
            $data['company'] = $companyModel->findById($user['company_id']);
        }
        Response::success($data);
    }
}
