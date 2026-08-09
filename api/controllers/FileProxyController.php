<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

class FileProxyController {
    
    /**
     * Proxies a file request, enforcing access control based on user role and file sensitivity.
     */
    public static function getFile(string $uuid) {
        $user = requireAuth(['employee', 'company', 'super_admin']);
        $db = Database::getInstance()->getConnection();

        // 1. Fetch file record (Ensuring company isolation)
        $stmt = $db->prepare("SELECT * FROM files WHERE uuid = :uuid AND company_id = :company_id AND status != 'deleted'");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            Response::error("File not found or access denied.", 404);
        }

        // 2. Enforce Access Control
        // - Admins can access all files within their company.
        // - Employees can ONLY access files where files.employee_id = their own user ID.
        
        $hasAccess = false;
        
        if ($user['role'] === 'company' || $user['role'] === 'super_admin') {
            $hasAccess = true;
        } else if ($user['role'] === 'employee') {
            if ($file['employee_id'] == $user['id']) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            Response::error("Access denied.", 403);
        }

        // 3. Log the access
        $logStmt = $db->prepare("INSERT INTO file_access_logs (file_id, accessed_by) VALUES (:file_id, :accessed_by)");
        $logStmt->execute([
            ':file_id' => $file['id'],
            ':accessed_by' => $user['id']
        ]);

        // 4. Serve the file
        $absolutePath = __DIR__ . '/../..' . $file['file_path'];
        
        if (!file_exists($absolutePath)) {
            Response::error("Physical file missing from server.", 404);
        }

        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: inline; filename="' . basename($file['file_name']) . '"');
        header('Cache-Control: private, max-age=86400'); // Cache for 1 day
        
        readfile($absolutePath);
        exit;
    }
}
