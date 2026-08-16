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
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByPhoneOrEmail(string $identifier): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE (email = ? OR phone = ?) AND deleted_at IS NULL");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByCompany(int $companyId, array $filters = []): array {
        $where = "u.company_id = ? AND u.deleted_at IS NULL";
        $params = [$companyId];

        if (!empty($filters['role'])) {
            $where .= " AND u.role = ?";
            $params[] = $filters['role'];
        }
        if (!empty($filters['department_id'])) {
            $where .= " AND u.department_id = ?";
            $params[] = $filters['department_id'];
        }
        if (!empty($filters['status'])) {
            $where .= " AND u.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.employee_id_code LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search]);
        }

        $page = $filters['page'] ?? 1;
        $perPage = min($filters['per_page'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        $offset = ($page - 1) * $perPage;

        // Count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users u WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Data
        $sql = "SELECT u.id, u.company_id, u.name, u.email, u.phone, u.role, 
                       u.department_id, u.designation_id, u.manager_id, u.branch_id,
                       u.employee_id_code, u.status, u.is_first_login, 
                       u.last_login, u.created_at,
                       d.name as department, 
                       des.name as designation,
                       b.name as branch
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN designations des ON u.designation_id = des.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE $where 
                ORDER BY u.created_at DESC 
                LIMIT $perPage OFFSET $offset";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO users (
            company_id, name, email, phone, password_hash, role,
            department_id, designation_id, manager_id, branch_id,
            employee_id_code, status, shift_id, weekoff_policy_id, employee_code, joining_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['company_id'] ?? null, $data['name'], $data['email'],
            $data['phone'] ?? null, $data['password_hash'], $data['role'],
            $data['department_id'] ?? null, $data['designation_id'] ?? null,
            $data['manager_id'] ?? null, $data['branch_id'] ?? null,
            $data['employee_id_code'] ?? null, $data['status'] ?? 'active',
            $data['shift_id'] ?? null, $data['weekoff_policy_id'] ?? null,
            $data['employee_code'] ?? null, $data['joining_date'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        $allowed = ['name', 'email', 'phone', 'department_id', 'designation_id', 'manager_id', 'branch_id', 'employee_id_code', 'status', 'avatar_url', 'device_uuid', 'is_first_login', 'refresh_token_hash', 'previous_refresh_token_hash', 'grace_period_expires_at', 'shift_id', 'weekoff_policy_id', 'employee_code', 'joining_date'];
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

    public function incrementTokenVersion(int $id): bool {
        $stmt = $this->db->prepare("UPDATE users SET token_version = token_version + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function softDelete(int $id): bool {
        $stmt = $this->db->prepare("UPDATE users SET deleted_at = NOW(), status = 'inactive', token_version = token_version + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function updateStatus(int $id, string $status): bool {
        if ($status === 'inactive') {
            $stmt = $this->db->prepare("UPDATE users SET status = ?, token_version = token_version + 1 WHERE id = ?");
        } else {
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
        }
        return $stmt->execute([$status, $id]);
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
        $this->incrementTokenVersion($id);
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function countByCompany(int $companyId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND role = 'employee' AND deleted_at IS NULL");
        $stmt->execute([$companyId]);
        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role != 'super_admin' AND deleted_at IS NULL");
        return (int) $stmt->fetchColumn();
    }
}
