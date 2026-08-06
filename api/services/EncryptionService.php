<?php

class EncryptionService {
    // Advanced Encryption Standard (AES) with 256-bit key in Cipher Block Chaining (CBC) mode
    private const CIPHER_ALGO = 'aes-256-cbc';

    /**
     * Gets the encryption key from environment variable.
     */
    private static function getKey(): string {
        $key = getenv('APP_KEY');
        if (!$key) {
            // Fallback for development if not set, but in production should be strictly in .env
            $key = 'secret_attendance_app_key_2026!'; 
        }
        // Hash the key to ensure it's exactly 32 bytes (256 bits) for AES-256
        return hash('sha256', $key, true);
    }

    /**
     * Encrypts plaintext data and returns ciphertext and IV.
     * 
     * @param string $plaintext
     * @return array ['ciphertext' => string, 'iv' => string]
     */
    public static function encrypt(string $plaintext): array {
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_ALGO,
            self::getKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv' => base64_encode($iv)
        ];
    }

    /**
     * Decrypts ciphertext using the provided IV.
     * 
     * @param string $ciphertextBase64
     * @param string $ivBase64
     * @return string|false The decrypted text or false on failure.
     */
    public static function decrypt(string $ciphertextBase64, string $ivBase64) {
        $ciphertext = base64_decode($ciphertextBase64);
        $iv = base64_decode($ivBase64);

        return openssl_decrypt(
            $ciphertext,
            self::CIPHER_ALGO,
            self::getKey(),
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /**
     * Helper to mask strings (e.g., return last 4 characters).
     */
    public static function getLast4(string $value): string {
        $len = strlen($value);
        if ($len <= 4) {
            return $value;
        }
        return substr($value, -4);
    }
}
