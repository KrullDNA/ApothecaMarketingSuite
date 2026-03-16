<?php
/**
 * Review gate endpoint handler.
 *
 * Registers /ams-review-gate/ rewrite endpoint, validates tokens,
 * logs clicks to ams_events, and routes to appropriate destination
 * based on the star rating submitted.
 *
 * @package Apotheca\Marketing\Reviews
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Reviews;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class ReviewGateHandler
{
    public function __construct()
    {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_endpoint']);
    }

    /**
     * Register the /ams-review-gate/ rewrite rule.
     */
    public function register_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^ams-review-gate/?$',
            'index.php?ams_review_gate=1',
            'top'
        );
        add_filter('query_vars', function (array $vars): array {
            $vars[] = 'ams_review_gate';
            return $vars;
        });
    }

    /**
     * Handle the review gate request.
     */
    public function handle_endpoint(): void
    {
        if (!get_query_var('ams_review_gate')) {
            return;
        }

        $token    = sanitize_text_field($_GET['token'] ?? '');
        $order_id = (int) ($_GET['order_id'] ?? 0);
        $rating   = (int) ($_GET['rating'] ?? 0);

        // Validate inputs.
        if (!$token || !$order_id || $rating < 1 || $rating > 5) {
            $this->render_expired_page();
            exit;
        }

        // Verify subscriber token.
        $subscriber = $this->get_subscriber_by_token($token);
        if (!$subscriber) {
            $this->render_expired_page();
            exit;
        }

        // Check gate link expiry.
        $expiry_hours = (int) Settings::get('reviews_gate_expiry_hours', 72);
        if ($this->is_gate_expired($subscriber->id, $order_id, $expiry_hours)) {
            $this->render_expired_page();
            exit;
        }

        // Verify order belongs to subscriber.
        if (!$this->verify_order_ownership($subscriber, $order_id)) {
            $this->render_expired_page();
            exit;
        }

        // Get product IDs from the order.
        $product_ids = $this->get_order_product_ids($order_id);

        // Log the click to ams_events.
        $this->log_gate_click($subscriber->id, $rating, $order_id, $product_ids);

        // Route based on rating.
        if ($rating >= 4) {
            $this->route_positive($order_id, $product_ids, $rating, $subscriber);
        } else {
            $this->route_negative($order_id, $rating);
        }

        exit;
    }

    /**
     * Route positive ratings (4-5) to the product review page.
     */
    private function route_positive(int $order_id, array $product_ids, int $rating, object $subscriber): void
    {
        $primary_product_id = $product_ids[0] ?? 0;

        if (!$primary_product_id) {
            wp_safe_redirect(home_url());
            return;
        }

        $product = function_exists('wc_get_product') ? wc_get_product($primary_product_id) : null;
        if (!$product) {
            wp_safe_redirect(home_url());
            return;
        }

        $redirect_url = $product->get_permalink() . '#tab-reviews';

        // If WooCommerce Blocks submit-review page exists, use it with pre-fill.
        $submit_review_page = get_page_by_path('submit-review');
        if ($submit_review_page) {
            $redirect_url = add_query_arg([
                'product_id' => $primary_product_id,
                'rating'     => $rating,
                'name'       => $subscriber->first_name ?? '',
            ], get_permalink($submit_review_page));
        }

        wp_redirect(esc_url_raw($redirect_url));
    }

    /**
     * Route negative ratings (1-3) to the private feedback page.
     */
    private function route_negative(int $order_id, int $rating): void
    {
        $feedback_page_id = (int) Settings::get('reviews_private_feedback_page', 0);

        if ($feedback_page_id && get_post_status($feedback_page_id) === 'publish') {
            $redirect_url = add_query_arg([
                'order_id' => $order_id,
                'rating'   => $rating,
            ], get_permalink($feedback_page_id));

            wp_redirect(esc_url_raw($redirect_url));
            return;
        }

        // No feedback page configured — show inline thank-you.
        $this->render_thank_you_page();
    }

    /**
     * Log the review gate click to ams_events.
     */
    private function log_gate_click(int $subscriber_id, int $rating, int $order_id, array $product_ids): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_events';

        $wpdb->insert($table, [
            'subscriber_id' => $subscriber_id,
            'event_type'    => 'review_gate_click',
            'event_data'    => wp_json_encode([
                'rating'      => $rating,
                'order_id'    => $order_id,
                'product_ids' => $product_ids,
                'timestamp'   => current_time('mysql'),
            ]),
            'woo_order_id'  => $order_id,
            'product_ids'   => wp_json_encode($product_ids),
            'created_at'    => current_time('mysql'),
        ], ['%d', '%s', '%s', '%d', '%s', '%s']);
    }

    /**
     * Check if the gate link has already been used or is expired.
     */
    private function is_gate_expired(int $subscriber_id, int $order_id, int $expiry_hours): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_events';

        // Check if already clicked.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE subscriber_id = %d AND event_type = 'review_gate_click' AND woo_order_id = %d
             LIMIT 1",
            $subscriber_id,
            $order_id
        ));

        if ($existing) {
            return true;
        }

        // Check order date against expiry window.
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order) {
            return true;
        }

        $order_date = $order->get_date_completed() ?: $order->get_date_created();
        if (!$order_date) {
            return true;
        }

        $expiry_timestamp = $order_date->getTimestamp() + ($expiry_hours * HOUR_IN_SECONDS);

        return time() > $expiry_timestamp;
    }

    /**
     * Verify order belongs to the subscriber.
     */
    private function verify_order_ownership(object $subscriber, int $order_id): bool
    {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order) {
            return false;
        }

        $order_email = strtolower(trim($order->get_billing_email()));
        $sub_email = strtolower(trim($subscriber->email));

        return $order_email === $sub_email;
    }

    /**
     * Get product IDs from an order.
     */
    private function get_order_product_ids(int $order_id): array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order) {
            return [];
        }

        $ids = [];
        foreach ($order->get_items() as $item) {
            $ids[] = $item->get_product_id();
        }

        return $ids;
    }

    /**
     * Look up subscriber by unsubscribe token.
     */
    private function get_subscriber_by_token(string $token): ?object
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE unsubscribe_token = %s LIMIT 1",
            $token
        ));

        return $row ?: null;
    }

    /**
     * Render a minimal expired/already-used page.
     */
    private function render_expired_page(): void
    {
        status_header(410);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . esc_html__('Link Expired', 'apotheca-marketing-suite') . '</title>'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f9fafb;color:#374151;}'
            . '.box{text-align:center;max-width:400px;padding:40px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);}'
            . 'h1{font-size:24px;margin:0 0 12px;}p{color:#6b7280;line-height:1.6;}</style></head><body>'
            . '<div class="box"><h1>' . esc_html__('Link Expired', 'apotheca-marketing-suite') . '</h1>'
            . '<p>' . esc_html__('This review link has expired or has already been used. If you\'d like to leave a review, please visit our store directly.', 'apotheca-marketing-suite') . '</p>'
            . '<p><a href="' . esc_url(home_url()) . '" style="color:#7c3aed;">' . esc_html__('Visit Store', 'apotheca-marketing-suite') . '</a></p></div>'
            . '</body></html>';
    }

    /**
     * Render a minimal thank-you page for negative feedback.
     */
    private function render_thank_you_page(): void
    {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . esc_html__('Thank You', 'apotheca-marketing-suite') . '</title>'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f9fafb;color:#374151;}'
            . '.box{text-align:center;max-width:400px;padding:40px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);}'
            . 'h1{font-size:24px;margin:0 0 12px;}p{color:#6b7280;line-height:1.6;}</style></head><body>'
            . '<div class="box"><h1>' . esc_html__('Thank You for Your Feedback', 'apotheca-marketing-suite') . '</h1>'
            . '<p>' . esc_html__('We appreciate you taking the time to share your experience. Your feedback helps us improve our products and service.', 'apotheca-marketing-suite') . '</p>'
            . '<p><a href="' . esc_url(home_url()) . '" style="color:#7c3aed;">' . esc_html__('Back to Store', 'apotheca-marketing-suite') . '</a></p></div>'
            . '</body></html>';
    }
}
