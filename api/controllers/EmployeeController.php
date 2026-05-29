<?php
/**
 * Employee Controller — Company Admin manages employees
 */

class EmployeeController {

    public static function index(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        if (!$companyId) Response::error('company_id required', 400);
        $filters = [
            'role' => 'employee', 'department' => $_GET['department'] ?? null,
            'status' => $_GET['status'] ?? null, 'search' => $_GET['search'] ?? null,
            'page' => $_GET['page'] ?? 1, 'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE
        ];
        $userModel = new User();
        $result = $userModel->findByCompany($companyId, $filters);
        Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
    }

    public static function show(int $id): void {
        $auth = authenticate();
        $userModel = new User();
        $employee = $userModel->findById($id);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['role'] === ROLE_EMPLOYEE && $auth['user_id'] != $id) Response::error('Access denied', 403);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        Response::success($employee);
    }

    public static function create(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $body = getRequestBody();
        $v = new Validator();
        $v->required('name', $body['name'] ?? '');
        $v->required('email', $body['email'] ?? '')->email('email', $body['email'] ?? '');
        $v->required('password', $body['password'] ?? '')->minLength('password', $body['password'] ?? '', 6);
        $v->validate();

        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($body['company_id'] ?? null) : $auth['company_id'];
        if (!$companyId) Response::error('company_id required', 400);

        // Check max employees
        $userModel = new User();
        $companyModel = new Company();
        $company = $companyModel->findById($companyId);
        $count = $userModel->countByCompany($companyId);
        if ($count >= $company['max_employees']) {
            Response::error("Employee limit reached ({$company['max_employees']}). Upgrade your plan.", 403);
        }

        if ($userModel->findByEmail($body['email'])) Response::error('Email already exists', 409);

        $empCode = $body['employee_id_code'] ?? 'EMP-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $id = $userModel->create([
            'company_id' => $companyId, 'name' => $body['name'], 'email' => $body['email'],
            'phone' => $body['phone'] ?? null,
            'password_hash' => password_hash($body['password'], PASSWORD_BCRYPT),
            'role' => ROLE_EMPLOYEE, 'department' => $body['department'] ?? null,
            'designation' => $body['designation'] ?? null, 'employee_id_code' => $empCode
        ]);
        Response::success($userModel->findById($id), 'Employee created', 201);
    }

    public static function update(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $userModel = new User();
        $employee = $userModel->findById($id);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        $body = getRequestBody();
        $userModel->update($id, $body);
        if (!empty($body['password'])) {
            $userModel->updatePassword($id, password_hash($body['password'], PASSWORD_BCRYPT));
        }
        Response::success($userModel->findById($id), 'Employee updated');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $userModel = new User();
        $employee = $userModel->findById($id);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        $userModel->delete($id);
        Response::success(null, 'Employee deleted');
    }

    public static function resetDevice(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $userModel = new User();
        $employee = $userModel->findById($id);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET device_uuid = NULL WHERE id = ?");
        $stmt->execute([$id]);
        
        Response::success(null, 'Device lock reset successfully. The employee can now log in on a new device.');
    }
}
