<?php
require_once __DIR__ . '/../config/database.php';

class Holiday {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll(int $companyId, array $filters = []): array {
        $where = ['company_id = ?'];
        $params = [$companyId];

        if (!empty($filters['year'])) {
            $where[] = 'YEAR(holiday_date) = ?';
            $params[] = $filters['year'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Pagination
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['per_page']) ? (int)$filters['per_page'] : 50;
        $offset = ($page - 1) * $perPage;

        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM company_holidays $whereClause";
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get data
        $query = "SELECT * FROM company_holidays $whereClause ORDER BY holiday_date ASC LIMIT ? OFFSET ?";
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
        $stmt = $this->db->prepare("SELECT * FROM company_holidays WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO company_holidays (company_id, holiday_date, name) 
            VALUES (:company_id, :holiday_date, :name)
        ");
        $stmt->execute([
            ':company_id' => $data['company_id'],
            ':holiday_date' => $data['holiday_date'],
            ':name' => $data['name']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $updates = [];
        $params = [];
        
        $allowedFields = ['holiday_date', 'name'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) return false;
        
        $params[':id'] = $id;
        $sql = "UPDATE company_holidays SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM company_holidays WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
