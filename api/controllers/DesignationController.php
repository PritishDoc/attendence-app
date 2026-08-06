<?php
/**
 * Designation Controller
 */
class DesignationController {
    
    public static function index(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        if (!$companyId) Response::error('company_id required', 400);
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM designations WHERE company_id = ? ORDER BY name ASC");
        $stmt->execute([$companyId]);
        Response::success($stmt->fetchAll());
    }
    
    public static function create(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $body = getRequestBody();
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($body['company_id'] ?? null) : $auth['company_id'];
        if (!$companyId) Response::error('company_id required', 400);
        
        $v = new Validator();
        $v->required('name', $body['name'] ?? '');
        $v->validate();
        
        $db = Database::getInstance()->getConnection();
        try {
            $stmt = $db->prepare("INSERT INTO designations (company_id, name, description, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$companyId, $body['name'], $body['description'] ?? null, $body['status'] ?? 'active']);
            $id = $db->lastInsertId();
            
            $stmt = $db->prepare("SELECT * FROM designations WHERE id = ?");
            $stmt->execute([$id]);
            Response::success($stmt->fetch(), 'Designation created', 201);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) Response::error('Designation name already exists for this company', 409);
            throw $e;
        }
    }
    
    public static function update(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN]);
        $body = getRequestBody();
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM designations WHERE id = ?");
        $stmt->execute([$id]);
        $designation = $stmt->fetch();
        
        if (!$designation) Response::error('Designation not found', 404);
        requireCompany($auth, $designation['company_id']);
        
        $fields = [];
        $params = [];
        $allowed = ['name', 'description', 'status'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $fields[] = "$field = ?";
                $params[] = $body[$field];
            }
        }
        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        
        try {
            $stmt = $db->prepare("UPDATE designations SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($params);
            
            $stmt = $db->prepare("SELECT * FROM designations WHERE id = ?");
            $stmt->execute([$id]);
            Response::success($stmt->fetch(), 'Designation updated');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) Response::error('Designation name already exists for this company', 409);
            throw $e;
        }
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN]);
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM designations WHERE id = ?");
        $stmt->execute([$id]);
        $designation = $stmt->fetch();
        
        if (!$designation) Response::error('Designation not found', 404);
        requireCompany($auth, $designation['company_id']);
        
        // Check if assigned to any user
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE designation_id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->fetchColumn() > 0) {
            Response::error('Cannot delete designation as it is assigned to one or more users', 400);
        }
        
        $stmt = $db->prepare("DELETE FROM designations WHERE id = ?");
        $stmt->execute([$id]);
        Response::success(null, 'Designation deleted successfully');
    }
}
