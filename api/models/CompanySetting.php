<?php
require_once __DIR__ . '/../config/database.php';

class CompanySetting {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByCompanyId(int $companyId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM company_settings WHERE company_id = ?");
        $stmt->execute([$companyId]);
        $result = $stmt->fetch();
        if ($result && !empty($result['working_days'])) {
            $result['working_days'] = json_decode($result['working_days'], true);
        }
        return $result ?: null;
    }

    public function update(int $companyId, array $data): bool {
        $updates = [];
        $params = [];
        
        $allowedFields = [
            'work_start_time', 
            'work_end_time', 
            'late_threshold_minutes', 
            'half_day_hours', 
            'full_day_hours', 
            'working_days', 
            'timezone'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                if ($field === 'working_days' && is_array($data[$field])) {
                    $params[":$field"] = json_encode($data[$field]);
                } else {
                    $params[":$field"] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) return false;
        
        $params[':company_id'] = $companyId;
        $sql = "UPDATE company_settings SET " . implode(', ', $updates) . " WHERE company_id = :company_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
