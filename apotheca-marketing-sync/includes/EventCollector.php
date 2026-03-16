<?php
/**
 * Event collector — hooks into WooCommerce events and schedules
 * Action Scheduler jobs for async dispatch to the marketing subdomain.
 *
 * @package Apotheca\MarketingSync
 */

declare(strict_types=1);

namespace Apotheca\MarketingSync;

defined('ABSPATH') || exit;

final class EventCollector
{
    public function __construct()
    {
        // 1. customer_registered
        add_action('user_register', [$this, 'on_customer_registered'], 20, 1);

        // 2. order_placed
        add_action('woocommerce_checkout_order_processed', [$this, 'on_order_placed'], 20, 1);

        // 3. order_status_changed
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 20, 3);

        // 4. cart_updated
        add_action('woocommerce_cart_updated', [$this, 'on_cart_updated'], 20);

        // 6. checkout_started
        add_action('woocommerce_checkout_init', [$this, 'on_checkout_started'], 20);
    }

    public function on_customer_registered(int $user_id): void
    {
        if (!Settings::is_event_enabled('customer_registered')) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $this->schedule('customer_registered', [
            'user_id'       => $user_id,
            'email'         => $user->user_email,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'registered_at' => current_time('mysql'),
        ]);
    }

    public function on_order_placed(int $order_id): void
    {
        if (!Settings::is_event_enabled('order_placed')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'product_id' => $item->get_product_id(),
                'name'       => $item->get_name(),
                'price'      => (float) $item->get_total(),
                'quantity'   => $item->get_quantity(),
            ];
        }

        $this->schedule('order_placed', [
            'order_id'           => $order_id,
            'customer_email'     => $order->get_billing_email(),
            'customer_id'        => $order->get_customer_id(),
            'order_total'        => (float) $order->get_total(),
            'order_status'       => $order->get_status(),
            'product_ids'        => $items,
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name'  => $order->get_billing_last_name(),
            'billing_city'       => $order->get_billing_city(),
            'billing_country'    => $order->get_billing_country(),
            'coupon_codes'       => $order->get_coupon_codes(),
            'created_at'         => current_time('mysql'),
        ]);
    }

    public function on_order_status_changed(int $order_id, string $old_status, string $new_status): void
    {
        if (!Settings::is_event_enabled('order_status_changed')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $this->schedule('order_status_changed', [
            'order_id'       => $order_id,
            'customer_email' => $order->get_billing_email(),
            'old_status'     => $old_status,
            'new_status'     => $new_status,
            'changed_at'     => current_time('mysql'),
        ]);
    }

    public function on_cart_updated(): void
    {
        if (!Settings::is_event_enabled('cart_updated')) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        $cart = WC()->cart;
        $items = [];
        foreach ($cart->get_cart() as $cart_item) {
            $items[] = [
                'product_id' => $cart_item['product_id'],
                'quantity'   => $cart_item['quantity'],
                'price'      => (float) ($cart_item['line_total'] ?? 0),
            ];
        }

        $customer_email = '';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $customer_email = $user->user_email;
        }

        $this->schedule('cart_updated', [
            'customer_email' => $customer_email,
            'session_id'     => WC()->session ? WC()->session->get_customer_id() : '',
            'cart_items'     => $items,
            'cart_total'     => (float) $cart->get_total('edit'),
            'updated_at'     => current_time('mysql'),
        ]);
    }

    public function on_checkout_started(): void
    {
        if (!Settings::is_event_enabled('checkout_started')) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        $cart = WC()->cart;
        $items = [];
        foreach ($cart->get_cart() as $cart_item) {
            $items[] = [
                'product_id' => $cart_item['product_id'],
                'quantity'   => $cart_item['quantity'],
                'price'      => (float) ($cart_item['line_total'] ?? 0),
            ];
        }

        $customer_email = '';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $customer_email = $user->user_email;
        }

        $this->schedule('checkout_started', [
            'customer_email' => $customer_email,
            'cart_items'     => $items,
            'cart_total'     => (float) $cart->get_total('edit'),
            'session_id'     => WC()->session ? WC()->session->get_customer_id() : '',
            'started_at'     => current_time('mysql'),
        ]);
    }

    /**
     * Schedule an async dispatch via Action Scheduler.
     */
    private function schedule(string $event_type, array $payload): void
    {
        if (!function_exists('as_schedule_single_action')) {
            return;
        }

        as_schedule_single_action(
            time() + 2,
            'ams_sync_dispatch',
            [
                'event_type' => $event_type,
                'payload'    => $payload,
            ],
            'ams-sync'
        );
    }
}
