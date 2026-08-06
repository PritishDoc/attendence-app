<?php
/**
 * Company Document Type Controller — Company Admin manages required documents
 */

require_once __DIR__ . '/../models/CompanyDocumentType.php';

class CompanyDocumentTypeController {

    public static function index(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN, ROLE_EMPLOYEE, ROLE_MANAGER]);
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required for super admin', 400);
        }

        $model = new CompanyDocumentType();
        $result = $model->findAll($companyId);
        
        Response::success($result, 'Document types retrieved successfully');
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
            'is_required' => isset($body['is_required']) ? (int)$body['is_required'] : 1
        ];

        $model = new CompanyDocumentType();
        try {
            $id = $model->create($data);
            Response::success($model->findById($id), 'Document type created successfully', 201);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_doc') !== false) {
                Response::error('A document type with this name already exists', 409);
            }
            throw $e;
        }
    }

    public static function update(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $model = new CompanyDocumentType();
        $doc = $model->findById($id);

        if (!$doc) Response::error('Document type not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $doc['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access', 403);
        }

        $body = getRequestBody();
        $v = new Validator();
        
        if (isset($body['name'])) {
            $v->required('name', $body['name'])->length('name', $body['name'], 2, 100);
        }
        $v->validate();

        $updates = [];
        if (isset($body['name'])) $updates['name'] = $body['name'];
        if (isset($body['is_required'])) $updates['is_required'] = (int)$body['is_required'];

        if (!empty($updates)) {
            try {
                $model->update($id, $updates);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'unique_doc') !== false) {
                    Response::error('A document type with this name already exists', 409);
                }
                throw $e;
            }
        }

        Response::success($model->findById($id), 'Document type updated successfully');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $model = new CompanyDocumentType();
        $doc = $model->findById($id);

        if (!$doc) Response::error('Document type not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $doc['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access', 403);
        }

        $model->delete($id);
        Response::success(null, 'Document type deleted successfully');
    }
}
