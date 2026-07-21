<?php
/**
 * JWT Helper — Pure PHP implementation (no external library)
 * Uses HMAC-SHA256 for signing
 */

class JWT {

    /**
     * Generate a JWT token
     * 
     * @param array $payload Data to encode in the token
     * @return string The JWT token
     */
    public static function generate(array $payload, int $expirySeconds = null): string {
        $header = [
            'typ' => 'JWT',
            'alg' => JWT_ALGORITHM
        ];

        // Add standard claims
        $payload['iss'] = JWT_ISSUER;
        $payload['aud'] = JWT_AUDIENCE;
        $payload['jti'] = bin2hex(random_bytes(16));
        $payload['iat'] = time();
        
        if (!isset($payload['exp'])) {
            $expirySeconds = $expirySeconds ?? JWT_EXPIRY;
            $payload['exp'] = time() + $expirySeconds;
        }

        $headerEncoded  = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Verify and decode a JWT token
     * 
     * @param string $token The JWT token
     * @return array|false Decoded payload or false if invalid
     */
    public static function verify(string $token) {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        $expectedSignatureEncoded = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
            return false;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return false;
        }

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        // Verify standard claims
        if (!isset($payload['iss']) || $payload['iss'] !== JWT_ISSUER) {
            return false;
        }
        if (!isset($payload['aud']) || $payload['aud'] !== JWT_AUDIENCE) {
            return false;
        }

        return $payload;
    }

    /**
     * Base64 URL-safe encode
     */
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
