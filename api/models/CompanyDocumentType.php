<?php
require_once __DIR__ . '/../config/database.php';

class CompanyDocumentType {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll(int $companyId): array {
        $stmt = $this->db->prepare("SELECT * FROM company_document_types WHERE company_id = ? ORDER BY name ASC");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM company_document_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO company_document_types (company_id, name, is_required) 
            VALUES (:company_id, :name, :is_required)
        ");
        $stmt->execute([
            ':company_id' => $data['company_id'],
            ':name' => $data['name'],
            ':is_required' => $data['is_required'] ?? 1
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $updates = [];
        $params = [];
        
        $allowedFields = ['name', 'is_required'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) return false;
        
        $params[':id'] = $id;
        $sql = "UPDATE company_document_types SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM company_document_types WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
