<?php
/**
 * Advance Controller
 */

class AdvanceController {

    public static function apply() {
        $user = authenticate();
        requireRole($user, [ROLE_EMPLOYEE, ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $body = getRequestBody();
        $db = Database::getInstance()->getConnection();
        
        $amount_requested = $body['amount_requested'] ?? null;
        $expense_type = $body['expense_type'] ?? null;

        if (!$amount_requested || !$expense_type) {
            Response::error('Missing required fields (amount_requested, expense_type).', 400);
        }

        $stmt = $db->prepare("
            INSERT INTO employee_advances 
            (uuid, company_id, employee_id, expense_type, amount_requested, status, created_by)
            VALUES (UUID(), :company_id, :employee_id, :expense_type, :amount_requested, 'Pending', :created_by)
        ");
        
        $stmt->execute([
            ':company_id' => $user['company_id'],
            ':employee_id' => $user['id'],
            ':expense_type' => $expense_type,
            ':amount_requested' => $amount_requested,
            ':created_by' => $user['id']
        ]);

        Response::success(['id' => $db->lastInsertId()], 'Advance request applied successfully.', 201);
    }

    public static function disburse() {
        $user = authenticate();
        requireRole($user, [ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $body = getRequestBody();
        $db = Database::getInstance()->getConnection();
        
        $target_user_id = $body['user_id'] ?? null;
        $amount_requested = $body['amount_requested'] ?? null;
        $amount_disbursed = $body['amount_disbursed'] ?? null;
        $advance_disbursal_date = $body['advance_disbursal_date'] ?? null;
        $expense_type = $body['expense_type'] ?? null;
        $payment_mode = $body['payment_mode'] ?? null;
        $reference_transaction_id = $body['reference_transaction_id'] ?? null;

        if (!$target_user_id || !$amount_requested || !$amount_disbursed || !$advance_disbursal_date || !$expense_type) {
            Response::error('Missing required fields.', 400);
        }
        
        // Verify target user belongs to same company
        $stmt = $db->prepare("SELECT id FROM users WHERE id = :id AND company_id = :company_id");
        $stmt->execute([':id' => $target_user_id, ':company_id' => $user['company_id']]);
        if (!$stmt->fetch()) {
            Response::error("Employee not found in your company.", 404);
        }

        $stmt = $db->prepare("
            INSERT INTO employee_advances 
            (uuid, company_id, employee_id, expense_type, amount_requested, amount_disbursed, advance_disbursal_date, payment_mode, reference_transaction_id, status, created_by, updated_by)
            VALUES (UUID(), :company_id, :employee_id, :expense_type, :amount_requested, :amount_disbursed, :advance_disbursal_date, :payment_mode, :reference_transaction_id, 'Disbursed', :created_by, :updated_by)
        ");
        
        $stmt->execute([
            ':company_id' => $user['company_id'],
            ':employee_id' => $target_user_id,
            ':expense_type' => $expense_type,
            ':amount_requested' => $amount_requested,
            ':amount_disbursed' => $amount_disbursed,
            ':advance_disbursal_date' => $advance_disbursal_date,
            ':payment_mode' => $payment_mode,
            ':reference_transaction_id' => $reference_transaction_id,
            ':created_by' => $user['id'],
            ':updated_by' => $user['id']
        ]);

        Response::success(['id' => $db->lastInsertId()], 'Advance disbursed successfully.', 201);
    }

    public static function myAdvances() {
        $user = authenticate();
        $db = Database::getInstance()->getConnection();
        
        $status = $_GET['status'] ?? null;
        
        $sql = "SELECT * FROM employee_advances WHERE employee_id = :user_id";
        $params = [':user_id' => $user['id']];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $advances = $stmt->fetchAll();

        Response::success($advances);
    }

    public static function allAdvances() {
        $user = authenticate();
        requireRole($user, [ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $db = Database::getInstance()->getConnection();
        
        $sql = "SELECT a.*, u.name, u.employee_id_code 
                FROM employee_advances a
                JOIN users u ON a.employee_id = u.id
                WHERE u.company_id = :company_id
                ORDER BY a.created_at DESC";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([':company_id' => $user['company_id']]);
        $advances = $stmt->fetchAll();

        Response::success($advances);
    }

    public static function updateStatus() {
        $user = authenticate();
        requireRole($user, [ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $body = getRequestBody();
        $advance_id = $body['advance_id'] ?? null;
        $status = $body['status'] ?? null; // 'Disbursed' or 'Rejected'

        if (!$advance_id || !$status) {
            Response::error("advance_id and status are required.", 400);
        }
        
        if (!in_array($status, ['Disbursed', 'Rejected'])) {
            Response::error("Invalid status. Expected Disbursed or Rejected.", 400);
        }

        $db = Database::getInstance()->getConnection();
        
        // Verify advance belongs to the company
        $stmt = $db->prepare("SELECT a.id FROM employee_advances a JOIN users u ON a.employee_id = u.id WHERE a.id = :id AND u.company_id = :company_id");
        $stmt->execute([':id' => $advance_id, ':company_id' => $user['company_id']]);
        if (!$stmt->fetch()) {
            Response::error("Advance request not found.", 404);
        }

        if ($status === 'Disbursed') {
            $amount_disbursed = $body['amount_disbursed'] ?? null;
            $advance_disbursal_date = $body['advance_disbursal_date'] ?? null;
            $payment_mode = $body['payment_mode'] ?? null;
            $reference_transaction_id = $body['reference_transaction_id'] ?? null;
            
            if (!$amount_disbursed || !$advance_disbursal_date) {
                Response::error("amount_disbursed and advance_disbursal_date are required for disbursement.", 400);
            }

            $stmt = $db->prepare("
                UPDATE employee_advances 
                SET status = :status, updated_by = :updated_by, 
                    amount_disbursed = :amount_disbursed, advance_disbursal_date = :advance_disbursal_date, 
                    payment_mode = :payment_mode, reference_transaction_id = :reference_transaction_id
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':updated_by' => $user['id'],
                ':amount_disbursed' => $amount_disbursed,
                ':advance_disbursal_date' => $advance_disbursal_date,
                ':payment_mode' => $payment_mode,
                ':reference_transaction_id' => $reference_transaction_id,
                ':id' => $advance_id
            ]);
        } else {
            // Rejected
            $stmt = $db->prepare("UPDATE employee_advances SET status = :status, action_by = :action_by WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':action_by' => $user['id'],
                ':id' => $advance_id
            ]);
        }

        Response::success(null, 'Advance status updated successfully.');
    }

    public static function view($id) {
        $user = authenticate();
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT a.*, u.name, u.employee_id_code, u.company_id
            FROM employee_advances a
            JOIN users u ON a.employee_id = u.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $advance = $stmt->fetch();
        
        if (!$advance) {
            Response::error("Advance request not found.", 404);
        }
        
        if ($user['role'] === ROLE_EMPLOYEE) {
            if ($advance['employee_id'] !== $user['id']) {
                Response::error("Unauthorized.", 403);
            }
        } else if (in_array($user['role'], [ROLE_COMPANY_ADMIN, ROLE_MANAGER]) && $advance['company_id'] != $user['company_id']) {
            Response::error("Unauthorized.", 403);
        }

        Response::success($advance);
    }
}
