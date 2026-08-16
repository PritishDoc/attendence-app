<?php
/**
 * Authentication Middleware
 * Extracts and validates JWT from Authorization header
 */

function authenticate(): array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = '';

    // Extract Bearer token
    if (!empty($authHeader) && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (isset($_GET['token'])) {
        $token = $_GET['token'];
    }

    if (empty($token)) {
        Response::error('Authentication required', 401);
    }

    $payload = JWT::verify($token);

    if (!$payload || !isset($payload['user_id'])) {
        Response::error('Invalid or expired token', 401);
    }

    $userModel = new User();
    $user = $userModel->findById($payload['user_id']);

    if (!$user) {
        Response::error('User not found or deleted', 401);
    }

    if ($user['status'] !== 'active') {
        Response::error('Account is deactivated', 401);
    }

    if (!isset($payload['token_version']) || (int)$user['token_version'] !== (int)$payload['token_version']) {
        Response::error('Session expired or invalidated', 401);
    }

    $payload['id'] = $payload['user_id'];
    return $payload;
}

/**
 * Check if user has required role
 */
function requireRole(array $user, array $allowedRoles): void {
    if (!in_array($user['role'], $allowedRoles)) {
        Response::error('Insufficient permissions', 403);
    }
}

/**
 * Check if user belongs to the specified company
 */
function requireCompany(array $user, int $companyId): void {
    if ($user['role'] !== ROLE_SUPER_ADMIN && $user['company_id'] != $companyId) {
        Response::error('Access denied to this company', 403);
    }
}

/**
 * Unified auth helper for newer controllers.
 * Authenticates the user, checks roles, and returns a user array with 'id'.
 */
function requireAuth(array $allowedRoles): array {
    $payload = authenticate();
    requireRole($payload, $allowedRoles);
    
    return [
        'id' => $payload['user_id'],
        'company_id' => $payload['company_id'] ?? null,
        'role' => $payload['role']
    ];
}

