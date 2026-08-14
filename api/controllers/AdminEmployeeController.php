<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../services/EncryptionService.php';
require_once __DIR__ . '/../services/HierarchyService.php';

class AdminEmployeeController {
    
    /**
     * Update joining details for an employee
     */
    public static function updateJoiningDetails(int $employeeId) {
        $user = requireAuth(['company_admin', 'super_admin']);
        $db = Database::getInstance()->getConnection();
        
        $data = json_decode(file_get_contents('php://input'), true);

        $stmtCheck = $db->prepare("SELECT id FROM users WHERE id = :employee_id AND company_id = :company_id");
        $stmtCheck->execute([':employee_id' => $employeeId, ':company_id' => $user['company_id']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::error("Employee not found", 404);
        }

        $fields = [];
        $params = [
            ':employee_id' => $employeeId,
            ':company_id' => $user['company_id']
        ];

        // Standard fields
        $standardFields = ['branch_id', 'department_id', 'shift_id', 'weekoff_policy_id', 'employee_code', 'dob', 'joining_date', 'manager_id'];
        foreach ($standardFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        // Encrypted fields
        $encryptedFields = ['aadhaar_no', 'pan_no', 'esic_no', 'pf_no', 'bank_name', 'ifsc_code'];
        foreach ($encryptedFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $enc = EncryptionService::encrypt($data[$field]);
                $fields[] = "{$field}_enc = :{$field}_enc";
                $fields[] = "{$field}_iv = :{$field}_iv";
                $params[":{$field}_enc"] = $enc['ciphertext'];
                $params[":{$field}_iv"] = $enc['iv'];
                
                // For aadhaar and pan, save last4
                if ($field === 'aadhaar_no' || $field === 'pan_no') {
                    $fields[] = "{$field}_last4 = :{$field}_last4";
                    $params[":{$field}_last4"] = EncryptionService::getLast4($data[$field]);
                }
            }
        }

        if (empty($fields)) {
            Response::error("No data provided to update", 400);
        }

        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :employee_id AND company_id = :company_id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success([], "Joining details updated successfully");
    }

    /**
     * Get joining details
     */
    public static function getJoiningDetails(int $employeeId) {
        $user = requireAuth(['company_admin', 'super_admin']);
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT 
                branch_id, department_id, shift_id, weekoff_policy_id, employee_code, dob, joining_date, manager_id,
                aadhaar_last4, pan_last4, esic_no_enc, esic_iv, pf_no_enc, pf_iv, bank_name_enc, bank_name_iv, ifsc_code_enc, ifsc_code_iv
            FROM users 
            WHERE id = :employee_id AND company_id = :company_id
        ");
        $stmt->execute([':employee_id' => $employeeId, ':company_id' => $user['company_id']]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$details) {
            Response::error("Employee not found", 404);
        }

        // Decrypt necessary fields (but usually just send masked in a real app, doing full decrypt here for completeness of example)
        $decrypted = [
            'branch_id' => $details['branch_id'],
            'department_id' => $details['department_id'],
            'shift_id' => $details['shift_id'],
            'weekoff_policy_id' => $details['weekoff_policy_id'],
            'employee_code' => $details['employee_code'],
            'dob' => $details['dob'],
            'joining_date' => $details['joining_date'],
            'manager_id' => $details['manager_id'],
            'aadhaar_last4' => $details['aadhaar_last4'],
            'pan_last4' => $details['pan_last4']
        ];

        $fieldsToDecrypt = ['esic_no', 'pf_no', 'bank_name', 'ifsc_code'];
        foreach ($fieldsToDecrypt as $field) {
            if ($details["{$field}_enc"] && $details["{$field}_iv"]) {
                $decrypted[$field] = EncryptionService::decrypt($details["{$field}_enc"], $details["{$field}_iv"]);
            } else {
                $decrypted[$field] = null;
            }
        }

        Response::success(['joining_details' => $decrypted]);
    }
}
