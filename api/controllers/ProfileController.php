<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';
require_once __DIR__ . '/../middleware/auth.php';

class ProfileController {
    
    // ==========================================
    // ADDRESS
    // ==========================================

    public static function getAddress() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM employee_addresses WHERE employee_id = :employee_id AND company_id = :company_id AND deleted_at IS NULL");
        $stmt->execute([':employee_id' => $user['id'], ':company_id' => $user['company_id']]);
        
        Response::success(['addresses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function createAddress() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation basic
        if (empty($data['house_no']) || empty($data['address_type'])) {
            Response::error("House number and address type are required");
        }

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

        $stmt = $db->prepare("
            INSERT INTO employee_addresses (
                uuid, company_id, employee_id, house_no, landmark, area, 
                country_id, state_id, city_id, zip_code, address_type, created_by
            ) VALUES (
                :uuid, :company_id, :employee_id, :house_no, :landmark, :area,
                :country_id, :state_id, :city_id, :zip_code, :address_type, :created_by
            )
        ");

        $stmt->execute([
            ':uuid' => $uuid,
            ':company_id' => $user['company_id'],
            ':employee_id' => $user['id'],
            ':house_no' => $data['house_no'] ?? null,
            ':landmark' => $data['landmark'] ?? null,
            ':area' => $data['area'] ?? null,
            ':country_id' => $data['country_id'] ?? null,
            ':state_id' => $data['state_id'] ?? null,
            ':city_id' => $data['city_id'] ?? null,
            ':zip_code' => $data['zip_code'] ?? null,
            ':address_type' => $data['address_type'],
            ':created_by' => $user['id']
        ]);

        Response::success(['uuid' => $uuid], "Address created successfully", 201);
    }
    
    public static function updateAddress(string $uuid) {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Find existing address
        $stmtCheck = $db->prepare("SELECT id FROM employee_addresses WHERE uuid = :uuid AND company_id = :company_id AND deleted_at IS NULL");
        $stmtCheck->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        if (!$stmtCheck->fetch()) {
            Response::error("Address not found", 404);
        }

        $stmt = $db->prepare("
            UPDATE employee_addresses SET 
                house_no = :house_no, 
                landmark = :landmark, 
                area = :area, 
                country_id = :country_id, 
                state_id = :state_id, 
                city_id = :city_id, 
                zip_code = :zip_code, 
                address_type = :address_type, 
                updated_by = :updated_by
            WHERE uuid = :uuid AND company_id = :company_id
        ");

        $stmt->execute([
            ':house_no' => $data['house_no'] ?? null,
            ':landmark' => $data['landmark'] ?? null,
            ':area' => $data['area'] ?? null,
            ':country_id' => $data['country_id'] ?? null,
            ':state_id' => $data['state_id'] ?? null,
            ':city_id' => $data['city_id'] ?? null,
            ':zip_code' => $data['zip_code'] ?? null,
            ':address_type' => $data['address_type'] ?? null,
            ':updated_by' => $user['id'],
            ':uuid' => $uuid,
            ':company_id' => $user['company_id']
        ]);

        Response::success([], "Address updated successfully");
    }

    public static function deleteAddress(string $uuid) {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("UPDATE employee_addresses SET deleted_at = CURRENT_TIMESTAMP WHERE uuid = :uuid AND company_id = :company_id");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);

        Response::success([], "Address deleted successfully");
    }

    // ==========================================
    // EXPERIENCE
    // ==========================================

    public static function getExperience() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM employee_experience WHERE employee_id = :employee_id AND company_id = :company_id AND deleted_at IS NULL");
        $stmt->execute([':employee_id' => $user['id'], ':company_id' => $user['company_id']]);
        Response::success(['experience' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function createExperience() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $data = json_decode(file_get_contents('php://input'), true);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

        $stmt = $db->prepare("
            INSERT INTO employee_experience (
                uuid, company_id, employee_id, organization, designation, 
                from_date, to_date, responsibility, created_by
            ) VALUES (
                :uuid, :company_id, :employee_id, :organization, :designation,
                :from_date, :to_date, :responsibility, :created_by
            )
        ");

        $stmt->execute([
            ':uuid' => $uuid,
            ':company_id' => $user['company_id'],
            ':employee_id' => $user['id'],
            ':organization' => $data['organization'] ?? null,
            ':designation' => $data['designation'] ?? null,
            ':from_date' => $data['from_date'] ?? null,
            ':to_date' => $data['to_date'] ?? null,
            ':responsibility' => $data['responsibility'] ?? null,
            ':created_by' => $user['id']
        ]);

        Response::success(['uuid' => $uuid], "Experience created successfully", 201);
    }
    
    public static function deleteExperience(string $uuid) {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE employee_experience SET deleted_at = CURRENT_TIMESTAMP WHERE uuid = :uuid AND company_id = :company_id");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        Response::success([], "Experience deleted successfully");
    }

    // ==========================================
    // EDUCATION
    // ==========================================

    public static function getEducation() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM employee_education WHERE employee_id = :employee_id AND company_id = :company_id AND deleted_at IS NULL");
        $stmt->execute([':employee_id' => $user['id'], ':company_id' => $user['company_id']]);
        Response::success(['education' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function createEducation() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $data = json_decode(file_get_contents('php://input'), true);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

        $stmt = $db->prepare("
            INSERT INTO employee_education (
                uuid, company_id, employee_id, qualification, year_of_passing, 
                grade, percentage, institute, university_board, created_by
            ) VALUES (
                :uuid, :company_id, :employee_id, :qualification, :year_of_passing,
                :grade, :percentage, :institute, :university_board, :created_by
            )
        ");

        $stmt->execute([
            ':uuid' => $uuid,
            ':company_id' => $user['company_id'],
            ':employee_id' => $user['id'],
            ':qualification' => $data['qualification'] ?? null,
            ':year_of_passing' => $data['year_of_passing'] ?? null,
            ':grade' => $data['grade'] ?? null,
            ':percentage' => $data['percentage'] ?? null,
            ':institute' => $data['institute'] ?? null,
            ':university_board' => $data['university_board'] ?? null,
            ':created_by' => $user['id']
        ]);

        Response::success(['uuid' => $uuid], "Education created successfully", 201);
    }
    
    public static function deleteEducation(string $uuid) {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE employee_education SET deleted_at = CURRENT_TIMESTAMP WHERE uuid = :uuid AND company_id = :company_id");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        Response::success([], "Education deleted successfully");
    }

    // ==========================================
    // FAMILY
    // ==========================================

    public static function getFamily() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM employee_family WHERE employee_id = :employee_id AND company_id = :company_id AND deleted_at IS NULL");
        $stmt->execute([':employee_id' => $user['id'], ':company_id' => $user['company_id']]);
        
        $family = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Exclude IVs and encrypted data from direct profile fetching
        foreach ($family as &$f) {
            unset($f['aadhaar_no_enc']);
            unset($f['aadhaar_iv']);
        }
        
        Response::success(['family' => $family]);
    }

    public static function createFamily() {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        
        // This endpoint could accept file uploads, but for simplicity of this profile CRUD, 
        // we'll assume JSON input, and actual file attachments happen via a specific document upload endpoint.
        $data = json_decode(file_get_contents('php://input'), true);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

        $stmt = $db->prepare("
            INSERT INTO employee_family (
                uuid, company_id, employee_id, name, dob, phone, relation, gender, created_by
            ) VALUES (
                :uuid, :company_id, :employee_id, :name, :dob, :phone, :relation, :gender, :created_by
            )
        ");

        $stmt->execute([
            ':uuid' => $uuid,
            ':company_id' => $user['company_id'],
            ':employee_id' => $user['id'],
            ':name' => $data['name'] ?? null,
            ':dob' => $data['dob'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':relation' => $data['relation'] ?? null,
            ':gender' => $data['gender'] ?? null,
            ':created_by' => $user['id']
        ]);

        Response::success(['uuid' => $uuid], "Family member created successfully", 201);
    }
    
    public static function deleteFamily(string $uuid) {
        $user = requireAuth(['employee']);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE employee_family SET deleted_at = CURRENT_TIMESTAMP WHERE uuid = :uuid AND company_id = :company_id");
        $stmt->execute([':uuid' => $uuid, ':company_id' => $user['company_id']]);
        Response::success([], "Family member deleted successfully");
    }
}
