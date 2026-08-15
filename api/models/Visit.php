<?php
require_once __DIR__ . '/../config/database.php';

class Visit {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(array $data): int {
        $query = "INSERT INTO visits (company_id, assignee_id, co_assignee_id, assigned_by, customer_name, address, visit_purpose, product, visit_date, visit_time, status) 
                  VALUES (:company_id, :assignee_id, :co_assignee_id, :assigned_by, :customer_name, :address, :visit_purpose, :product, :visit_date, :visit_time, :status)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':company_id'     => $data['company_id'],
            ':assignee_id'    => $data['assignee_id'],
            ':co_assignee_id' => $data['co_assignee_id'] ?? null,
            ':assigned_by'    => $data['assigned_by'],
            ':customer_name'  => $data['customer_name'],
            ':address'        => $data['address'] ?? null,
            ':visit_purpose'  => $data['visit_purpose'] ?? null,
            ':product'        => $data['product'] ?? null,
            ':visit_date'     => $data['visit_date'],
            ':visit_time'     => $data['visit_time'],
            ':status'         => $data['status'] ?? 'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateCheckIn(int $id, array $data): bool {
        $query = "UPDATE visits 
                  SET status = 'in_progress', 
                      checkin_time = :checkin_time, 
                      checkin_lat = :checkin_lat, 
                      checkin_lng = :checkin_lng, 
                      checkin_selfie = :checkin_selfie 
                  WHERE id = :id AND status = 'pending'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':id'             => $id,
            ':checkin_time'   => $data['checkin_time'],
            ':checkin_lat'    => $data['checkin_lat'],
            ':checkin_lng'    => $data['checkin_lng'],
            ':checkin_selfie' => $data['checkin_selfie'],
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updateCheckOut(int $id, array $data): bool {
        $query = "UPDATE visits 
                  SET status = 'completed', 
                      checkout_time = :checkout_time, 
                      checkout_lat = :checkout_lat, 
                      checkout_lng = :checkout_lng, 
                      checkout_selfie = :checkout_selfie,
                      completed_at = :completed_at
                  WHERE id = :id AND status = 'in_progress'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':id'              => $id,
            ':checkout_time'   => $data['checkout_time'],
            ':checkout_lat'    => $data['checkout_lat'],
            ':checkout_lng'    => $data['checkout_lng'],
            ':checkout_selfie' => $data['checkout_selfie'],
            ':completed_at'    => $data['completed_at'],
        ]);
        return $stmt->rowCount() > 0;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM visits WHERE id = ?");
        $stmt->execute([$id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        return $visit ?: null;
    }

    public function findAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = ?';
            $params[] = $filters['company_id'];
        }

        if (!empty($filters['assignee_id'])) {
            $where[] = 'assignee_id = ?';
            $params[] = $filters['assignee_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT * FROM visits $whereClause ORDER BY visit_date DESC, visit_time DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(array $filters): array {
        $where = [];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = ?';
            $params[] = $filters['company_id'];
        }

        if (!empty($filters['assignee_id'])) {
            $where[] = 'assignee_id = ?';
            $params[] = $filters['assignee_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'pending' AND visit_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming
                  FROM visits 
                  $whereClause";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result : ['total' => 0, 'completed' => 0, 'pending' => 0, 'upcoming' => 0];
    }
}
