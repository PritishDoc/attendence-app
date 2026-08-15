<?php
/**
 * Visit Controller — Manage Employee Visits
 */

require_once __DIR__ . '/../models/Visit.php';
require_once __DIR__ . '/../models/User.php';

class VisitController {

    private static function handleSelfieUpload(array $file, int $companyId, int $employeeId): string {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $mimeType = mime_content_type($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            Response::error("Invalid file type. Only JPG and PNG are allowed.", 400);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error("File size exceeds 5MB limit.", 400);
        }

        $storageDir = __DIR__ . "/../../storage/public/visits/{$companyId}/{$employeeId}";
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        // Using a simple unique name if UUID function is not globally available
        $fileName = uniqid() . '.' . $ext; 
        $relativePath = "/storage/public/visits/{$companyId}/{$employeeId}/{$fileName}";
        $absolutePath = __DIR__ . '/../..' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            Response::error("Failed to save selfie.", 500);
        }

        return $relativePath;
    }

    public static function createVisit(): void {
        $auth = authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['customer_name']) || empty($data['visit_date']) || empty($data['visit_time'])) {
            Response::error('Customer name, visit date, and visit time are required.', 400);
        }

        $assigneeId = $auth['user_id'];
        
        if (in_array($auth['role'], ['company', 'company_admin', 'super_admin'])) {
            if (!empty($data['assignee_id'])) {
                $assigneeId = (int)$data['assignee_id'];
            } else {
                Response::error('Assignee ID is required when creating a visit as a manager/admin.', 400);
            }
        }

        $visitModel = new Visit();
        
        $visitData = [
            'company_id'     => $auth['company_id'],
            'assignee_id'    => $assigneeId,
            'co_assignee_id' => !empty($data['co_assignee_id']) ? (int)$data['co_assignee_id'] : null,
            'assigned_by'    => $auth['user_id'],
            'customer_name'  => trim($data['customer_name']),
            'address'        => trim($data['address'] ?? ''),
            'visit_purpose'  => trim($data['visit_purpose'] ?? ''),
            'product'        => trim($data['product'] ?? ''),
            'visit_date'     => trim($data['visit_date']),
            'visit_time'     => trim($data['visit_time']),
            'status'         => 'pending'
        ];

        try {
            $visitId = $visitModel->create($visitData);
            Response::success(['visit_id' => $visitId], 'Visit created successfully', 201);
        } catch (PDOException $e) {
            Response::error('Database Error: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            Response::error('Server Error: ' . $e->getMessage(), 500);
        }
    }

    public static function getAllVisits(): void {
        $auth = authenticate();
        $visitModel = new Visit();
        
        $filters = ['company_id' => $auth['company_id']];
        
        // If the user is an employee, only show their visits
        if ($auth['role'] === 'employee') {
            $filters['assignee_id'] = $auth['user_id'];
        } else if (!empty($_GET['assignee_id'])) {
            $filters['assignee_id'] = (int)$_GET['assignee_id'];
        }

        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $visits = $visitModel->findAll($filters);
        
        Response::success($visits, 'Visits retrieved successfully');
    }

    public static function checkIn(int $id): void {
        $auth = authenticate();
        $visitModel = new Visit();
        
        $visit = $visitModel->findById($id);
        if (!$visit) {
            Response::error('Visit not found', 404);
        }

        if ($visit['assignee_id'] !== $auth['user_id']) {
            Response::error('You can only check-in to your own visits', 403);
        }

        if ($visit['status'] !== 'pending') {
            Response::error('Visit is not in a pending state', 400);
        }

        // Validate that check-in happens on the scheduled visit day
        $today = date('Y-m-d');
        if ($visit['visit_date'] !== $today) {
            Response::error("You can only check in on the scheduled date ({$visit['visit_date']})", 400);
        }

        if (empty($_POST['lat']) || empty($_POST['lng']) || empty($_FILES['selfie'])) {
            Response::error('Latitude, longitude, and selfie are required for check-in', 400);
        }

        $selfiePath = self::handleSelfieUpload($_FILES['selfie'], $auth['company_id'], $auth['user_id']);
        
        $updateData = [
            'checkin_time'   => date('Y-m-d H:i:s'),
            'checkin_lat'    => (float)$_POST['lat'],
            'checkin_lng'    => (float)$_POST['lng'],
            'checkin_selfie' => $selfiePath
        ];

        if ($visitModel->updateCheckIn($id, $updateData)) {
            Response::success(['checkin_time' => $updateData['checkin_time']], 'Checked in successfully');
        } else {
            Response::error('Failed to check in', 500);
        }
    }

    public static function checkOut(int $id): void {
        $auth = authenticate();
        $visitModel = new Visit();
        
        $visit = $visitModel->findById($id);
        if (!$visit) {
            Response::error('Visit not found', 404);
        }

        if ($visit['assignee_id'] !== $auth['user_id']) {
            Response::error('You can only check-out of your own visits', 403);
        }

        if ($visit['status'] !== 'in_progress') {
            Response::error('Visit is not in progress', 400);
        }

        if (empty($_POST['lat']) || empty($_POST['lng']) || empty($_FILES['selfie'])) {
            Response::error('Latitude, longitude, and selfie are required for check-out', 400);
        }

        $selfiePath = self::handleSelfieUpload($_FILES['selfie'], $auth['company_id'], $auth['user_id']);
        $now = date('Y-m-d H:i:s');
        
        $updateData = [
            'checkout_time'   => $now,
            'checkout_lat'    => (float)$_POST['lat'],
            'checkout_lng'    => (float)$_POST['lng'],
            'checkout_selfie' => $selfiePath,
            'completed_at'    => $now
        ];

        if ($visitModel->updateCheckOut($id, $updateData)) {
            Response::success(['checkout_time' => $now], 'Checked out successfully');
        } else {
            Response::error('Failed to check out', 500);
        }
    }

    public static function getStats(): void {
        $auth = authenticate();
        
        $filters = ['company_id' => $auth['company_id']];
        
        if ($auth['role'] === 'employee') {
            $filters['assignee_id'] = $auth['user_id'];
        } else if (!empty($_GET['assignee_id'])) {
            $filters['assignee_id'] = (int)$_GET['assignee_id'];
        }

        $visitModel = new Visit();
        $stats = $visitModel->getStats($filters);
        
        // Ensure values are integers
        $stats = [
            'total'     => (int)($stats['total'] ?? 0),
            'completed' => (int)($stats['completed'] ?? 0),
            'pending'   => (int)($stats['pending'] ?? 0),
            'upcoming'  => (int)($stats['upcoming'] ?? 0),
        ];

        Response::success($stats, 'Visit statistics retrieved');
    }

    public static function getCompletedVisits(): void {
        $auth = authenticate();
        
        $assigneeId = $auth['user_id'];
        if (in_array($auth['role'], ['company', 'company_admin', 'super_admin']) && !empty($_GET['assignee_id'])) {
            $assigneeId = (int)$_GET['assignee_id'];
        }

        $visitModel = new Visit();
        
        $filters = [
            'company_id'  => $auth['company_id'],
            'assignee_id' => $assigneeId,
            'status'      => 'completed'
        ];

        $visits = $visitModel->findAll($filters);
        
        Response::success($visits, 'Completed visits retrieved successfully');
    }
}
