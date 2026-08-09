<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

class TeamController {
    
    /**
     * Get a flat list of colleagues in the same company
     */
    public static function getTeam() {
        $user = requireAuth(['employee', 'company', 'super_admin']);
        $db = Database::getInstance()->getConnection();
        
        // Basic team fetch. Depending on requirements, might filter by department_id
        $stmt = $db->prepare("
            SELECT id, name, email, role, department_id, manager_id, org_path
            FROM users 
            WHERE company_id = :company_id 
              AND role = 'employee' 
              AND status = 'active'
        ");
        $stmt->execute([':company_id' => $user['company_id']]);
        
        Response::success(['team' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /**
     * Get the team grouped by organizational path (or flat grouping)
     */
    public static function getStructure() {
        $user = requireAuth(['employee', 'company', 'super_admin']);
        $db = Database::getInstance()->getConnection();

        // Fetch all employees in the company
        $stmt = $db->prepare("
            SELECT id, name, email, role, department_id, manager_id, org_path
            FROM users 
            WHERE company_id = :company_id 
              AND role = 'employee' 
              AND status = 'active'
            ORDER BY org_path ASC
        ");
        $stmt->execute([':company_id' => $user['company_id']]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by org_path for organizational grouping
        $grouped = [];

        foreach ($employees as $employee) {
            $path = $employee['org_path'] ?: 'unassigned';
            if (!isset($grouped[$path])) {
                $grouped[$path] = [];
            }
            $grouped[$path][] = $employee;
        }

        Response::success(['structure' => $grouped]);
    }
}
