<?php
/**
 * Holiday Controller — Company Admin manages holidays
 */

require_once __DIR__ . '/../models/Holiday.php';

class HolidayController {

    public static function index(): void {
        $auth = authenticate();
        // Employees should be able to view holidays, but only admins can manage them.
        // For now, let's allow everyone to view the list.
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required for super admin', 400);
        }

        $filters = [
            'year'     => $_GET['year'] ?? null,
            'search'   => $_GET['search'] ?? null,
            'page'     => $_GET['page'] ?? 1,
            'per_page' => $_GET['limit'] ?? 50
        ];

        $holidayModel = new Holiday();
        $result = $holidayModel->findAll($companyId, $filters);
        
        Response::success($result['data'], 'Holidays retrieved successfully');
    }

    public static function create(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $body = getRequestBody();
        $v = new Validator();
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($body['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required', 400);
        }

        $v->required('name', $body['name'] ?? '')->length('name', $body['name'] ?? '', 2, 100);
        $v->required('holiday_date', $body['holiday_date'] ?? '');
        $v->validate();

        $data = [
            'company_id' => $companyId,
            'holiday_date' => $body['holiday_date'],
            'name' => $body['name']
        ];

        $holidayModel = new Holiday();
        $id = $holidayModel->create($data);
        
        Response::success($holidayModel->findById($id), 'Holiday created successfully', 201);
    }

    public static function update(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $holidayModel = new Holiday();
        $holiday = $holidayModel->findById($id);

        if (!$holiday) Response::error('Holiday not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $holiday['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access to this holiday', 403);
        }

        $body = getRequestBody();
        $v = new Validator();
        
        if (isset($body['name'])) {
            $v->required('name', $body['name'])->length('name', $body['name'], 2, 100);
        }
        if (isset($body['holiday_date'])) {
            $v->required('holiday_date', $body['holiday_date']);
        }
        $v->validate();

        $updates = [];
        if (isset($body['name'])) $updates['name'] = $body['name'];
        if (isset($body['holiday_date'])) $updates['holiday_date'] = $body['holiday_date'];

        if (!empty($updates)) {
            $holidayModel->update($id, $updates);
        }

        Response::success($holidayModel->findById($id), 'Holiday updated successfully');
    }

    public static function delete(int $id): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $holidayModel = new Holiday();
        $holiday = $holidayModel->findById($id);

        if (!$holiday) Response::error('Holiday not found', 404);
        if ($auth['role'] !== ROLE_SUPER_ADMIN && $holiday['company_id'] !== $auth['company_id']) {
            Response::error('Unauthorized access to this holiday', 403);
        }

        $holidayModel->delete($id);
        Response::success(null, 'Holiday deleted successfully');
    }
}
