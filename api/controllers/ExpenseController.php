<?php
/**
 * Expense Controller
 */

class ExpenseController {
    
    private static function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function create() {
        $user = authenticate();
        requireRole($user, [ROLE_EMPLOYEE, ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $db = Database::getInstance()->getConnection();
        
        $expense_date = $_POST['expense_date'] ?? null;
        $expense_type = $_POST['expense_type'] ?? null;
        $expense_category = $_POST['expense_category'] ?? null;
        $expense_head = $_POST['expense_head'] ?? null;
        $amount = $_POST['amount'] ?? null;
        $reference_id = isset($_POST['reference_id']) && $_POST['reference_id'] !== '' ? (int)$_POST['reference_id'] : null;

        if (!$expense_date || !$expense_type || !$expense_category || !$expense_head || !$amount) {
            Response::error('Missing required fields.', 400);
        }

        $relativePath = null;

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            $mimeType = mime_content_type($file['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                Response::error("Invalid file type. Only PDF, JPG, and PNG are allowed.", 400);
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                Response::error("File size exceeds 5MB limit.", 400);
            }

            $storageDir = __DIR__ . "/../../storage/private/{$user['company_id']}/expenses";
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (empty($ext)) {
                if (strpos($mimeType, 'jpeg') !== false || strpos($mimeType, 'jpg') !== false) $ext = 'jpg';
                elseif (strpos($mimeType, 'png') !== false) $ext = 'png';
                elseif (strpos($mimeType, 'pdf') !== false) $ext = 'pdf';
            }

            $fileUuid = self::generateUUID();
            $relativePath = "/storage/private/{$user['company_id']}/expenses/{$fileUuid}.{$ext}";
            $absolutePath = __DIR__ . '/../..' . $relativePath;

            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                Response::error("Failed to save uploaded file.", 500);
            }
        }

        $stmt = $db->prepare("
            INSERT INTO employee_expenses 
            (uuid, company_id, employee_id, expense_date, expense_type, expense_category, expense_head, amount, attachment, status)
            VALUES (UUID(), :company_id, :employee_id, :expense_date, :expense_type, :expense_category, :expense_head, :amount, :attachment, 'Pending')
        ");
        
        $stmt->execute([
            ':company_id' => $user['company_id'],
            ':employee_id' => $user['id'],
            ':expense_date' => $expense_date,
            ':expense_type' => $expense_type,
            ':expense_category' => $expense_category,
            ':expense_head' => $expense_head,
            ':amount' => $amount,
            ':attachment' => $relativePath
        ]);

        Response::success(['id' => $db->lastInsertId()], 'Expense added successfully.', 201);
    }

    public static function myExpenses() {
        $user = authenticate();
        $db = Database::getInstance()->getConnection();
        
        $status = $_GET['status'] ?? null;
        
        $sql = "SELECT * FROM employee_expenses WHERE employee_id = :employee_id";
        $params = [':employee_id' => $user['id']];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();

        Response::success($expenses);
    }

    public static function allExpenses() {
        $user = authenticate();
        requireRole($user, [ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $db = Database::getInstance()->getConnection();
        
        // Optionally join with users to get employee details
        $sql = "SELECT e.*, u.name, u.employee_id_code 
                FROM employee_expenses e
                JOIN users u ON e.employee_id = u.id
                WHERE u.company_id = :company_id
                ORDER BY e.created_at DESC";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([':company_id' => $user['company_id']]);
        $expenses = $stmt->fetchAll();

        Response::success($expenses);
    }

    public static function updateStatus() {
        $user = authenticate();
        requireRole($user, [ROLE_COMPANY_ADMIN, ROLE_MANAGER]);
        
        $body = getRequestBody();
        $expense_id = $body['expense_id'] ?? null;
        $status = $body['status'] ?? null;

        if (!$expense_id || !$status) {
            Response::error("expense_id and status are required.", 400);
        }
        
        if (!in_array($status, ['Approved', 'Rejected'])) {
            Response::error("Invalid status.", 400);
        }

        $db = Database::getInstance()->getConnection();
        
        // Verify expense belongs to the company
        $stmt = $db->prepare("SELECT e.id FROM employee_expenses e JOIN users u ON e.employee_id = u.id WHERE e.id = :id AND u.company_id = :company_id");
        $stmt->execute([':id' => $expense_id, ':company_id' => $user['company_id']]);
        if (!$stmt->fetch()) {
            Response::error("Expense not found.", 404);
        }

        $stmt = $db->prepare("UPDATE employee_expenses SET status = :status, approved_by = :approved_by WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':approved_by' => $user['id'],
            ':id' => $expense_id
        ]);

        Response::success(null, 'Expense status updated successfully.');
    }

    public static function view($id) {
        $user = authenticate();
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT e.*, u.name, u.employee_id_code, u.company_id
            FROM employee_expenses e
            JOIN users u ON e.employee_id = u.id
            WHERE e.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $expense = $stmt->fetch();
        
        if (!$expense) {
            Response::error("Expense not found.", 404);
        }
        
        // Employee can only view their own. Admin/Manager can view company's.
        if ($user['role'] === ROLE_EMPLOYEE && $expense['employee_id'] != $user['id']) {
            Response::error("Unauthorized.", 403);
        } else if (in_array($user['role'], [ROLE_COMPANY_ADMIN, ROLE_MANAGER]) && $expense['company_id'] != $user['company_id']) {
            Response::error("Unauthorized.", 403);
        }

        Response::success($expense);
    }
}
