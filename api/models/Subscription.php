<?php
/**
 * Subscription Model
 */

class Subscription {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByCompany(int $companyId): array {
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE company_id = ? ORDER BY created_at DESC");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    public function getActive(int $companyId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE company_id = ? AND is_active = 1 AND expiry_date >= CURDATE() ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$companyId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO subscriptions (company_id, plan, amount, currency, payment_status, payment_id, start_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['company_id'], $data['plan'], $data['amount'] ?? 0,
            $data['currency'] ?? 'INR', $data['payment_status'] ?? 'pending',
            $data['payment_id'] ?? null, $data['start_date'] ?? date('Y-m-d'),
            $data['expiry_date']
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getTotalRevenue(): float {
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE payment_status = 'paid'");
        return (float) $stmt->fetchColumn();
    }
}
