<?php
/**
 * KYC Controller - Handles submitting, viewing, and deleting sensitive KYC data
 */

require_once __DIR__ . '/../helpers/Encryption.php';

class KycController {

    /**
     * View the logged-in user's KYC data (only returning the safe last 4 digits)
     */
    public static function viewKyc(): void {
        $auth = authenticate();
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT 
            profile_photo, date_of_birth,
            aadhaar_last4, pan_last4, esic_last4, pf_last4, 
            bank_name_last4, bank_account_last4, ifsc_code_last4 
            FROM users WHERE id = ?");
        $stmt->execute([$auth['user_id']]);
        $data = $stmt->fetch();
        
        if (!$data) {
            Response::error('User not found', 404);
        }

        // Convert relative storage path to absolute URL for the Android app
        if (!empty($data['profile_photo']) && strpos($data['profile_photo'], '/storage') === 0) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $data['profile_photo'] = $protocol . '://' . $host . $data['profile_photo'];
        }

        Response::success($data, 'KYC data retrieved');
    }

    /**
     * Submit or Update KYC Data (Encrypts sensitive fields before saving)
     */
    public static function submitKyc(): void {
        $auth = authenticate();
        $isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
        $body = $isMultipart ? $_POST : getRequestBody();
        $db = Database::getInstance()->getConnection();

        $fields = [];
        $params = [];

        // 1. Profile Photo (File Upload)
        if (isset($_FILES['profile_photo'])) {
            if ($_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_photo'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                $mimeType = mime_content_type($file['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                Response::error("Invalid file type. Only JPG and PNG are allowed.", 400);
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                Response::error("File size exceeds 5MB limit.", 400);
            }

            $storageDir = __DIR__ . "/../../storage/private/{$auth['company_id']}/kyc";
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (empty($ext)) {
                if (strpos($mimeType, 'jpeg') !== false || strpos($mimeType, 'jpg') !== false) $ext = 'jpg';
                elseif (strpos($mimeType, 'png') !== false) $ext = 'png';
            }

            $fileUuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            $relativePath = "/storage/private/{$auth['company_id']}/kyc/{$fileUuid}.{$ext}";
            $absolutePath = __DIR__ . '/../..' . $relativePath;

            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                Response::error("Failed to save uploaded file.", 500);
            }

            $fields[] = "profile_photo = ?";
            $params[] = $relativePath;
            } elseif ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                // If there's an error other than "no file uploaded", throw it
                Response::error("File upload error code: " . $_FILES['profile_photo']['error'], 400);
            }
        } elseif (isset($body['profile_photo']) && is_string($body['profile_photo']) && trim($body['profile_photo']) === '') {
            // Allow clearing the photo
            $fields[] = "profile_photo = NULL";
        }

        // 2. Sensitive Fields that need Encryption
        $sensitiveFields = [
            'aadhaar' => $body['aadhaar_no'] ?? null,
            'pan' => $body['pan_no'] ?? null,
            'esic' => $body['esic_no'] ?? null,
            'pf' => $body['pf_no'] ?? null,
            'bank_name' => $body['bank_name'] ?? null,
            'bank_account' => $body['bank_account_no'] ?? null,
            'ifsc_code' => $body['ifsc_code'] ?? null
        ];

        foreach ($sensitiveFields as $prefix => $rawValue) {
            if ($rawValue !== null) {
                // Determine the correct encryption column name based on original schema
                $encCol = in_array($prefix, ['aadhaar', 'pan', 'esic', 'pf', 'bank_account']) ? "{$prefix}_no_enc" : "{$prefix}_enc";

                // If the user sends an empty string, it means they want to clear it
                if (trim($rawValue) === '') {
                    $fields[] = "$encCol = NULL";
                    $fields[] = "{$prefix}_iv = NULL";
                    $fields[] = "{$prefix}_last4 = NULL";
                } else {
                    // Encrypt the value
                    $encData = Encryption::encrypt($rawValue);
                    
                    $fields[] = "$encCol = ?";
                    $params[] = $encData['enc'];

                    $fields[] = "{$prefix}_iv = ?";
                    $params[] = $encData['iv'];

                    $fields[] = "{$prefix}_last4 = ?";
                    $params[] = $encData['last4'];
                }
            }
        }

        // Date of Birth (Plain text, usually in user profile but user requested it in KYC)
        if (isset($body['date_of_birth'])) {
            $fields[] = "date_of_birth = ?";
            $params[] = $body['date_of_birth'];
        }

        if (empty($fields)) {
            Response::error('No data provided to update', 400);
        }

        $params[] = $auth['user_id'];
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute($params)) {
            Response::success(null, 'KYC data successfully securely saved');
        } else {
            Response::error('Failed to save KYC data', 500);
        }
    }

    /**
     * Delete KYC data entirely for the logged-in user
     */
    public static function deleteKyc(): void {
        $auth = authenticate();
        $db = Database::getInstance()->getConnection();

        $sql = "UPDATE users SET 
            profile_photo = NULL,
            aadhaar_no_enc = NULL, aadhaar_iv = NULL, aadhaar_last4 = NULL,
            pan_no_enc = NULL, pan_iv = NULL, pan_last4 = NULL,
            esic_no_enc = NULL, esic_iv = NULL, esic_last4 = NULL,
            pf_no_enc = NULL, pf_iv = NULL, pf_last4 = NULL,
            bank_name_enc = NULL, bank_name_iv = NULL, bank_name_last4 = NULL,
            bank_account_no_enc = NULL, bank_account_iv = NULL, bank_account_last4 = NULL,
            ifsc_code_enc = NULL, ifsc_code_iv = NULL, ifsc_code_last4 = NULL
        WHERE id = ?";

        $stmt = $db->prepare($sql);
        if ($stmt->execute([$auth['user_id']])) {
            Response::success(null, 'KYC data successfully deleted');
        } else {
            Response::error('Failed to delete KYC data', 500);
        }
    }
}
