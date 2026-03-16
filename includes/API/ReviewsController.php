<?php
/**
 * REST API controller for Reviews settings and cache management.
 *
 * Endpoints:
 * - GET    /ams/v1/reviews/settings        — get review settings
 * - POST   /ams/v1/reviews/settings        — update review settings
 * - POST   /ams/v1/reviews/refresh         — trigger manual cache refresh
 * - GET    /ams/v1/reviews/stats           — cache statistics
 * - POST   /ams/v1/reviews/test-judgeme    — test Judge.me connection
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Settings;
use Apotheca\Marketing\Reviews\JudgeMeImporter;
use Apotheca\Marketing\Reviews\CacheRefresher;

defined('ABSPATH') || exit;

final class ReviewsController
{
    private string $namespace = 'ams/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/reviews/settings', [
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

        register_rest_route($this->namespace, '/reviews/refresh', [
            'methods'             => 'POST',
            'callback'            => [$this, 'refresh_cache'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/reviews/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/reviews/test-judgeme', [
            'methods'             => 'POST',
            'callback'            => [$this, 'test_judgeme'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /**
     * Get review settings.
     */
    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = Settings::all();

        return new \WP_REST_Response([
            'min_rating'            => (int) ($settings['reviews_min_rating'] ?? 4),
            'private_feedback_page' => (int) ($settings['reviews_private_feedback_page'] ?? 0),
            'gate_expiry_hours'     => (int) ($settings['reviews_gate_expiry_hours'] ?? 72),
            'judgeme_api_key_set'   => !empty($settings['judgeme_api_key']),
            'judgeme_available'     => JudgeMeImporter::is_available(),
            'last_refresh'          => get_option('ams_reviews_last_refresh', ''),
        ], 200);
    }

    /**
     * Update review settings.
     */
    public function update_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $updates = [];

        if ($request->has_param('min_rating')) {
            $min = (int) $request->get_param('min_rating');
            $updates['reviews_min_rating'] = max(3, min(5, $min));
        }

        if ($request->has_param('private_feedback_page')) {
            $updates['reviews_private_feedback_page'] = (int) $request->get_param('private_feedback_page');
        }

        if ($request->has_param('gate_expiry_hours')) {
            $hours = (int) $request->get_param('gate_expiry_hours');
            $updates['reviews_gate_expiry_hours'] = max(1, min(720, $hours));
        }

        if ($request->has_param('judgeme_api_key')) {
            $key = sanitize_text_field($request->get_param('judgeme_api_key'));
            JudgeMeImporter::store_api_key($key);
        }

        if (!empty($updates)) {
            Settings::update($updates);
        }

        return new \WP_REST_Response(['updated' => true], 200);
    }

    /**
     * Trigger manual cache refresh.
     */
    public function refresh_cache(\WP_REST_Request $request): \WP_REST_Response
    {
        $refresher = new CacheRefresher();
        $refresher->refresh();

        return new \WP_REST_Response([
            'refreshed'    => true,
            'last_refresh' => get_option('ams_reviews_last_refresh', ''),
        ], 200);
    }

    /**
     * Get cache statistics.
     */
    public function get_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wc_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE source = 'woocommerce'");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $jm_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE source = 'judgeme'");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return new \WP_REST_Response([
            'total'        => $total,
            'woocommerce'  => $wc_count,
            'judgeme'       => $jm_count,
            'last_refresh' => get_option('ams_reviews_last_refresh', ''),
        ], 200);
    }

    /**
     * Test Judge.me API connection.
     */
    public function test_judgeme(\WP_REST_Request $request): \WP_REST_Response
    {
        $importer = new JudgeMeImporter();
        $result = $importer->test_connection();

        return new \WP_REST_Response($result, $result['success'] ? 200 : 400);
    }
}
