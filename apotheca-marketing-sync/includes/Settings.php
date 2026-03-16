<?php
/**
 * Settings manager for the sync plugin.
 *
 * @package Apotheca\MarketingSync
 */

declare(strict_types=1);

namespace Apotheca\MarketingSync;

defined('ABSPATH') || exit;

final class Settings
{
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'endpoint_url'             => '',
            'shared_secret'            => '',
            'enable_customer_registered' => true,
            'enable_order_placed'        => true,
            'enable_order_status_changed' => true,
            'enable_cart_updated'        => true,
            'enable_product_viewed'      => true,
            'enable_checkout_started'    => true,
        ];
    }

    public static function all(): array
    {
        if (null === self::$cache) {
            $stored = get_option(AMS_SYNC_SETTINGS_KEY, []);
            self::$cache = array_merge(self::defaults(), is_array($stored) ? $stored : []);
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function update(array $values): void
    {
        $all = self::all();
        $all = array_merge($all, $values);
        update_option(AMS_SYNC_SETTINGS_KEY, $all);
        self::$cache = $all;
    }

    /**
     * Get decrypted shared secret.
     */
    public static function get_shared_secret(): string
    {
        $encrypted = self::get('shared_secret', '');
        if (!$encrypted) {
            return '';
        }

        $key = defined('AUTH_KEY') ? AUTH_KEY : 'ams-sync-fallback-key';
        $data = base64_decode($encrypted);
        if (false === $data || strlen($data) < 17) {
            return '';
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted ?: '';
    }

    /**
     * Encrypt and store shared secret.
     */
    public static function store_shared_secret(string $secret): void
    {
        if (empty($secret)) {
            self::update(['shared_secret' => '']);
            return;
        }

        $key = defined('AUTH_KEY') ? AUTH_KEY : 'ams-sync-fallback-key';
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        self::update(['shared_secret' => base64_encode($iv . $ciphertext)]);
    }

    public static function is_event_enabled(string $event_type): bool
    {
        return (bool) self::get('enable_' . $event_type, true);
    }

    public static function reset_cache(): void
    {
        self::$cache = null;
    }
}
