<?php
/**
 * Company Leave Policy Controller — Company Admin manages leave policies
 */

require_once __DIR__ . '/../models/CompanyLeavePolicy.php';

class CompanyLeavePolicyController {

    public static function index(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required for super admin', 400);
        }

        $filters = [
            'year'     => $_GET['year'] ?? null,
            'page'     => $_GET['page'] ?? 1,
            'per_page' => $_GET['limit'] ?? 50
        ];

        $policyModel = new CompanyLeavePolicy();
        $result = $policyModel->findAll($companyId, $filters);
        
        Response::success($result['data'], 'Leave policies retrieved successfully');
    }

    public static function create(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $body = getRequestBody();
        $v = new Validator();
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($body['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required', 400);
        }

        $v->required('leave_type', $body['leave_type'] ?? '')->inArray('leave_type', $body['leave_type'] ?? '', ['CL', 'SL', 'CO', 'LOP', 'EL', 'ML']);
        $v->required('leave_year', $body['leave_year'] ?? '');
        $v->required('allocated_days', $body['allocated_days'] ?? '');
        $v->validate();

        $data = [
            'company_id' => $companyId,
            'leave_type' => $body['leave_type'],
            'leave_year' => $body['leave_year'],
            'allocated_days' => $body['allocated_days'],
            'is_paid' => isset($body['is_paid']) ? (int)$body['is_paid'] : 1
        ];

        $policyModel = new CompanyLeavePolicy();
        try {
            $id = $policyModel->create($data);
            Response::success($policyModel->findById($id), 'Leave policy created successfully', 201);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_company_leave') !== false) {
                Response::error('A leave policy for this type and year already exists.', 409);
            }
            throw $e;
        }
    }

    public static function update(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $policyModel = new CompanyLeavePolicy();
        $policy = $policyModel->findById($id);

        if (!$policy) Response::error('Leave policy not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $policy['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access', 403);
        }

        $body = getRequestBody();
        $updates = [];
        
        if (isset($body['allocated_days'])) {
            $updates['allocated_days'] = $body['allocated_days'];
        }
        if (isset($body['is_paid'])) {
            $updates['is_paid'] = (int)$body['is_paid'];
        }

        if (!empty($updates)) {
            $policyModel->update($id, $updates);
        }

        Response::success($policyModel->findById($id), 'Leave policy updated successfully');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $policyModel = new CompanyLeavePolicy();
        $policy = $policyModel->findById($id);

        if (!$policy) Response::error('Leave policy not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $policy['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access', 403);
        }

        $policyModel->delete($id);
        Response::success(null, 'Leave policy deleted successfully');
    }
}
