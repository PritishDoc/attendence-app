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
            'role' => 'employee', 'department_id' => $_GET['department_id'] ?? null,
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
        
        self::validateRelations($companyId, $body);

        $empCode = $body['employee_id_code'] ?? 'EMP-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $id = $userModel->create([
            'company_id' => $companyId, 'name' => $body['name'], 'email' => $body['email'],
            'phone' => $body['phone'] ?? null,
            'password_hash' => password_hash($body['password'], PASSWORD_BCRYPT),
            'role' => ROLE_EMPLOYEE, 'department_id' => $body['department_id'] ?? null,
            'designation_id' => $body['designation_id'] ?? null, 'manager_id' => $body['manager_id'] ?? null,
            'branch_id' => $body['branch_id'] ?? null,
            'employee_id_code' => $empCode
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
        self::validateRelations($employee['company_id'], $body, $id);
        $userModel->update($id, $body);
        if (!empty($body['password'])) {
            $userModel->updatePassword($id, password_hash($body['password'], PASSWORD_BCRYPT));
        }
        Response::success($userModel->findById($id), 'Employee updated');
    }

    public static function activate(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $userModel = new User();
        $employee = $userModel->findById($id);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['user_id'] == $id) Response::error('Cannot activate yourself', 400);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        
        $userModel->updateStatus($id, 'active');
        Response::success(null, 'Employee activated successfully');
    }

    public static function deactivate(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $userModel = new User();
        $employee = $userModel->findById($id);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['user_id'] == $id) Response::error('Cannot deactivate yourself', 400);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        
        $userModel->updateStatus($id, 'inactive');
        Response::success(null, 'Employee deactivated successfully');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $userModel = new User();
        $employee = $userModel->findById($id);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['user_id'] == $id) Response::error('Cannot delete yourself', 400);
        if ($employee['role'] === ROLE_COMPANY_ADMIN && $auth['role'] !== ROLE_SUPER_ADMIN) {
            Response::error('Cannot delete company admin', 403);
        }
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        
        if (isset($_GET['force']) && $_GET['force'] === 'true') {
            $userModel->delete($id);
            $msg = 'Employee permanently deleted';
        } else {
            $userModel->softDelete($id);
            $msg = 'Employee deleted';
        }
        Response::success(null, $msg);
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

    private static function validateRelations(int $companyId, array $body, ?int $currentEmployeeId = null): void {
        $db = Database::getInstance()->getConnection();
        
        if (!empty($body['department_id'])) {
            $stmt = $db->prepare("SELECT id FROM departments WHERE id = ? AND company_id = ?");
            $stmt->execute([$body['department_id'], $companyId]);
            if (!$stmt->fetch()) Response::error('Invalid department', 400);
        }
        
        if (!empty($body['designation_id'])) {
            $stmt = $db->prepare("SELECT id FROM designations WHERE id = ? AND company_id = ?");
            $stmt->execute([$body['designation_id'], $companyId]);
            if (!$stmt->fetch()) Response::error('Invalid designation', 400);
        }
        
        if (!empty($body['branch_id'])) {
            $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND company_id = ? AND status = 'active'");
            $stmt->execute([$body['branch_id'], $companyId]);
            if (!$stmt->fetch()) Response::error('Invalid or inactive branch', 400);
        }
        
        if (!empty($body['manager_id'])) {
            if ($currentEmployeeId && $body['manager_id'] == $currentEmployeeId) {
                Response::error('Employee cannot be their own manager', 400);
            }
            
            $stmt = $db->prepare("SELECT id, manager_id FROM users WHERE id = ? AND company_id = ? AND role = 'employee' AND status = 'active' AND deleted_at IS NULL");
            $stmt->execute([$body['manager_id'], $companyId]);
            $manager = $stmt->fetch();
            if (!$manager) Response::error('Invalid, inactive, or deleted manager', 400);
            
            if ($currentEmployeeId) {
                if ($manager['manager_id'] == $currentEmployeeId) {
                    Response::error('Manager loop detected (A -> B -> A)', 400);
                }
            }
        }
    }
}
