<?php
/**
 * Department Model
 */

class Department {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT d.*, (SELECT COUNT(*) FROM users u WHERE u.department = d.name AND u.company_id = d.company_id) as employee_count FROM departments d WHERE d.id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function findByCompany(int $companyId): array {
        $stmt = $this->db->prepare("SELECT d.*, (SELECT COUNT(*) FROM users u WHERE u.department = d.name AND u.company_id = d.company_id AND u.role = 'employee') as employee_count FROM departments d WHERE d.company_id = ? AND d.status = 'active' ORDER BY d.name");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO departments (company_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$data['company_id'], $data['name'], $data['description'] ?? null]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        foreach (['name', 'description', 'status'] as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE departments SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM departments WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
