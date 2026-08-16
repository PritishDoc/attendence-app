<?php
/**
 * Incentive Controller
 */

class IncentiveController {

    public static function add() {
        $user = authenticate();
        requireRole($user, [ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $body = getRequestBody();
        $db = Database::getInstance()->getConnection();
        
        $target_user_id = $body['user_id'] ?? null;
        $incentive_type = $body['incentive_type'] ?? null;
        $target_achieved_description = $body['target_achieved_description'] ?? null;
        $total_incentive_amount = $body['total_incentive_amount'] ?? null;
        $payroll_processing_month = $body['payroll_processing_month'] ?? null;
        $approval_date = $body['approval_date'] ?? null;

        if (!$target_user_id || !$incentive_type || !$target_achieved_description || !$total_incentive_amount || !$payroll_processing_month || !$approval_date) {
            Response::error('Missing required fields.', 400);
        }

        // Verify target user belongs to same company
        $stmt = $db->prepare("SELECT id FROM users WHERE id = :id AND company_id = :company_id");
        $stmt->execute([':id' => $target_user_id, ':company_id' => $user['company_id']]);
        if (!$stmt->fetch()) {
            Response::error("Employee not found in your company.", 404);
        }

        $stmt = $db->prepare("
            INSERT INTO incentives 
            (user_id, incentive_type, target_achieved_description, total_incentive_amount, payroll_processing_month, approval_date, approved_by)
            VALUES (:user_id, :incentive_type, :target_achieved_description, :total_incentive_amount, :payroll_processing_month, :approval_date, :approved_by)
        ");
        
        $stmt->execute([
            ':user_id' => $target_user_id,
            ':incentive_type' => $incentive_type,
            ':target_achieved_description' => $target_achieved_description,
            ':total_incentive_amount' => $total_incentive_amount,
            ':payroll_processing_month' => $payroll_processing_month,
            ':approval_date' => $approval_date,
            ':approved_by' => $user['id']
        ]);

        Response::success(['id' => $db->lastInsertId()], 'Incentive added successfully.', 201);
    }

    public static function myIncentives() {
        $user = authenticate();
        $db = Database::getInstance()->getConnection();
        
        $sql = "SELECT * FROM incentives WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user['id']]);
        $incentives = $stmt->fetchAll();

        Response::success($incentives);
    }

    public static function allIncentives() {
        $user = authenticate();
        requireRole($user, [ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $db = Database::getInstance()->getConnection();
        
        $sql = "SELECT i.*, u.name, u.employee_id_code 
                FROM incentives i
                JOIN users u ON i.user_id = u.id
                WHERE u.company_id = :company_id
                ORDER BY i.created_at DESC";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([':company_id' => $user['company_id']]);
        $incentives = $stmt->fetchAll();

        Response::success($incentives);
    }

    public static function view($id) {
        $user = authenticate();
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT i.*, u.name, u.employee_id_code, u.company_id
            FROM incentives i
            JOIN users u ON i.user_id = u.id
            WHERE i.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $incentive = $stmt->fetch();
        
        if (!$incentive) {
            Response::error("Incentive not found.", 404);
        }
        
        if ($user['role'] === ROLE_EMPLOYEE && $incentive['user_id'] != $user['id']) {
            Response::error("Unauthorized.", 403);
        } else if (in_array($user['role'], [ROLE_COMPANY_ADMIN, ROLE_MANAGER]) && $incentive['company_id'] != $user['company_id']) {
            Response::error("Unauthorized.", 403);
        }

        Response::success($incentive);
    }
}
