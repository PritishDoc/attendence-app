<?php
/**
 * User Model
 */

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByPhoneOrEmail(string $identifier): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByCompany(int $companyId, array $filters = []): array {
        $where = "company_id = ?";
        $params = [$companyId];

        if (!empty($filters['role'])) {
            $where .= " AND role = ?";
            $params[] = $filters['role'];
        }
        if (!empty($filters['department'])) {
            $where .= " AND department = ?";
            $params[] = $filters['department'];
        }
        if (!empty($filters['status'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where .= " AND (name LIKE ? OR email LIKE ? OR employee_id_code LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search]);
        }

        $page = $filters['page'] ?? 1;
        $perPage = min($filters['per_page'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        $offset = ($page - 1) * $perPage;

        // Count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Data
        $stmt = $this->db->prepare("SELECT id, company_id, name, email, phone, role, department, designation, employee_id_code, status, is_first_login, last_login, created_at FROM users WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO users (company_id, name, email, phone, password_hash, role, department, designation, employee_id_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['company_id'] ?? null, $data['name'], $data['email'],
            $data['phone'] ?? null, $data['password_hash'], $data['role'],
            $data['department'] ?? null, $data['designation'] ?? null,
            $data['employee_id_code'] ?? null, $data['status'] ?? 'active'
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        $allowed = ['name', 'email', 'phone', 'department', 'designation', 'employee_id_code', 'status', 'avatar_url', 'device_uuid', 'is_first_login', 'refresh_token_hash', 'previous_refresh_token_hash', 'grace_period_expires_at'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public function updatePassword(int $id, string $hash): bool {
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$hash, $id]);
    }

    public function updateLastLogin(int $id): bool {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function countByCompany(int $companyId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND role = 'employee'");
        $stmt->execute([$companyId]);
        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role != 'super_admin'");
        return (int) $stmt->fetchColumn();
    }
}
