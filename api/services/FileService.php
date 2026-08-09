<?php
require_once __DIR__ . '/../config/database.php';

class FileService {
    private static function generateUuid(): string {
        // Simple v4 UUID generator
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Uploads a file securely to the private storage and records it in the database.
     * 
     * @param array $fileData The $_FILES array for the specific upload (e.g. $_FILES['document'])
     * @param int $companyId
     * @param int $ownerEmployeeId
     * @param int $uploadedBy
     * @param bool $isSensitive
     * @param string|null $entityType
     * @param int|null $entityId
     * @return string|false Returns the file UUID on success, or false on failure.
     */
    public static function uploadFile(
        array $fileData, 
        int $companyId, 
        int $ownerEmployeeId, 
        int $uploadedBy, 
        bool $isSensitive = true,
        ?string $entityType = null,
        ?int $entityId = null
    ) {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Validate size (max 5MB)
        if ($fileData['size'] > 5 * 1024 * 1024) {
            throw new Exception("File size exceeds 5MB limit.");
        }

        // Validate type
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes)) {
            throw new Exception("Invalid file type. Only PDF, JPG, and PNG are allowed.");
        }

        $uuid = self::generateUuid();
        $ext = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        
        // Define storage path outside of public web root
        // Assuming app root is one level above api/
        $baseDir = __DIR__ . '/../../storage/private/' . $companyId . '/' . $ownerEmployeeId;
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $fileName = $uuid . '.' . $ext;
        $destination = $baseDir . '/' . $fileName;
        
        // Internal relative path to store in DB
        $internalPath = '/storage/private/' . $companyId . '/' . $ownerEmployeeId . '/' . $fileName;

        if (move_uploaded_file($fileData['tmp_name'], $destination)) {
            $db = Database::getInstance()->getConnection();
            $sql = "INSERT INTO files (
                        uuid, company_id, employee_id, uploaded_by, 
                        entity_type, entity_id, file_name, file_path, 
                        mime_type, file_size, is_sensitive
                    ) VALUES (
                        :uuid, :company_id, :employee_id, :uploaded_by,
                        :entity_type, :entity_id, :file_name, :file_path,
                        :mime_type, :file_size, :is_sensitive
                    )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':uuid' => $uuid,
                ':company_id' => $companyId,
                ':employee_id' => $ownerEmployeeId,
                ':uploaded_by' => $uploadedBy,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':file_name' => $fileData['name'],
                ':file_path' => $internalPath,
                ':mime_type' => $mime,
                ':file_size' => $fileData['size'],
                ':is_sensitive' => $isSensitive ? 1 : 0
            ]);

            return $uuid;
        }

        return false;
    }

    /**
     * Replaces a file by archiving the old one and uploading the new one with versioning.
     */
    public static function replaceFile(
        string $parentFileUuid,
        array $fileData, 
        int $companyId, 
        int $ownerEmployeeId, 
        int $uploadedBy, 
        bool $isSensitive = true
    ) {
        $db = Database::getInstance()->getConnection();
        
        // Find parent file
        $stmt = $db->prepare("SELECT version_number FROM files WHERE uuid = :uuid AND company_id = :company_id");
        $stmt->execute([':uuid' => $parentFileUuid, ':company_id' => $companyId]);
        $parentFile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parentFile) {
            throw new Exception("Parent file not found.");
        }

        // Archive old file
        $stmtArchive = $db->prepare("UPDATE files SET status = 'archived' WHERE uuid = :uuid");
        $stmtArchive->execute([':uuid' => $parentFileUuid]);

        // Upload new file
        $newUuid = self::uploadFile($fileData, $companyId, $ownerEmployeeId, $uploadedBy, $isSensitive);
        if (!$newUuid) {
            return false;
        }

        $newVersion = (int)$parentFile['version_number'] + 1;

        // Update new file with version info
        $stmtUpdate = $db->prepare("UPDATE files SET version_number = :version, parent_file_uuid = :parent_uuid WHERE uuid = :new_uuid");
        $stmtUpdate->execute([
            ':version' => $newVersion,
            ':parent_uuid' => $parentFileUuid,
            ':new_uuid' => $newUuid
        ]);

        return $newUuid;
    }
}
