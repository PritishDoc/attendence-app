<?php
/**
 * Standardized JSON Response Helper
 */

class Response {

    public static function success($data = null, string $message = 'Success', int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) $response['data'] = $data;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message = 'An error occurred', int $statusCode = 400, $errors = null): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => $message];
        if ($errors !== null) $response['errors'] = $errors;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function paginated(array $data, int $total, int $page, int $perPage): void {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total, 'per_page' => $perPage,
                'current_page' => $page, 'last_page' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
