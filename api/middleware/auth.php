<?php
/**
 * Authentication Middleware
 * Extracts and validates JWT from Authorization header
 */

function authenticate(): array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader)) {
        Response::error('Authentication required', 401);
    }

    // Extract Bearer token
    if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        Response::error('Invalid authorization format', 401);
    }

    $token = $matches[1];
    $payload = JWT::verify($token);

    if (!$payload) {
        Response::error('Invalid or expired token', 401);
    }

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
