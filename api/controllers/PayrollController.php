<?php
/**
 * Payroll Controller
 */
class PayrollController {
    
    public static function getStructure(int $employeeId): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM salary_structures WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        $structure = $stmt->fetch();
        
        if (!$structure) Response::error('Structure not found', 404);
        
        $allowances = $db->prepare("SELECT * FROM salary_allowances WHERE salary_structure_id = ?");
        $allowances->execute([$structure['id']]);
        
        $deductions = $db->prepare("SELECT * FROM salary_deductions WHERE salary_structure_id = ?");
        $deductions->execute([$structure['id']]);
        
        $structure['allowances'] = $allowances->fetchAll();
        $structure['deductions'] = $deductions->fetchAll();
        
        Response::success($structure);
    }
    
    public static function saveStructure(int $employeeId): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $body = getRequestBody();
        $db = Database::getInstance()->getConnection();
        
        // Ensure user exists and is in the same company
        $userModel = new User();
        $employee = $userModel->findById($employeeId);
        if (!$employee) Response::error('Employee not found', 404);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $employee['company_id']);
        
        $db->beginTransaction();
        try {
            // Delete existing
            $stmt = $db->prepare("DELETE FROM salary_structures WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            
            // Create new
            $stmt = $db->prepare("INSERT INTO salary_structures (employee_id, company_id, base_salary, payment_frequency) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employeeId, $employee['company_id'], $body['base_salary'] ?? 0, $body['payment_frequency'] ?? 'monthly']);
            $structureId = $db->lastInsertId();
            
            // Allowances
            if (!empty($body['allowances'])) {
                $stmtAllow = $db->prepare("INSERT INTO salary_allowances (salary_structure_id, name, amount) VALUES (?, ?, ?)");
                foreach ($body['allowances'] as $allow) {
                    $stmtAllow->execute([$structureId, $allow['name'], $allow['amount']]);
                }
            }
            
            // Deductions
            if (!empty($body['deductions'])) {
                $stmtDeduct = $db->prepare("INSERT INTO salary_deductions (salary_structure_id, name, amount) VALUES (?, ?, ?)");
                foreach ($body['deductions'] as $deduct) {
                    $stmtDeduct->execute([$structureId, $deduct['name'], $deduct['amount']]);
                }
            }
            $db->commit();
            Response::success(null, 'Structure saved');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Failed to save structure: ' . $e->getMessage(), 500);
        }
    }
    
    public static function generatePayslip(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_SUPER_ADMIN, ROLE_COMPANY_ADMIN]);
        $body = getRequestBody();
        $employeeId = $body['employee_id'] ?? null;
        $month = $body['month'] ?? date('n');
        $year = $body['year'] ?? date('Y');
        
        if (!$employeeId) Response::error('employee_id required', 400);
        
        $db = Database::getInstance()->getConnection();
        
        // Get Structure
        $stmt = $db->prepare("SELECT * FROM salary_structures WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        $structure = $stmt->fetch();
        if (!$structure) Response::error('Salary structure not defined for employee', 404);
        
        $allowancesStmt = $db->prepare("SELECT SUM(amount) FROM salary_allowances WHERE salary_structure_id = ?");
        $allowancesStmt->execute([$structure['id']]);
        $totalAllowances = (float) $allowancesStmt->fetchColumn();
        
        $deductionsStmt = $db->prepare("SELECT SUM(amount) FROM salary_deductions WHERE salary_structure_id = ?");
        $deductionsStmt->execute([$structure['id']]);
        $totalDeductions = (float) $deductionsStmt->fetchColumn();
        
        $basicPay = (float) $structure['base_salary'];
        $netSalary = $basicPay + $totalAllowances - $totalDeductions;
        
        $stmt = $db->prepare("INSERT INTO payslips (employee_id, company_id, month, year, basic_pay, total_allowances, total_deductions, net_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'generated') ON DUPLICATE KEY UPDATE basic_pay=VALUES(basic_pay), total_allowances=VALUES(total_allowances), total_deductions=VALUES(total_deductions), net_salary=VALUES(net_salary)");
        $stmt->execute([
            $employeeId, $structure['company_id'], $month, $year, $basicPay, $totalAllowances, $totalDeductions, $netSalary
        ]);
        
        Response::success(['net_salary' => $netSalary], 'Payslip generated successfully');
    }
    
    public static function viewPayslips(int $employeeId): void {
        $auth = authenticate();
        // Employee can view their own
        if ($auth['role'] === ROLE_EMPLOYEE && $auth['user_id'] != $employeeId) Response::error('Access denied', 403);
        
        $db = Database::getInstance()->getConnection();
        $year = $_GET['year'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT * FROM payslips WHERE employee_id = ?";
        $params = [$employeeId];
        
        if ($year) {
            $query .= " AND year = ?";
            $params[] = $year;
        }
        
        $countQuery = "SELECT COUNT(*) FROM payslips WHERE employee_id = ?";
        $countParams = $params;
        
        $query .= " ORDER BY year DESC, month DESC LIMIT $perPage OFFSET $offset";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $payslips = $stmt->fetchAll();
        
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        Response::paginated($payslips, $total, $page, $perPage);
    }

    public static function getSinglePayslip(int $id): void {
        $auth = authenticate();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM payslips WHERE id = ?");
        $stmt->execute([$id]);
        $payslip = $stmt->fetch();
        
        if (!$payslip) Response::error('Payslip not found', 404);
        if ($auth['role'] === ROLE_EMPLOYEE && $auth['user_id'] != $payslip['employee_id']) Response::error('Access denied', 403);
        if ($auth['role'] === ROLE_COMPANY_ADMIN) requireCompany($auth, $payslip['company_id']);
        
        Response::success($payslip);
    }
    
    public static function myPayslips(): void {
        $auth = authenticate();
        if ($auth['role'] !== ROLE_EMPLOYEE) Response::error('Only employees can access this route', 403);
        self::viewPayslips($auth['user_id']);
    }
}
