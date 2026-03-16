<?php
/**
 * AES-256-CBC credential encryptor.
 *
 * Encrypts and decrypts sensitive SMS provider credentials stored in wp_options.
 * Derives the encryption key from WordPress AUTH_KEY constant.
 *
 * @package Apotheca\Marketing\Sms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sms;

defined('ABSPATH') || exit;

final class CredentialEncryptor
{
    private const CIPHER = 'aes-256-cbc';
    private const OPTION_KEY = 'ams_sms_credentials';

    /**
     * Derive a 32-byte encryption key from AUTH_KEY.
     */
    private static function get_key(): string
    {
        $source = defined('AUTH_KEY') ? AUTH_KEY : 'ams-default-key-change-me';
        return hash('sha256', $source, true);
    }

    /**
     * Encrypt a plaintext string.
     */
    public static function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return '';
        }

        // Store IV + ciphertext, base64 encoded.
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a ciphertext string.
     */
    public static function decrypt(string $ciphertext): string
    {
        if (empty($ciphertext)) {
            return '';
        }

        $data = base64_decode($ciphertext, true);
        if ($data === false) {
            return '';
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::CIPHER);

        if (strlen($data) < $iv_length) {
            return '';
        }

        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Store encrypted credentials.
     *
     * @param array{account_sid: string, auth_token: string, from_number: string, help_text: string} $credentials
     */
    public static function store(array $credentials): void
    {
        $encrypted = [
            'account_sid' => self::encrypt($credentials['account_sid'] ?? ''),
            'auth_token'  => self::encrypt($credentials['auth_token'] ?? ''),
            'from_number' => self::encrypt($credentials['from_number'] ?? ''),
            'help_text'   => sanitize_text_field($credentials['help_text'] ?? ''),
        ];

        update_option(self::OPTION_KEY, $encrypted);
    }

    /**
     * Retrieve decrypted credentials.
     *
     * @return array{account_sid: string, auth_token: string, from_number: string, help_text: string}
     */
    public static function retrieve(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        if (!is_array($stored)) {
            return ['account_sid' => '', 'auth_token' => '', 'from_number' => '', 'help_text' => ''];
        }

        return [
            'account_sid' => self::decrypt($stored['account_sid'] ?? ''),
            'auth_token'  => self::decrypt($stored['auth_token'] ?? ''),
            'from_number' => self::decrypt($stored['from_number'] ?? ''),
            'help_text'   => $stored['help_text'] ?? 'Reply STOP to opt out. Reply HELP for help. Msg&data rates may apply.',
        ];
    }

    /**
     * Check if credentials are configured.
     */
    public static function has_credentials(): bool
    {
        $creds = self::retrieve();
        return !empty($creds['account_sid']) && !empty($creds['auth_token']) && !empty($creds['from_number']);
    }
}
