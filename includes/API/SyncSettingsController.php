<?php
/**
 * REST API controller for Sync settings on the marketing subdomain.
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class SyncSettingsController
{
    private string $namespace = 'ams/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/sync/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/sync/log', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_log'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sync/log/clear', [
            'methods'             => 'POST',
            'callback'            => [$this, 'clear_log'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'shared_secret_set' => !empty(Settings::get('sync_shared_secret', '')) || !empty(Settings::get('sync_shared_secret_encrypted', '')),
            'allowed_domain'    => Settings::get('sync_allowed_domain', ''),
            'last_received'     => get_option('ams_sync_last_received', ''),
        ], 200);
    }

    public function update_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $updates = [];

        if ($request->has_param('shared_secret')) {
            $secret = sanitize_text_field($request->get_param('shared_secret'));
            if ($secret) {
                // Store encrypted.
                $key = defined('AUTH_KEY') ? AUTH_KEY : 'ams-sync-fallback-key';
                $iv = random_bytes(16);
                $ciphertext = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                $updates['sync_shared_secret_encrypted'] = base64_encode($iv . $ciphertext);
                $updates['sync_shared_secret'] = $secret; // Also store plain for SSO handler.
            }
        }

        if ($request->has_param('allowed_domain')) {
            $updates['sync_allowed_domain'] = sanitize_text_field($request->get_param('allowed_domain'));
        }

        if (!empty($updates)) {
            Settings::update($updates);
        }

        return new \WP_REST_Response(['updated' => true], 200);
    }

    public function get_log(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_sync_inbound_log';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return new \WP_REST_Response([], 200);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY received_at DESC LIMIT 50"
        ) ?: [];

        return new \WP_REST_Response($results, 200);
    }

    public function clear_log(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_sync_inbound_log';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("TRUNCATE TABLE {$table}");
        }

        return new \WP_REST_Response(['cleared' => true], 200);
    }
}
