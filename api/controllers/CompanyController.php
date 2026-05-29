<?php
/**
 * Company Controller — Super Admin manages companies
 */

class CompanyController {

    public static function index(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN]);
        $filters = [
            'status'   => $_GET['status'] ?? null,
            'search'   => $_GET['search'] ?? null,
            'plan'     => $_GET['plan'] ?? null,
            'page'     => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE
        ];
        $companyModel = new Company();
        $result = $companyModel->findAll($filters);
        // Add employee count to each company
        $userModel = new User();
        foreach ($result['data'] as &$c) {
            $c['employee_count'] = $userModel->countByCompany($c['id']);
        }
        Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
    }

    public static function show(int $id): void {
        $auth = authenticate();
        if ($auth['role'] !== ROLE_SUPER_ADMIN) requireCompany($auth, $id);
        $companyModel = new Company();
        $company = $companyModel->findById($id);
        if (!$company) Response::error('Company not found', 404);
        $userModel = new User();
        $company['employee_count'] = $userModel->countByCompany($id);
        Response::success($company);
    }

    public static function update(int $id): void {
        $auth = authenticate();
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $id);
        else requireRole($auth, [ROLE_SUPER_ADMIN]);
        $body = getRequestBody();
        $companyModel = new Company();
        $companyModel->update($id, $body);
        Response::success($companyModel->findById($id), 'Company updated');
    }

    public static function updateStatus(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN]);
        $body = getRequestBody();
        $v = new Validator();
        $v->required('status', $body['status'] ?? '')->inArray('status', $body['status'] ?? '', ['active', 'inactive', 'pending']);
        $v->validate();
        $companyModel = new Company();
        $companyModel->update($id, ['status' => $body['status']]);
        Response::success(null, 'Company status updated');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN]);
        $companyModel = new Company();
        $companyModel->delete($id);
        Response::success(null, 'Company deleted');
    }
}
