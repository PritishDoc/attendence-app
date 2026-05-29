<?php
/**
 * Company Model
 */

class Company {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        return $c ?: null;
    }

    public function findAll(array $filters = []): array {
        $where = "1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where .= " AND (company_name LIKE ? OR email LIKE ?)";
            $s = "%{$filters['search']}%";
            $params = array_merge($params, [$s, $s]);
        }
        if (!empty($filters['plan'])) {
            $where .= " AND subscription_plan = ?";
            $params[] = $filters['plan'];
        }

        $page = $filters['page'] ?? 1;
        $perPage = min($filters['per_page'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM companies WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT * FROM companies WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO companies (company_name, email, phone, address, office_latitude, office_longitude, office_radius, subscription_plan, subscription_expiry, max_employees, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['company_name'], $data['email'], $data['phone'] ?? null,
            $data['address'] ?? null, $data['office_latitude'] ?? null,
            $data['office_longitude'] ?? null, $data['office_radius'] ?? DEFAULT_OFFICE_RADIUS,
            $data['subscription_plan'] ?? 'trial',
            $data['subscription_expiry'] ?? date('Y-m-d', strtotime('+14 days')),
            $data['max_employees'] ?? 5, $data['status'] ?? 'pending'
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        $allowed = ['company_name', 'email', 'phone', 'address', 'logo_url', 'office_latitude', 'office_longitude', 'office_radius', 'subscription_plan', 'subscription_expiry', 'max_employees', 'status'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE companies SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM companies WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function countAll(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    }

    public function countByStatus(string $status): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM companies WHERE status = ?");
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }
}
