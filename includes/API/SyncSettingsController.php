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

        register_rest_route($this->namespace, '/sync/test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'test_connection'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(): bool
    {
        return current_user_can(function_exists('WC') ? 'manage_woocommerce' : 'manage_options');
    }

    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'store_url'         => Settings::get('store_url', ''),
            'shared_secret_set' => !empty(Settings::get('sync_shared_secret', '')) || !empty(Settings::get('sync_shared_secret_encrypted', '')),
            'allowed_domain'    => Settings::get('sync_allowed_domain', ''),
            'last_received'     => get_option('ams_sync_last_received', ''),
            'ingest_url'        => rest_url('ams/v1/sync/ingest'),
        ], 200);
    }

    public function update_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $updates = [];

        if ($request->has_param('store_url')) {
            $updates['store_url'] = esc_url_raw($request->get_param('store_url'));
        }

        if ($request->has_param('shared_secret')) {
            $secret = sanitize_text_field($request->get_param('shared_secret'));
            if ($secret) {
                // Store encrypted using AES-256-CBC.
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

    /**
     * Test connection by sending a signed loopback request to the ingest endpoint.
     */
    public function test_connection(\WP_REST_Request $request): \WP_REST_Response
    {
        $secret = Settings::get('sync_shared_secret', '');
        if (!$secret) {
            return new \WP_REST_Response(['status' => 'error', 'message' => 'Shared secret not configured.'], 422);
        }

        $body = wp_json_encode([
            'event_type' => 'test_ping',
            'payload'    => [],
            'timestamp'  => time(),
            'site_url'   => home_url(),
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $body, $secret);

        $response = wp_remote_post(rest_url('ams/v1/sync/ingest'), [
            'headers' => [
                'Content-Type'    => 'application/json',
                'X-AMS-Signature' => $signature,
                'X-AMS-Timestamp' => $timestamp,
            ],
            'body'      => $body,
            'timeout'   => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response([
                'status'  => 'error',
                'message' => $response->get_error_message(),
            ], 500);
        }

        $code = wp_remote_retrieve_response_code($response);
        $resp_body = json_decode(wp_remote_retrieve_body($response), true) ?: [];

        return new \WP_REST_Response([
            'status'    => $code === 200 ? 'ok' : 'error',
            'http_code' => $code,
            'response'  => $resp_body,
        ], 200);
    }

    public function get_log(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_sync_log';

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
        $table = $wpdb->prefix . 'ams_sync_log';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("TRUNCATE TABLE {$table}");
        }

        return new \WP_REST_Response(['cleared' => true], 200);
    }
}
