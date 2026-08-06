<?php
/**
 * Company Setting Controller — Company Admin manages settings
 */

require_once __DIR__ . '/../models/CompanySetting.php';

class CompanySettingController {

    public static function show(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required for super admin', 400);
        }

        $settingModel = new CompanySetting();
        $settings = $settingModel->findByCompanyId($companyId);
        
        if (!$settings) {
            Response::error('Settings not found for this company', 404);
        }

        Response::success($settings);
    }

    public static function update(): void {
        $auth = authenticate();
        requireRole($auth, [ROLE_COMPANY_ADMIN, ROLE_SUPER_ADMIN]);
        
        $body = getRequestBody();
        $companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($body['company_id'] ?? null) : $auth['company_id'];
        
        if (!$companyId) {
            Response::error('Company ID is required', 400);
        }

        $v = new Validator();
        
        if (isset($body['work_start_time'])) {
            $v->required('work_start_time', $body['work_start_time']);
        }
        if (isset($body['work_end_time'])) {
            $v->required('work_end_time', $body['work_end_time']);
        }
        $v->validate();

        $updates = [];
        $fields = [
            'work_start_time', 
            'work_end_time', 
            'late_threshold_minutes', 
            'half_day_hours', 
            'full_day_hours', 
            'working_days', 
            'timezone'
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $body)) {
                $updates[$field] = $body[$field];
            }
        }

        if (!empty($updates)) {
            $settingModel = new CompanySetting();
            $settingModel->update($companyId, $updates);
        }

        $settingModel = new CompanySetting();
        Response::success($settingModel->findByCompanyId($companyId), 'Settings updated successfully');
    }
}
