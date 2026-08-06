<?php
require_once __DIR__ . '/../config/database.php';

class CompanyLeavePolicy {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll(int $companyId, array $filters = []): array {
        $where = ['company_id = ?'];
        $params = [$companyId];

        if (!empty($filters['year'])) {
            $where[] = 'leave_year = ?';
            $params[] = $filters['year'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Pagination
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['per_page']) ? (int)$filters['per_page'] : 50;
        $offset = ($page - 1) * $perPage;

        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM company_leave_policies $whereClause";
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get data
        $query = "SELECT * FROM company_leave_policies $whereClause ORDER BY leave_year DESC, leave_type ASC LIMIT ? OFFSET ?";
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
        $stmt = $this->db->prepare("SELECT * FROM company_leave_policies WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO company_leave_policies (company_id, leave_type, leave_year, allocated_days, is_paid) 
            VALUES (:company_id, :leave_type, :leave_year, :allocated_days, :is_paid)
        ");
        $stmt->execute([
            ':company_id' => $data['company_id'],
            ':leave_type' => $data['leave_type'],
            ':leave_year' => $data['leave_year'],
            ':allocated_days' => $data['allocated_days'],
            ':is_paid' => $data['is_paid'] ?? 1
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $updates = [];
        $params = [];
        
        $allowedFields = ['allocated_days', 'is_paid'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) return false;
        
        $params[':id'] = $id;
        $sql = "UPDATE company_leave_policies SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM company_leave_policies WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
