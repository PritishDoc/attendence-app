<?php
require_once __DIR__ . '/../config/Database.php';

class HierarchyService {
    /**
     * Provides optional org_path generation for organizational grouping (e.g., departments).
     * Currently, since there is no manager hierarchy, org_path is maintained only for 
     * flat grouping and does not enforce reporting relationships.
     * 
     * @param int $employeeId
     * @param int $companyId
     * @return void
     */
    public static function rebuildOrgPath(int $employeeId, int $companyId): void {
        $db = Database::getInstance()->getConnection();
        
        // As manager hierarchy is removed, org_path can just be the employee's own ID or department based.
        // Keeping it simple as just the employee's ID for flat grouping.
        $newOrgPath = (string)$employeeId;
        
        $updateStmt = $db->prepare("UPDATE users SET org_path = :org_path WHERE id = :employee_id AND company_id = :company_id");
        $updateStmt->execute([
            ':org_path' => $newOrgPath,
            ':employee_id' => $employeeId,
            ':company_id' => $companyId
        ]);
    }
}
