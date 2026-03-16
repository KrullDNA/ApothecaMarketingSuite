<?php
/**
 * Product view beacon — lightweight JS on single product pages.
 *
 * Fires an AJAX POST which queues an Action Scheduler job.
 *
 * @package Apotheca\MarketingSync
 */

declare(strict_types=1);

namespace Apotheca\MarketingSync;

defined('ABSPATH') || exit;

final class ProductViewBeacon
{
    public function __construct()
    {
        if (!Settings::is_event_enabled('product_viewed')) {
            return;
        }

        add_action('wp_footer', [$this, 'render_beacon']);
        add_action('wp_ajax_ams_sync_product_view', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_ams_sync_product_view', [$this, 'handle_ajax']);
    }

    /**
     * Output inline beacon JS on single product pages only.
     */
    public function render_beacon(): void
    {
        if (!is_singular('product')) {
            return;
        }

        global $post;
        $product_id = $post ? $post->ID : 0;
        if (!$product_id) {
            return;
        }

        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        $product_name = $product ? esc_js($product->get_name()) : '';
        $category_ids = $product ? implode(',', $product->get_category_ids()) : '';
        $ajax_url = esc_url(admin_url('admin-ajax.php'));
        $nonce = wp_create_nonce('ams_sync_pv');

        echo '<script>(function(){var d=new FormData();'
            . 'd.append("action","ams_sync_product_view");'
            . 'd.append("nonce","' . $nonce . '");'
            . 'd.append("product_id",' . (int) $product_id . ');'
            . 'd.append("product_name","' . $product_name . '");'
            . 'd.append("category_ids","' . esc_js($category_ids) . '");'
            . 'var t="";try{t=document.cookie.match(/ams_subscriber_token=([^;]+)/);t=t?t[1]:"";}catch(e){}'
            . 'd.append("subscriber_token",t);'
            . 'navigator.sendBeacon("' . $ajax_url . '",d);'
            . '})();</script>';
    }

    /**
     * Handle the AJAX product view event.
     */
    public function handle_ajax(): void
    {
        check_ajax_referer('ams_sync_pv', 'nonce');

        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error('Missing product_id');
        }

        $payload = [
            'product_id'       => $product_id,
            'product_name'     => sanitize_text_field($_POST['product_name'] ?? ''),
            'category_ids'     => sanitize_text_field($_POST['category_ids'] ?? ''),
            'subscriber_token' => sanitize_text_field($_POST['subscriber_token'] ?? ''),
            'session_id'       => function_exists('WC') && WC()->session ? WC()->session->get_customer_id() : '',
            'viewed_at'        => current_time('mysql'),
        ];

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time() + 2,
                'ams_sync_dispatch',
                [
                    'event_type' => 'product_viewed',
                    'payload'    => $payload,
                ],
                'ams-sync'
            );
        }

        wp_send_json_success();
    }
}
