<?php
/**
 * Event dispatcher — sends events to the marketing subdomain via HMAC-signed HTTP POST.
 *
 * Handles retries with exponential backoff (5min, 15min, 45min).
 *
 * @package Apotheca\MarketingSync
 */

declare(strict_types=1);

namespace Apotheca\MarketingSync;

defined('ABSPATH') || exit;

final class Dispatcher
{
    private const MAX_RETRIES = 3;
    private const RETRY_INTERVALS = [300, 900, 2700]; // 5min, 15min, 45min

    public function __construct()
    {
        add_action('ams_sync_dispatch', [$this, 'dispatch'], 10, 2);
        add_action('ams_sync_dispatch_retry', [$this, 'dispatch_retry'], 10, 3);
    }

    /**
     * Send an event to the marketing subdomain.
     */
    public function dispatch(string $event_type, array $payload): void
    {
        $this->send($event_type, $payload, 1);
    }

    /**
     * Retry a failed dispatch.
     */
    public function dispatch_retry(string $event_type, array $payload, int $attempt): void
    {
        $this->send($event_type, $payload, $attempt);
    }

    /**
     * Execute the HTTP POST to the ingest endpoint.
     */
    private function send(string $event_type, array $payload, int $attempt): void
    {
        $endpoint_url = Settings::get('endpoint_url', '');
        $shared_secret = Settings::get_shared_secret();

        if (!$endpoint_url || !$shared_secret) {
            return;
        }

        $timestamp = time();
        $body = [
            'event_type' => $event_type,
            'payload'    => $payload,
            'timestamp'  => $timestamp,
            'site_url'   => home_url(),
        ];

        $json_body = wp_json_encode($body);
        $hmac = hash_hmac('sha256', wp_json_encode($payload) . $timestamp, $shared_secret);
        $payload_hash = substr($hmac, 0, 16);

        $response = wp_remote_post(
            rtrim($endpoint_url, '/') . '/wp-json/ams/v1/sync/ingest',
            [
                'timeout'  => 30,
                'headers'  => [
                    'Content-Type'    => 'application/json',
                    'X-AMS-Signature' => $hmac,
                    'X-AMS-Timestamp' => (string) $timestamp,
                ],
                'body'     => $json_body,
            ]
        );

        $http_status = 0;
        $response_body = '';

        if (is_wp_error($response)) {
            $response_body = $response->get_error_message();
        } else {
            $http_status = (int) wp_remote_retrieve_response_code($response);
            $response_body = substr(wp_remote_retrieve_body($response), 0, 500);
        }

        // Log the dispatch attempt.
        $this->log($event_type, $payload_hash, $http_status, $attempt, $response_body);

        // Handle failure with retries.
        if ($http_status === 200) {
            return; // Success.
        }

        if ($http_status >= 400 && $http_status < 500) {
            return; // Permanent failure — don't retry.
        }

        // 5xx, timeout, or network error — retry with backoff.
        if ($attempt < self::MAX_RETRIES && function_exists('as_schedule_single_action')) {
            $delay = self::RETRY_INTERVALS[$attempt - 1] ?? 2700;
            as_schedule_single_action(
                time() + $delay,
                'ams_sync_dispatch_retry',
                [
                    'event_type' => $event_type,
                    'payload'    => $payload,
                    'attempt'    => $attempt + 1,
                ],
                'ams-sync'
            );
        }
    }

    /**
     * Log dispatch attempt to ams_sync_log.
     */
    private function log(string $event_type, string $payload_hash, int $http_status, int $attempt, string $response_body): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_sync_log';

        $wpdb->insert($table, [
            'event_type'     => $event_type,
            'payload_hash'   => $payload_hash,
            'http_status'    => $http_status,
            'attempt_number' => $attempt,
            'response_body'  => $response_body,
            'dispatched_at'  => current_time('mysql'),
            'created_at'     => current_time('mysql'),
        ], ['%s', '%s', '%d', '%d', '%s', '%s', '%s']);

        // Update last successful sync.
        if ($http_status === 200) {
            update_option('ams_sync_last_success', current_time('mysql'));
        }
    }

    /**
     * Send a test ping to verify connectivity.
     *
     * @return array{success: bool, status: int, message: string}
     */
    public static function test_connection(): array
    {
        $endpoint_url = Settings::get('endpoint_url', '');
        $shared_secret = Settings::get_shared_secret();

        if (!$endpoint_url) {
            return ['success' => false, 'status' => 0, 'message' => 'No endpoint URL configured.'];
        }

        if (!$shared_secret) {
            return ['success' => false, 'status' => 0, 'message' => 'No shared secret configured.'];
        }

        $timestamp = time();
        $payload = ['ping' => true];
        $body = [
            'event_type' => 'test_ping',
            'payload'    => $payload,
            'timestamp'  => $timestamp,
            'site_url'   => home_url(),
        ];

        $hmac = hash_hmac('sha256', wp_json_encode($payload) . $timestamp, $shared_secret);

        $response = wp_remote_post(
            rtrim($endpoint_url, '/') . '/wp-json/ams/v1/sync/ingest',
            [
                'timeout'  => 15,
                'headers'  => [
                    'Content-Type'    => 'application/json',
                    'X-AMS-Signature' => $hmac,
                    'X-AMS-Timestamp' => (string) $timestamp,
                ],
                'body'     => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return ['success' => false, 'status' => 0, 'message' => $response->get_error_message()];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return [
            'success' => $status === 200,
            'status'  => $status,
            'message' => $status === 200 ? 'Connection successful.' : 'HTTP ' . $status . ': ' . substr($body, 0, 200),
        ];
    }
}
