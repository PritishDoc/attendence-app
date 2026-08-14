<?php
/**
 * Leave Model
 * Handles Leaves, Leave Policies, Balances, and Holidays
 */

class Leave {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // ===========================================
    // 1. Leave Applications
    // ===========================================
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT l.*, u.name as employee_name FROM leaves l JOIN users u ON l.employee_id = u.id WHERE l.id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function applyLeave(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO leaves (employee_id, company_id, leave_type, leave_duration, start_date, end_date, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $data['employee_id'], $data['company_id'], $data['leave_type'], 
            $data['leave_duration'], $data['start_date'], $data['end_date'], 
            $data['reason']
        ]);
        $leaveId = (int) $this->db->lastInsertId();
        
        $this->logAudit($leaveId, 'created', $data['employee_id'], json_encode($data));
        
        return $leaveId;
    }

    public function updateStatus(int $id, string $status, int $adminId, ?string $approvedStart = null, ?string $approvedEnd = null): bool {
        $updateSql = "UPDATE leaves SET status = ?, approved_by = ?, approved_at = NOW()";
        $params = [$status, $adminId];

        if ($status === 'approved') {
            $updateSql .= ", approved_start_date = ?, approved_end_date = ?";
            $params[] = $approvedStart;
            $params[] = $approvedEnd;
        }

        $updateSql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->db->prepare($updateSql);
        $result = $stmt->execute($params);

        if ($result) {
            $changes = ['status' => $status, 'approved_start_date' => $approvedStart, 'approved_end_date' => $approvedEnd];
            $this->logAudit($id, $status, $adminId, json_encode($changes));
        }

        return $result;
    }

    public function softDelete(int $id, int $userId): bool {
        $stmt = $this->db->prepare("UPDATE leaves SET deleted_at = NOW() WHERE id = ? AND status = 'pending'");
        $result = $stmt->execute([$id]);
        if ($result && $stmt->rowCount() > 0) {
            $this->logAudit($id, 'deleted', $userId, null);
            return true;
        }
        return false;
    }

    public function cancelLeave(int $id, int $userId): bool {
        $stmt = $this->db->prepare("UPDATE leaves SET status = 'cancelled' WHERE id = ? AND status = 'approved'");
        $result = $stmt->execute([$id]);
        if ($result && $stmt->rowCount() > 0) {
            $this->logAudit($id, 'cancelled', $userId, null);
            return true;
        }
        return false;
    }

    // ===========================================
    // 2. Fetching & Validation Helpers
    // ===========================================
    public function getLeavesByEmployee(int $employeeId, ?int $year = null): array {
        $sql = "SELECT * FROM leaves WHERE employee_id = ? AND deleted_at IS NULL";
        $params = [$employeeId];
        if ($year) {
            $sql .= " AND (YEAR(start_date) = ? OR YEAR(end_date) = ?)";
            $params[] = $year;
            $params[] = $year;
        }
        $sql .= " ORDER BY start_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getOverlappingLeaves(int $employeeId, string $startDate, string $endDate): array {
        $stmt = $this->db->prepare("
            SELECT * FROM leaves 
            WHERE employee_id = ? 
            AND deleted_at IS NULL 
            AND status IN ('pending', 'under_process', 'approved') 
            AND (start_date <= ? AND end_date >= ?)
        ");
        $stmt->execute([$employeeId, $endDate, $startDate]);
        return $stmt->fetchAll();
    }
    
    public function getAdminLeaves(int $companyId, int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        $stmt = $this->db->prepare("
            SELECT l.*, u.name as employee_name 
            FROM leaves l JOIN users u ON l.employee_id = u.id 
            WHERE l.company_id = ? AND l.deleted_at IS NULL 
            ORDER BY l.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $companyId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ===========================================
    // 3. Balances & Policies
    // ===========================================
    public function getEmployeeBalances(int $employeeId, int $companyId, int $year): array {
        // Fetch existing balances
        $stmt = $this->db->prepare("SELECT * FROM employee_leave_balances WHERE employee_id = ? AND company_id = ? AND leave_year = ?");
        $stmt->execute([$employeeId, $companyId, $year]);
        $existing = $stmt->fetchAll();

        // Fetch company policies for the year
        $stmtPolicy = $this->db->prepare("SELECT * FROM company_leave_policies WHERE company_id = ? AND leave_year = ?");
        $stmtPolicy->execute([$companyId, $year]);
        $policies = $stmtPolicy->fetchAll();

        $existingTypes = array_column($existing, 'leave_type');
        $newRecordsInserted = false;

        foreach ($policies as $policy) {
            if (!in_array($policy['leave_type'], $existingTypes)) {
                $stmtInsert = $this->db->prepare("
                    INSERT INTO employee_leave_balances (employee_id, company_id, leave_type, leave_year, allocated_days, used_days, remaining_days)
                    VALUES (?, ?, ?, ?, ?, 0, ?)
                ");
                $stmtInsert->execute([
                    $employeeId, 
                    $companyId, 
                    $policy['leave_type'], 
                    $year, 
                    $policy['allocated_days'],
                    $policy['allocated_days']
                ]);
                $newRecordsInserted = true;
            }
        }

        if ($newRecordsInserted) {
            $stmt->execute([$employeeId, $companyId, $year]);
            return $stmt->fetchAll();
        }

        return $existing;
    }

    public function getCompanyPolicies(int $companyId, int $year): array {
        $stmt = $this->db->prepare("SELECT * FROM company_leave_policies WHERE company_id = ? AND leave_year = ?");
        $stmt->execute([$companyId, $year]);
        return $stmt->fetchAll();
    }

    public function updateEmployeeBalance(int $employeeId, string $leaveType, int $year, float $daysToDeduct): bool {
        $stmt = $this->db->prepare("
            UPDATE employee_leave_balances 
            SET used_days = used_days + ?, remaining_days = remaining_days - ? 
            WHERE employee_id = ? AND leave_type = ? AND leave_year = ?
        ");
        return $stmt->execute([$daysToDeduct, $daysToDeduct, $employeeId, $leaveType, $year]);
    }

    // ===========================================
    // 4. Audit Logging
    // ===========================================
    private function logAudit(int $leaveId, string $action, int $actorId, ?string $changes = null) {
        $stmt = $this->db->prepare("INSERT INTO leave_audit_logs (leave_id, action, actor_id, changes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$leaveId, $action, $actorId, $changes]);
    }

    // ===========================================
    // 5. Holidays
    // ===========================================
    public function getHolidaysBetween(int $companyId, string $startDate, string $endDate): array {
        $stmt = $this->db->prepare("SELECT holiday_date FROM company_holidays WHERE company_id = ? AND holiday_date BETWEEN ? AND ?");
        $stmt->execute([$companyId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
