<?php
/**
 * WooCommerce event tracker.
 *
 * Captures all subscriber events and stores them in ams_events.
 *
 * @package Apotheca\Marketing\Events
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Events;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class EventTracker
{
    private string $table;
    private SubscriberRepository $subscribers;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ams_events';
        $this->subscribers = new SubscriberRepository();
        $this->register_hooks();
    }

    private function register_hooks(): void
    {
        // Order placed.
        add_action('woocommerce_checkout_order_processed', [$this, 'track_placed_order'], 20, 3);

        // Order completed.
        add_action('woocommerce_order_status_completed', [$this, 'track_completed_purchase'], 10, 1);

        // Refund requested.
        add_action('woocommerce_order_status_refunded', [$this, 'track_refund_requested'], 10, 1);

        // Product viewed.
        add_action('woocommerce_after_single_product', [$this, 'track_viewed_product']);

        // Added to cart.
        add_action('woocommerce_add_to_cart', [$this, 'track_added_to_cart'], 10, 6);

        // Started checkout.
        add_action('woocommerce_after_checkout_form', [$this, 'track_started_checkout']);

        // Review written.
        add_action('comment_post', [$this, 'track_wrote_review'], 10, 3);
    }

    /**
     * Track order placed event.
     */
    public function track_placed_order(int $order_id, array $posted_data, \WC_Order $order): void
    {
        $subscriber = $this->get_subscriber_for_order($order);
        if (!$subscriber) {
            return;
        }

        $product_ids = [];
        $items_data = [];
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product_ids[] = $product_id;
            $items_data[] = [
                'product_id'   => $product_id,
                'variation_id' => $item->get_variation_id(),
                'name'         => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'total'        => $item->get_total(),
            ];
        }

        $this->record_event(
            (int) $subscriber->id,
            'placed_order',
            [
                'order_total'    => $order->get_total(),
                'order_currency' => $order->get_currency(),
                'items'          => $items_data,
                'coupon_codes'   => $order->get_coupon_codes(),
            ],
            $order_id,
            $product_ids
        );

        // Update subscriber order stats.
        $this->update_subscriber_order_stats($subscriber);
    }

    /**
     * Track order completed event.
     */
    public function track_completed_purchase(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $subscriber = $this->get_subscriber_for_order($order);
        if (!$subscriber) {
            return;
        }

        $product_ids = array_map(fn($item) => $item->get_product_id(), array_values($order->get_items()));

        $this->record_event(
            (int) $subscriber->id,
            'completed_purchase',
            [
                'order_total'  => $order->get_total(),
                'order_status' => $order->get_status(),
            ],
            $order_id,
            $product_ids
        );
    }

    /**
     * Track refund requested event.
     */
    public function track_refund_requested(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $subscriber = $this->get_subscriber_for_order($order);
        if (!$subscriber) {
            return;
        }

        $this->record_event(
            (int) $subscriber->id,
            'refund_requested',
            [
                'order_total'   => $order->get_total(),
                'refund_amount' => $order->get_total_refunded(),
            ],
            $order_id
        );
    }

    /**
     * Track product viewed event.
     *
     * Outputs a small inline script to fire an AJAX call — no site-wide JS loaded.
     */
    public function track_viewed_product(): void
    {
        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $product_id = $product->get_id();
        $nonce = wp_create_nonce('ams_track_event');
        $ajax_url = admin_url('admin-ajax.php');

        // Inline script — only on single product pages.
        $script = sprintf(
            '(function(){var x=new XMLHttpRequest();x.open("POST","%s");x.setRequestHeader("Content-Type","application/x-www-form-urlencoded");x.send("action=ams_track_event&event_type=viewed_product&product_id=%d&_wpnonce=%s");})();',
            esc_url($ajax_url),
            (int) $product_id,
            esc_js($nonce)
        );

        wp_add_inline_script('jquery', $script);

        // Also register AJAX handlers.
        $this->register_ajax_handlers();
    }

    /**
     * Track added to cart event.
     */
    public function track_added_to_cart(string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data): void
    {
        $email = $this->get_current_user_email();
        if (empty($email)) {
            return;
        }

        $subscriber = $this->subscribers->find_by_email($email);
        if (!$subscriber) {
            return;
        }

        $product = wc_get_product($product_id);

        $this->record_event(
            (int) $subscriber->id,
            'added_to_cart',
            [
                'product_name' => $product ? $product->get_name() : '',
                'quantity'     => $quantity,
                'variation_id' => $variation_id,
                'price'        => $product ? $product->get_price() : 0,
            ],
            null,
            [$product_id]
        );
    }

    /**
     * Track started checkout event.
     */
    public function track_started_checkout(): void
    {
        $email = $this->get_current_user_email();
        if (empty($email)) {
            return;
        }

        $subscriber = $this->subscribers->find_by_email($email);
        if (!$subscriber) {
            return;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $product_ids = [];
        $cart_items = [];
        foreach ($cart->get_cart() as $item) {
            $product_ids[] = $item['product_id'];
            $cart_items[] = [
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'line_total' => $item['line_total'],
            ];
        }

        $this->record_event(
            (int) $subscriber->id,
            'started_checkout',
            [
                'cart_total' => $cart->get_cart_contents_total(),
                'items'      => $cart_items,
            ],
            null,
            $product_ids
        );
    }

    /**
     * Track review written event.
     */
    public function track_wrote_review(int $comment_id, int|string $comment_approved, array $comment_data): void
    {
        if ($comment_data['comment_type'] !== 'review') {
            return;
        }

        $email = sanitize_email($comment_data['comment_author_email'] ?? '');
        if (empty($email)) {
            return;
        }

        $subscriber = $this->subscribers->find_by_email($email);
        if (!$subscriber) {
            return;
        }

        $product_id = (int) ($comment_data['comment_post_ID'] ?? 0);

        $this->record_event(
            (int) $subscriber->id,
            'wrote_review',
            [
                'comment_id' => $comment_id,
                'rating'     => (int) get_comment_meta($comment_id, 'rating', true),
            ],
            null,
            $product_id > 0 ? [$product_id] : []
        );
    }

    /**
     * Register AJAX handlers for front-end event tracking.
     */
    public function register_ajax_handlers(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $handler = function (): void {
            check_ajax_referer('ams_track_event');

            $event_type = sanitize_text_field(wp_unslash($_POST['event_type'] ?? ''));
            $product_id = (int) ($_POST['product_id'] ?? 0);

            $email = $this->get_current_user_email();
            if (empty($email) || empty($event_type)) {
                wp_send_json_error('Invalid request', 400);
            }

            $subscriber = $this->subscribers->find_by_email($email);
            if (!$subscriber) {
                wp_send_json_error('Unknown subscriber', 404);
            }

            $product = $product_id > 0 ? wc_get_product($product_id) : null;

            $this->record_event(
                (int) $subscriber->id,
                $event_type,
                [
                    'product_id'   => $product_id,
                    'product_name' => $product ? $product->get_name() : '',
                ],
                null,
                $product_id > 0 ? [$product_id] : []
            );

            wp_send_json_success();
        };

        add_action('wp_ajax_ams_track_event', $handler);
        add_action('wp_ajax_nopriv_ams_track_event', $handler);
    }

    /**
     * Record an event to the database.
     *
     * @param array<string, mixed>  $event_data
     * @param int[]                  $product_ids
     */
    public function record_event(int $subscriber_id, string $event_type, array $event_data = [], ?int $order_id = null, array $product_ids = []): void
    {
        global $wpdb;

        $wpdb->insert($this->table, [
            'subscriber_id' => $subscriber_id,
            'event_type'    => sanitize_text_field($event_type),
            'event_data'    => wp_json_encode($event_data),
            'woo_order_id'  => $order_id,
            'product_ids'   => !empty($product_ids) ? wp_json_encode(array_map('intval', $product_ids)) : null,
            'created_at'    => current_time('mysql', true),
        ]);

        do_action('ams_event_recorded', $subscriber_id, $event_type, $event_data);
    }

    /**
     * Update subscriber order totals from WooCommerce data.
     */
    private function update_subscriber_order_stats(object $subscriber): void
    {
        global $wpdb;

        $email = $subscriber->email;

        // Get order stats directly from WooCommerce orders.
        $orders = wc_get_orders([
            'billing_email' => $email,
            'status'        => ['wc-completed', 'wc-processing'],
            'limit'         => -1,
            'return'        => 'ids',
        ]);

        $total_orders = count($orders);
        $total_spent = 0.0;
        $last_order_date = null;

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $total_spent += (float) $order->get_total();
                $order_date = $order->get_date_completed() ?? $order->get_date_created();
                if ($order_date) {
                    $date_str = $order_date->date('Y-m-d H:i:s');
                    if (null === $last_order_date || $date_str > $last_order_date) {
                        $last_order_date = $date_str;
                    }
                }
            }
        }

        $this->subscribers->update((int) $subscriber->id, [
            'total_orders'   => $total_orders,
            'total_spent'    => $total_spent,
            'last_order_date' => $last_order_date,
        ]);
    }

    /**
     * Get the email of the current logged-in user.
     */
    private function get_current_user_email(): string
    {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return $user->user_email ?? '';
        }
        return '';
    }
}
