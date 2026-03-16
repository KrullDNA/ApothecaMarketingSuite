<?php
/**
 * REST API controller for AI features.
 *
 * Handles AI settings, subject line generation, email body generation,
 * segment suggestions, product search, and usage tracking.
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\AI\OpenAiClient;
use Apotheca\Marketing\AI\SubjectLineGenerator;
use Apotheca\Marketing\AI\EmailBodyGenerator;
use Apotheca\Marketing\AI\SegmentSuggester;
use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class AiController
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        $ns = 'ams/v1';
        $perm = function () {
            return current_user_can('manage_woocommerce');
        };

        // AI Settings.
        register_rest_route($ns, '/ai/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'update_settings'],
                'permission_callback' => $perm,
            ],
        ]);

        // Token usage summary.
        register_rest_route($ns, '/ai/usage', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_usage'],
            'permission_callback' => $perm,
        ]);

        // Subject line generation.
        register_rest_route($ns, '/ai/generate-subjects', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate_subjects'],
            'permission_callback' => $perm,
        ]);

        // Poll subject line result.
        register_rest_route($ns, '/ai/subjects-result/(?P<id>[a-zA-Z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_subjects_result'],
            'permission_callback' => $perm,
        ]);

        // Email body generation.
        register_rest_route($ns, '/ai/generate-email-body', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate_email_body'],
            'permission_callback' => $perm,
        ]);

        // Poll email body result.
        register_rest_route($ns, '/ai/email-body-result/(?P<id>[a-zA-Z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_email_body_result'],
            'permission_callback' => $perm,
        ]);

        // Segment suggestions.
        register_rest_route($ns, '/ai/suggest-segments', [
            'methods'             => 'POST',
            'callback'            => [$this, 'suggest_segments'],
            'permission_callback' => $perm,
        ]);

        // Poll segment suggestions result.
        register_rest_route($ns, '/ai/segments-result/(?P<id>[a-zA-Z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_segments_result'],
            'permission_callback' => $perm,
        ]);

        // Product search (for email body generator product picker).
        register_rest_route($ns, '/ai/product-search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'product_search'],
            'permission_callback' => $perm,
        ]);
    }

    // ── AI Settings ─────────────────────────────────────────────────────

    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'has_api_key'                    => OpenAiClient::has_api_key(),
            'ai_subject_lines_enabled'       => (bool) Settings::get('ai_subject_lines_enabled', true),
            'ai_email_body_enabled'          => (bool) Settings::get('ai_email_body_enabled', true),
            'ai_send_time_enabled'           => (bool) Settings::get('ai_send_time_enabled', true),
            'ai_product_recs_enabled'        => (bool) Settings::get('ai_product_recs_enabled', true),
            'ai_segment_suggestions_enabled' => (bool) Settings::get('ai_segment_suggestions_enabled', true),
            'ai_monthly_token_budget'        => (int) Settings::get('ai_monthly_token_budget', 500000),
            'ai_product_card_template'       => Settings::get('ai_product_card_template', ''),
            'budget_warning'                 => OpenAiClient::is_budget_warning(),
            'budget_exceeded'                => OpenAiClient::is_budget_exceeded(),
        ]);
    }

    public function update_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        // Handle API key separately (encrypted storage).
        if (isset($params['api_key']) && !empty($params['api_key'])) {
            OpenAiClient::store_api_key(sanitize_text_field($params['api_key']));
        }

        $toggles = [
            'ai_subject_lines_enabled',
            'ai_email_body_enabled',
            'ai_send_time_enabled',
            'ai_product_recs_enabled',
            'ai_segment_suggestions_enabled',
        ];

        $updates = [];
        foreach ($toggles as $key) {
            if (isset($params[$key])) {
                $updates[$key] = (bool) $params[$key];
            }
        }

        if (isset($params['ai_monthly_token_budget'])) {
            $updates['ai_monthly_token_budget'] = max(0, (int) $params['ai_monthly_token_budget']);
        }

        if (isset($params['ai_product_card_template'])) {
            $updates['ai_product_card_template'] = wp_kses_post($params['ai_product_card_template']);
        }

        if (!empty($updates)) {
            Settings::update($updates);
        }

        return $this->get_settings($request);
    }

    // ── Usage ───────────────────────────────────────────────────────────

    public function get_usage(\WP_REST_Request $request): \WP_REST_Response
    {
        $usage = OpenAiClient::get_monthly_usage();
        $budget = (int) Settings::get('ai_monthly_token_budget', 500000);

        return new \WP_REST_Response([
            'tokens_used'    => $usage['tokens'],
            'cost_usd'       => $usage['cost'],
            'calls'          => $usage['calls'],
            'budget'         => $budget,
            'budget_pct'     => $budget > 0 ? round(($usage['tokens'] / $budget) * 100, 1) : 0,
            'budget_warning' => OpenAiClient::is_budget_warning(),
            'budget_exceeded' => OpenAiClient::is_budget_exceeded(),
        ]);
    }

    // ── Subject Line Generator ──────────────────────────────────────────

    public function generate_subjects(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $request_id = SubjectLineGenerator::queue([
            'brand_name'      => sanitize_text_field($params['brand_name'] ?? ''),
            'product_context' => sanitize_text_field($params['product_context'] ?? ''),
            'body_summary'    => sanitize_text_field($params['body_summary'] ?? ''),
            'segment_name'    => sanitize_text_field($params['segment_name'] ?? ''),
        ]);

        if (empty($request_id)) {
            return new \WP_REST_Response(['error' => 'AI subject lines disabled or not configured'], 400);
        }

        return new \WP_REST_Response(['request_id' => $request_id]);
    }

    public function get_subjects_result(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = sanitize_text_field($request->get_param('id'));
        $result = get_transient('ams_ai_subjects_' . $id);

        if ($result === false) {
            return new \WP_REST_Response(['status' => 'not_found'], 404);
        }

        return new \WP_REST_Response($result);
    }

    // ── Email Body Generator ────────────────────────────────────────────

    public function generate_email_body(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $request_id = EmailBodyGenerator::queue([
            'goal'        => sanitize_text_field($params['goal'] ?? 'promotion'),
            'tone'        => sanitize_text_field($params['tone'] ?? 'friendly'),
            'key_message' => sanitize_text_field($params['key_message'] ?? ''),
            'product_ids' => array_map('intval', (array) ($params['product_ids'] ?? [])),
            'brand_name'  => sanitize_text_field($params['brand_name'] ?? ''),
        ]);

        if (empty($request_id)) {
            return new \WP_REST_Response(['error' => 'AI email body generation disabled or not configured'], 400);
        }

        return new \WP_REST_Response(['request_id' => $request_id]);
    }

    public function get_email_body_result(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = sanitize_text_field($request->get_param('id'));
        $result = get_transient('ams_ai_email_body_' . $id);

        if ($result === false) {
            return new \WP_REST_Response(['status' => 'not_found'], 404);
        }

        return new \WP_REST_Response($result);
    }

    // ── Segment Suggestions ─────────────────────────────────────────────

    public function suggest_segments(\WP_REST_Request $request): \WP_REST_Response
    {
        $request_id = SegmentSuggester::queue();

        if (empty($request_id)) {
            return new \WP_REST_Response(['error' => 'AI segment suggestions disabled or not configured'], 400);
        }

        return new \WP_REST_Response(['request_id' => $request_id]);
    }

    public function get_segments_result(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = sanitize_text_field($request->get_param('id'));
        $result = get_transient('ams_ai_segments_' . $id);

        if ($result === false) {
            return new \WP_REST_Response(['status' => 'not_found'], 404);
        }

        return new \WP_REST_Response($result);
    }

    // ── Product Search ──────────────────────────────────────────────────

    public function product_search(\WP_REST_Request $request): \WP_REST_Response
    {
        $search = sanitize_text_field($request->get_param('search') ?: '');

        if (empty($search) || !function_exists('wc_get_products')) {
            return new \WP_REST_Response([]);
        }

        $products = wc_get_products([
            'status' => 'publish',
            's'      => $search,
            'limit'  => 10,
        ]);

        $items = [];
        foreach ($products as $product) {
            $items[] = [
                'id'    => $product->get_id(),
                'name'  => $product->get_name(),
                'price' => $product->get_price(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
            ];
        }

        return new \WP_REST_Response($items);
    }
}
