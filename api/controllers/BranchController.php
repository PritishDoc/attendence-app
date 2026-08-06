<?php
/**
 * Branch Controller — Company Admin manages branches
 */

require_once __DIR__ . '/../models/Branch.php';

class BranchController {

    public static function index(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required for super admin', 400);
        }

        $filters = [
            'company_id' => $companyId,
            'status'   => $_GET['status'] ?? null,
            'search'   => $_GET['search'] ?? null,
            'page'     => $_GET['page'] ?? 1,
            'per_page' => $_GET['limit'] ?? DEFAULT_PAGE_SIZE
        ];

        $branchModel = new Branch();
        $result = $branchModel->findAll($filters);
        
        Response::success($result['data'], 'Branches retrieved successfully');
    }

    public static function show(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);

        $branchModel = new Branch();
        $branch = $branchModel->findById($id);

        if (!$branch) Response::error('Branch not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $branch['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access to this branch', 403);
        }

        Response::success($branch);
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

        $v->required('name', $body['name'] ?? '')->length('name', $body['name'] ?? '', 2, 100);
        $v->validate();

        $data = [
            'company_id' => $companyId,
            'name' => $body['name'],
            'location' => $body['location'] ?? null,
            'status' => $body['status'] ?? 'active'
        ];

        $branchModel = new Branch();
        try {
            $id = $branchModel->create($data);
            Response::success($branchModel->findById($id), 'Branch created successfully', 201);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_branch') !== false) {
                Response::error('A branch with this name already exists for the company', 409);
            }
            throw $e;
        }
    }

    public static function update(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $branchModel = new Branch();
        $branch = $branchModel->findById($id);

        if (!$branch) Response::error('Branch not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $branch['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access to this branch', 403);
        }

        $body = getRequestBody();
        $v = new Validator();
        
        if (isset($body['name'])) {
            $v->required('name', $body['name'])->length('name', $body['name'], 2, 100);
        }
        if (isset($body['status'])) {
            $v->inArray('status', $body['status'], ['active', 'inactive']);
        }
        $v->validate();

        $updates = [];
        if (isset($body['name'])) $updates['name'] = $body['name'];
        if (isset($body['location'])) $updates['location'] = $body['location'];
        if (isset($body['status'])) $updates['status'] = $body['status'];

        if (!empty($updates)) {
            try {
                $branchModel->update($id, $updates);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'unique_branch') !== false) {
                    Response::error('A branch with this name already exists', 409);
                }
                throw $e;
            }
        }

        Response::success($branchModel->findById($id), 'Branch updated successfully');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $branchModel = new Branch();
        $branch = $branchModel->findById($id);

        if (!$branch) Response::error('Branch not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $branch['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access to this branch', 403);
        }

        // Optional: Check if branch is assigned to users before deleting.
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            Response::error('Cannot delete branch because it is assigned to one or more active employees.', 409);
        }

        $branchModel->delete($id);
        Response::success(null, 'Branch deleted successfully');
    }
}
