<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

class EmployeeDocumentController {

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

    /**
     * Upload a joining document for an employee
     */
    public static function upload(int $employeeId) {
        $user = requireAuth(['company_admin', 'super_admin', 'employee']);
        $db = Database::getInstance()->getConnection();

        // Access control check
        if ($user['role'] === 'employee') {
            if ($user['id'] != $employeeId) {
                Response::error("Access denied. You can only upload your own documents.", 403);
            }
        } else {
            // Validate employee belongs to company
            $stmt = $db->prepare("SELECT id FROM users WHERE id = :employee_id AND company_id = :company_id");
            $stmt->execute([':employee_id' => $employeeId, ':company_id' => $user['company_id']]);
            if (!$stmt->fetch()) {
                Response::error("Employee not found", 404);
            }
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            Response::error("No file uploaded or upload error.", 400);
        }

        $file = $_FILES['document'];
        $documentTypeId = isset($_POST['document_type_id']) ? (int)$_POST['document_type_id'] : null;

        if (!$documentTypeId) {
            Response::error("Document type ID is required.", 400);
        }

        // Validate file type
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $mimeType = mime_content_type($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            Response::error("Invalid file type. Only PDF, JPG, and PNG are allowed.", 400);
        }

        // Validate size (e.g. max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error("File size exceeds 5MB limit.", 400);
        }

