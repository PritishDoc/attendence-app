<?php
require_once __DIR__ . '/../config/database.php';

class Branch {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = ?';
            $params[] = $filters['company_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR location LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Pagination
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['per_page']) ? (int)$filters['per_page'] : 20;
        $offset = ($page - 1) * $perPage;

        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM branches $whereClause";
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get data
        $query = "SELECT * FROM branches $whereClause ORDER BY name ASC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM branches WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO branches (company_id, name, location, status) 
            VALUES (:company_id, :name, :location, :status)
        ");
        $stmt->execute([
            ':company_id' => $data['company_id'],
            ':name' => $data['name'],
            ':location' => $data['location'] ?? null,
            ':status' => $data['status'] ?? 'active'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $updates = [];
        $params = [];
        
        $allowedFields = ['name', 'location', 'status'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) return false;
        
        $params[':id'] = $id;
        $sql = "UPDATE branches SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM branches WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
