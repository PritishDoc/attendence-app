<?php

class Encryption {
    private static $cipher = 'aes-256-cbc';
    // Use a securely generated key, or fallback to an environment variable/config
    // For this demonstration, we'll use a hardcoded 32-byte key if one isn't defined.
    // In production, this MUST be in an environment variable or secrets manager.
    private static function getKey(): string {
        return defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'c8f7e2a9b3d14560f81a7b9c2d3e4f5a'; 
    }

    /**
     * Encrypt a string and return the encrypted value and IV
     * @param string $data The raw string to encrypt
     * @return array [ 'enc' => string, 'iv' => string, 'last4' => string ]
     */
    public static function encrypt(string $data): array {
        if (empty(trim($data))) {
            return ['enc' => null, 'iv' => null, 'last4' => null];
        }

        $ivlen = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $key = self::getKey();
        
        $encrypted = openssl_encrypt($data, self::$cipher, $key, 0, $iv);
        
        // Extract last 4 characters (if length is at least 4, else just use the string)
        $trimmed = trim($data);
        $last4 = strlen($trimmed) >= 4 ? substr($trimmed, -4) : $trimmed;

        return [
            'enc' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'last4' => $last4
        ];
    }

    /**
     * Decrypt an encrypted string using its IV
     * @param string|null $encrypted Base64 encoded encrypted string
     * @param string|null $iv Base64 encoded IV
     * @return string|null The decrypted string, or null on failure
     */
    public static function decrypt(?string $encrypted, ?string $iv): ?string {
        if (empty($encrypted) || empty($iv)) {
            return null;
        }

        $key = self::getKey();
        $decrypted = openssl_decrypt(base64_decode($encrypted), self::$cipher, $key, 0, base64_decode($iv));
        
        return $decrypted !== false ? $decrypted : null;
    }
}
