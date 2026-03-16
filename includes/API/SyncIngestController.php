<?php
/**
 * REST API controller for the sync ingest endpoint on the marketing subdomain.
 *
 * POST /ams/v1/sync/ingest — receives HMAC-signed events from the main store.
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Settings;
use Apotheca\Marketing\Sync\SyncIngestor;

defined('ABSPATH') || exit;

final class SyncIngestController
{
    private string $namespace = 'ams/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/sync/ingest', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_ingest'],
            'permission_callback' => '__return_true', // Auth via HMAC signature.
        ]);
    }

    /**
     * Handle an inbound sync event.
     */
    public function handle_ingest(\WP_REST_Request $request): \WP_REST_Response
    {
        $signature = $request->get_header('X-AMS-Signature');
        $timestamp = $request->get_header('X-AMS-Timestamp');

        if (!$signature || !$timestamp) {
            $this->log_inbound('unknown', $request->get_param('site_url') ?? '', 401);
            return new \WP_REST_Response(['error' => 'Missing signature headers.'], 401);
        }

        // Verify timestamp is within 300 seconds.
        $ts = (int) $timestamp;
        if (abs(time() - $ts) > 300) {
            $this->log_inbound('unknown', $request->get_param('site_url') ?? '', 401);
            return new \WP_REST_Response(['error' => 'Timestamp expired.'], 401);
        }

        // Verify HMAC signature.
        $shared_secret = Settings::get('sync_shared_secret', '');
        if (!$shared_secret) {
            // Try decrypted version.
            $shared_secret = self::get_decrypted_secret();
        }

        if (!$shared_secret) {
            $this->log_inbound('unknown', $request->get_param('site_url') ?? '', 500);
            return new \WP_REST_Response(['error' => 'Sync not configured.'], 500);
        }

        $payload = $request->get_param('payload') ?? [];
        $expected_hmac = hash_hmac('sha256', wp_json_encode($payload) . $timestamp, $shared_secret);

        if (!hash_equals($expected_hmac, $signature)) {
            $this->log_inbound($request->get_param('event_type') ?? 'unknown', $request->get_param('site_url') ?? '', 401);
            return new \WP_REST_Response(['error' => 'Invalid signature.'], 401);
        }

        $event_type = sanitize_text_field($request->get_param('event_type') ?? '');
        $site_url = sanitize_text_field($request->get_param('site_url') ?? '');

        // Verify allowed source domain.
        $allowed_domain = Settings::get('sync_allowed_domain', '');
        if ($allowed_domain && $site_url) {
            $source_host = wp_parse_url($site_url, PHP_URL_HOST);
            if ($source_host && $source_host !== $allowed_domain) {
                $this->log_inbound($event_type, $site_url, 403);
                return new \WP_REST_Response(['error' => 'Source domain not allowed.'], 403);
            }
        }

        // Handle test ping.
        if ($event_type === 'test_ping') {
            $this->log_inbound($event_type, $site_url, 200);
            return new \WP_REST_Response(['status' => 'pong'], 200);
        }

        // Route to ingestor.
        $ingestor = new SyncIngestor();
        $result = $ingestor->handle($event_type, $payload);

        $http_status = $result ? 200 : 422;
        $this->log_inbound($event_type, $site_url, $http_status);

        return new \WP_REST_Response([
            'status'  => $result ? 'processed' : 'error',
            'event'   => $event_type,
        ], $http_status);
    }

    /**
     * Log inbound sync event.
     */
    private function log_inbound(string $event_type, string $source_url, int $http_status): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_sync_inbound_log';

        // Check if table exists (it's created in the installer update).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert($table, [
            'event_type'        => $event_type,
            'source_site_url'   => substr($source_url, 0, 500),
            'payload_hash'      => substr(md5($event_type . time()), 0, 16),
            'http_response_sent' => $http_status,
            'received_at'       => current_time('mysql'),
        ], ['%s', '%s', '%s', '%d', '%s']);

        // Update last received timestamp.
        update_option('ams_sync_last_received', current_time('mysql'));
    }

    /**
     * Decrypt the stored shared secret.
     */
    private static function get_decrypted_secret(): string
    {
        $encrypted = Settings::get('sync_shared_secret_encrypted', '');
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
}