        // Prepare directory
        $storageDir = __DIR__ . "/../../storage/private/{$user['company_id']}/{$employeeId}";
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileUuid = self::generateUUID();
        $relativePath = "/storage/private/{$user['company_id']}/{$employeeId}/{$fileUuid}.{$ext}";
        $absolutePath = __DIR__ . '/../..' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            Response::error("Failed to save file.", 500);
        }

        // Set verification status and verified_by based on role
        $verificationStatus = 'pending';
        $verifiedBy = null;
        if ($user['role'] === 'company' || $user['role'] === 'super_admin' || $user['role'] === 'company_admin') {
            $verificationStatus = 'verified';
            $verifiedBy = $user['id'];
        }

        $db->beginTransaction();
        try {
            // 1. Insert into files table (physical file record)
            $sqlFile = "INSERT INTO files (
                uuid, company_id, employee_id, uploaded_by, entity_type, entity_id, 
                file_name, file_path, mime_type, file_size, is_sensitive
            ) VALUES (
                :uuid, :company_id, :employee_id, :uploaded_by, :entity_type, :entity_id, 
                :file_name, :file_path, :mime_type, :file_size, :is_sensitive
            )";

            $stmtFile = $db->prepare($sqlFile);
            $stmtFile->execute([
                ':uuid' => $fileUuid,
                ':company_id' => $user['company_id'],
                ':employee_id' => $employeeId,
                ':uploaded_by' => $user['id'],
                ':entity_type' => 'employee_document', // Generic marker
                ':entity_id' => null,
                ':file_name' => $file['name'],
                ':file_path' => $relativePath,
                ':mime_type' => $mimeType,
                ':file_size' => $file['size'],
                ':is_sensitive' => 1
            ]);

            // 2. Insert into employee_documents table (logical record)
            $documentUuid = self::generateUUID();
            $sqlDoc = "INSERT INTO employee_documents (
                uuid, company_id, employee_id, document_type_id, document_name, 
                document_file_uuid, version_number, is_active, verification_status, verified_by, created_by
            ) VALUES (
                :uuid, :company_id, :employee_id, :document_type_id, :document_name,
                :document_file_uuid, 1, 1, :verification_status, :verified_by, :created_by
            )";

            $stmtDoc = $db->prepare($sqlDoc);
            $stmtDoc->execute([
                ':uuid' => $documentUuid,
                ':company_id' => $user['company_id'],
                ':employee_id' => $employeeId,
                ':document_type_id' => $documentTypeId,
                ':document_name' => $file['name'],
                ':document_file_uuid' => $fileUuid,
                ':verification_status' => $verificationStatus,
                ':verified_by' => $verifiedBy,
                ':created_by' => $user['id']
            ]);

            $db->commit();

            Response::success([
                'uuid' => $documentUuid,
                'file_uuid' => $fileUuid,
                'file_name' => $file['name'],
                'url' => "/api/files/{$fileUuid}",
                'verification_status' => $verificationStatus
            ], "Document uploaded successfully", 201);
            
        } catch (Exception $e) {
            $db->rollBack();
            // Clean up the physical file since DB insertion failed
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }
            Response::error("Database error: " . $e->getMessage(), 500);
        }
    }

    /**
     * List documents for an employee
     */
    public static function list(int $employeeId) {
        $user = requireAuth(['company_admin', 'super_admin', 'employee']);
        $db = Database::getInstance()->getConnection();

        // Access control check
        if ($user['role'] === 'employee' && $user['id'] != $employeeId) {
            Response::error("Access denied.", 403);
        } else if ($user['role'] !== 'employee') {
            $stmt = $db->prepare("SELECT id FROM users WHERE id = :employee_id AND company_id = :company_id");
            $stmt->execute([':employee_id' => $employeeId, ':company_id' => $user['company_id']]);
            if (!$stmt->fetch()) {
                Response::error("Employee not found", 404);
            }
        }

        $sql = "
            SELECT 
                ed.uuid, ed.document_name as file_name, ed.document_type_id,
                cdt.name as document_type_name,
                ed.verification_status, ed.rejected_at,
                ed.document_file_uuid as file_uuid,
                f.file_size, f.mime_type, ed.created_at,
                u1.name as uploaded_by_name,
                u2.name as verified_by_name
            FROM employee_documents ed
            LEFT JOIN files f ON ed.document_file_uuid = f.uuid
            LEFT JOIN company_document_types cdt ON ed.document_type_id = cdt.id
            LEFT JOIN users u1 ON ed.created_by = u1.id
            LEFT JOIN users u2 ON ed.verified_by = u2.id
            WHERE ed.employee_id = :employee_id 
              AND ed.company_id = :company_id 
              AND ed.is_active = 1
              AND ed.deleted_at IS NULL
            ORDER BY ed.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':company_id' => $user['company_id']
        ]);

        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add URL for convenience and format names
        foreach ($documents as &$doc) {
            $doc['url'] = "/api/files/{$doc['file_uuid']}";
        }

        // Return raw array to match Android frontend expectations
        Response::success($documents);
    }

    /**
     * List all pending document verifications for the company (Admin only)
     */
    public static function pendingVerifications() {
        $user = requireAuth(['company_admin', 'super_admin']);
        $db = Database::getInstance()->getConnection();
        
        $sql = "
            SELECT 
                ed.uuid, ed.document_name as file_name, ed.document_type_id,
                cdt.name as document_type_name,
                ed.verification_status,
                ed.document_file_uuid as file_uuid,
                f.file_size, f.mime_type, ed.created_at,
                e.id as employee_id, e.name as employee_name,
                u1.name as uploaded_by_name
            FROM employee_documents ed
            LEFT JOIN files f ON ed.document_file_uuid = f.uuid
            LEFT JOIN company_document_types cdt ON ed.document_type_id = cdt.id
            LEFT JOIN users e ON ed.employee_id = e.id
            LEFT JOIN users u1 ON ed.created_by = u1.id
            WHERE ed.company_id = :company_id 
              AND ed.is_active = 1
              AND ed.deleted_at IS NULL
              AND ed.verification_status = 'pending'
            ORDER BY ed.created_at ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':company_id' => $user['company_id']]);
        
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($documents as &$doc) {
            $doc['url'] = "/api/files/{$doc['file_uuid']}";
        }
        
        Response::success($documents);
    }

    /**
     * Verify or reject a pending document (Admin only)
     */
    public static function verifyDocument(string $uuid) {
        $user = requireAuth(['company_admin', 'super_admin']);
        $db = Database::getInstance()->getConnection();
        
        $data = json_decode(file_get_contents('php://input'), true);
        $status = $data['status'] ?? null; // 'verified' or 'rejected'
        
        if (!in_array($status, ['verified', 'rejected'])) {
            Response::error("Invalid status. Must be 'verified' or 'rejected'", 400);
        }
        
        $stmt = $db->prepare("SELECT * FROM employee_documents WHERE uuid = :uuid AND company_id = :company_id AND is_active = 1");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doc) {
            Response::error("Document not found", 404);
        }
        
        if ($doc['verification_status'] !== 'pending') {
            Response::error("Document is already processed", 400);
        }
        
        $rejectedAt = ($status === 'rejected') ? date('Y-m-d H:i:s') : null;

        $updateStmt = $db->prepare("
            UPDATE employee_documents 
            SET verification_status = :status, verified_by = :verified_by, rejected_at = :rejected_at, updated_by = :updated_by
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':status' => $status,
            ':verified_by' => $user['id'],
            ':rejected_at' => $rejectedAt,
            ':updated_by' => $user['id'],
            ':id' => $doc['id']
        ]);
        
        Response::success([], "Document $status successfully");
    }

    /**
     * Delete a document
     */
    public static function delete(string $uuid) {
        $user = requireAuth(['company_admin', 'super_admin']);
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT * FROM employee_documents WHERE uuid = :uuid AND company_id = :company_id");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            Response::error("Document not found", 404);
        }

        // Soft delete
        $updateStmt = $db->prepare("UPDATE employee_documents SET is_active = 0, deleted_at = NOW(), updated_by = :updated_by WHERE id = :id");
        $updateStmt->execute([
            ':updated_by' => $user['id'],
            ':id' => $doc['id']
        ]);

        Response::success([], "Document deleted successfully");
    }
}
