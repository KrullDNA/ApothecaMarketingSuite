<?php
/**
 * Centralised settings manager.
 *
 * @package Apotheca\Marketing
 */

declare(strict_types=1);

namespace Apotheca\Marketing;

defined('ABSPATH') || exit;

final class Settings
{
    /**
     * Cached settings array.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $cache = null;

    /**
     * Return default settings.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'gdpr_double_optin'       => false,
            'gdpr_consent_text'       => 'I agree to receive marketing emails. You can unsubscribe at any time.',
            'checkout_optin_enabled'   => true,
            'checkout_optin_label'     => 'Keep me updated with news and offers via email.',
            'registration_capture'     => true,
            'abandoned_cart_timeout'   => 60,
            'frequency_cap_email'      => 3,
            'frequency_cap_sms'        => 2,
            'send_window_start'        => 8,
            'send_window_end'          => 21,
            'attribution_window_days'  => 5,
            'unsubscribe_page_title'   => 'Unsubscribe',
            'unsubscribe_page_message' => 'You have been successfully unsubscribed.',
            'ai_subject_lines_enabled'       => true,
            'ai_email_body_enabled'          => true,
            'ai_send_time_enabled'           => true,
            'ai_product_recs_enabled'        => true,
            'ai_segment_suggestions_enabled' => true,
            'ai_monthly_token_budget'        => 500000,
            'ai_product_card_template'       => '',
            'reviews_min_rating'             => 4,
            'reviews_private_feedback_page'  => 0,
            'reviews_gate_expiry_hours'      => 72,
            'store_url'                      => '',
            'sync_shared_secret'             => '',
            'sync_shared_secret_encrypted'   => '',
            'sync_allowed_domain'            => '',
        ];
    }

    /**
     * Get all settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (null === self::$cache) {
            $stored = get_option(AMS_SETTINGS_KEY, []);
            self::$cache = array_merge(self::defaults(), is_array($stored) ? $stored : []);
        }
        return self::$cache;
    }

    /**
     * Get a single setting value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    /**
     * Update a single setting.
     */
    public static function set(string $key, mixed $value): void
    {
        $all = self::all();
        $all[$key] = $value;
        update_option(AMS_SETTINGS_KEY, $all);
        self::$cache = $all;
    }

    /**
     * Bulk update settings.
     *
     * @param array<string, mixed> $values
     */
    public static function update(array $values): void
    {
        $all = self::all();
        $all = array_merge($all, $values);
        update_option(AMS_SETTINGS_KEY, $all);
        self::$cache = $all;
    }

    /**
     * Reset cache (useful after direct option updates).
     */
    public static function reset_cache(): void
    {
        self::$cache = null;
    }
}
