<?php
/**
 * Department Controller
 */

class DepartmentController {

    public static function index(): void {
        $auth = authenticate();
        $companyId = ($auth['role'] === ROLE_SUPER_ADMIN) ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        if (!$companyId) Response::error('company_id required', 400);
        $deptModel = new Department();
        Response::success($deptModel->findByCompany($companyId));
    }

    public static function create(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $body = getRequestBody();
        $v = new Validator();
        $v->required('name', $body['name'] ?? '');
        $v->validate();
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($body['company_id'] ?? null) : $auth['company_id'];
        $deptModel = new Department();
        $id = $deptModel->create(['company_id' => $companyId, 'name' => $body['name'], 'description' => $body['description'] ?? null]);
        Response::success($deptModel->findById($id), 'Department created', 201);
    }

    public static function update(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $body = getRequestBody();
        $deptModel = new Department();
        $deptModel->update($id, $body);
        Response::success($deptModel->findById($id), 'Department updated');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $deptModel = new Department();
        $deptModel->delete($id);
        Response::success(null, 'Department deleted');
    }
}
