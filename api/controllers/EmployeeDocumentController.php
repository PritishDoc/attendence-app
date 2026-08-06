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
        $user = requireAuth(['company', 'super_admin']);
        $db = Database::getInstance()->getConnection();

        // Validate employee belongs to company
        $stmt = $db->prepare("SELECT id FROM users WHERE id = :employee_id AND company_id = :company_id");
        $stmt->execute([':employee_id' => $employeeId, ':company_id' => $user['company_id']]);
        if (!$stmt->fetch()) {
            Response::error("Employee not found", 404);
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            Response::error("No file uploaded or upload error.", 400);
        }

        $file = $_FILES['document'];
        $documentTypeId = isset($_POST['document_type_id']) ? (int)$_POST['document_type_id'] : null;

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
        $uuid = self::generateUUID();
        $relativePath = "/storage/private/{$user['company_id']}/{$employeeId}/{$uuid}.{$ext}";
        $absolutePath = __DIR__ . '/../..' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            Response::error("Failed to save file.", 500);
        }

        // Insert into database
        $sql = "INSERT INTO files (
            uuid, company_id, employee_id, uploaded_by, entity_type, entity_id, 
            file_name, file_path, mime_type, file_size, is_sensitive
        ) VALUES (
            :uuid, :company_id, :employee_id, :uploaded_by, :entity_type, :entity_id, 
            :file_name, :file_path, :mime_type, :file_size, :is_sensitive
        )";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':uuid' => $uuid,
            ':company_id' => $user['company_id'],
            ':employee_id' => $employeeId,
            ':uploaded_by' => $user['id'],
            ':entity_type' => 'joining_document',
            ':entity_id' => $documentTypeId,
            ':file_name' => $file['name'],
            ':file_path' => $relativePath,
            ':mime_type' => $mimeType,
            ':file_size' => $file['size'],
            ':is_sensitive' => 1
        ]);

        Response::success([
            'uuid' => $uuid,
            'file_name' => $file['name'],
            'url' => "/api/files/{$uuid}"
        ], "Document uploaded successfully", 201);
    }

    /**
     * List documents for an employee
     */
    public static function list(int $employeeId) {
        $user = requireAuth(['company', 'super_admin', 'employee']);
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
                f.uuid, f.file_name, f.file_size, f.mime_type, f.created_at, f.entity_id as document_type_id,
                cdt.name as document_type_name
            FROM files f
            LEFT JOIN company_document_types cdt ON f.entity_id = cdt.id
            WHERE f.employee_id = :employee_id 
              AND f.company_id = :company_id 
              AND f.entity_type = 'joining_document'
              AND f.status = 'active'
            ORDER BY f.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':company_id' => $user['company_id']
        ]);

        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add URL for convenience
        foreach ($documents as &$doc) {
            $doc['url'] = "/api/files/{$doc['uuid']}";
        }

        Response::success(['documents' => $documents]);
    }

    /**
     * Delete a document
     */
    public static function delete(string $uuid) {
        $user = requireAuth(['company', 'super_admin']);
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT * FROM files WHERE uuid = :uuid AND company_id = :company_id");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            Response::error("Document not found", 404);
        }

        // Soft delete
        $updateStmt = $db->prepare("UPDATE files SET status = 'deleted', deleted_at = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $file['id']]);

        Response::success([], "Document deleted successfully");
    }
}
