<?php
/**
 * Sync event ingestor — maps inbound events to internal actions.
 *
 * @package Apotheca\Marketing\Sync
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sync;

defined('ABSPATH') || exit;

final class SyncIngestor
{
    /**
     * Handle an inbound sync event.
     *
     * @return bool True if processed successfully.
     */
    public function handle(string $event_type, array $payload): bool
    {
        return match ($event_type) {
            'customer_registered'  => $this->handle_customer_registered($payload),
            'order_placed'         => $this->handle_order_placed($payload),
            'order_status_changed' => $this->handle_order_status_changed($payload),
            'cart_updated'         => $this->handle_cart_updated($payload),
            'product_viewed'       => $this->handle_product_viewed($payload),
            'checkout_started'     => $this->handle_checkout_started($payload),
            'abandoned_cart'       => $this->handle_abandoned_cart($payload),
            default                => false,
        };
    }

    /**
     * customer_registered: create/update subscriber, fire welcome flow.
     */
    private function handle_customer_registered(array $payload): bool
    {
        $email = sanitize_email($payload['email'] ?? '');
        if (!$email) {
            return false;
        }

        $subscriber_id = $this->find_or_create_subscriber($email, [
            'first_name' => sanitize_text_field($payload['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($payload['last_name'] ?? ''),
            'source'     => 'sync_registration',
        ]);

        if (!$subscriber_id) {
            return false;
        }

        $this->insert_event($subscriber_id, 'customer_registered', $payload);

        // Fire welcome flow trigger.
        do_action('ams_trigger_welcome_series', $subscriber_id);

        return true;
    }

    /**
     * order_placed: create/update subscriber, log event, update stats, fire flow.
     */
    private function handle_order_placed(array $payload): bool
    {
        $email = sanitize_email($payload['customer_email'] ?? '');
        if (!$email) {
            return false;
        }

        $subscriber_id = $this->find_or_create_subscriber($email, [
            'first_name' => sanitize_text_field($payload['billing_first_name'] ?? ''),
            'last_name'  => sanitize_text_field($payload['billing_last_name'] ?? ''),
            'source'     => 'checkout',
        ]);

        if (!$subscriber_id) {
            return false;
        }

        // Extract product IDs from items.
        $product_ids = [];
        $items = $payload['product_ids'] ?? [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['product_id'])) {
                    $product_ids[] = (int) $item['product_id'];
                }
            }
        }

        $this->insert_event($subscriber_id, 'placed_order', $payload, (int) ($payload['order_id'] ?? 0), $product_ids);

        // Update subscriber stats.
        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';
        $order_total = (float) ($payload['order_total'] ?? 0);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET total_orders = total_orders + 1, total_spent = total_spent + %f, last_order_date = %s, updated_at = %s WHERE id = %d",
            $order_total,
            current_time('mysql'),
            current_time('mysql'),
            $subscriber_id
        ));

        // Fire post-purchase flow trigger.
        do_action('ams_trigger_post_purchase', $subscriber_id, $payload);

        return true;
    }

    /**
     * order_status_changed: log event, fire relevant triggers.
     */
    private function handle_order_status_changed(array $payload): bool
    {
        $email = sanitize_email($payload['customer_email'] ?? '');
        if (!$email) {
            return false;
        }

        $subscriber_id = $this->find_subscriber_by_email($email);
        if (!$subscriber_id) {
            return false;
        }

        $this->insert_event($subscriber_id, 'order_status_changed', $payload, (int) ($payload['order_id'] ?? 0));

        $new_status = $payload['new_status'] ?? '';

        if ($new_status === 'completed') {
            do_action('ams_trigger_post_purchase', $subscriber_id, $payload);
        }

        if ($new_status === 'refunded') {
            $this->insert_event($subscriber_id, 'refund_requested', $payload, (int) ($payload['order_id'] ?? 0));
        }

        return true;
    }

    /**
     * cart_updated: log event, reset abandoned cart timer.
     */
    private function handle_cart_updated(array $payload): bool
    {
        $email = sanitize_email($payload['customer_email'] ?? '');
        $subscriber_id = $email ? $this->find_subscriber_by_email($email) : 0;

        if (!$subscriber_id) {
            return false;
        }

        // Extract product IDs.
        $product_ids = [];
        $items = $payload['cart_items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['product_id'])) {
                    $product_ids[] = (int) $item['product_id'];
                }
            }
        }

        $this->insert_event($subscriber_id, 'added_to_cart', $payload, 0, $product_ids);

        // Reset abandoned cart timer.
        do_action('ams_reset_abandoned_cart_timer', $subscriber_id);

        return true;
    }

    /**
     * product_viewed: log event, fire browse abandonment check.
     */
    private function handle_product_viewed(array $payload): bool
    {
        $token = sanitize_text_field($payload['subscriber_token'] ?? '');
        $subscriber_id = 0;

        if ($token) {
            $subscriber_id = $this->find_subscriber_by_token($token);
        }

        if (!$subscriber_id) {
            return false;
        }

        $product_id = (int) ($payload['product_id'] ?? 0);

        $this->insert_event($subscriber_id, 'viewed_product', [
            'product_id'   => $product_id,
            'product_name' => sanitize_text_field($payload['product_name'] ?? ''),
            'category_ids' => sanitize_text_field($payload['category_ids'] ?? ''),
        ], 0, $product_id ? [$product_id] : []);

        // Fire browse abandonment check.
        do_action('ams_trigger_browse_abandonment', $subscriber_id, $product_id);

        return true;
    }

    /**
     * checkout_started: find/create subscriber, log event.
     */
    private function handle_checkout_started(array $payload): bool
    {
        $email = sanitize_email($payload['customer_email'] ?? '');
        if (!$email) {
            return false;
        }

        $subscriber_id = $this->find_or_create_subscriber($email, [
            'source' => 'checkout',
        ]);

        if (!$subscriber_id) {
            return false;
        }

        $this->insert_event($subscriber_id, 'started_checkout', $payload);

        return true;
    }

    /**
     * abandoned_cart: find subscriber, log event, fire abandoned_cart flow trigger.
     */
    private function handle_abandoned_cart(array $payload): bool
    {
        $email = sanitize_email($payload['customer_email'] ?? '');
        if (!$email) {
            return false;
        }

        $subscriber_id = $this->find_subscriber_by_email($email);
        if (!$subscriber_id) {
            return false;
        }

        $this->insert_event($subscriber_id, 'abandoned_cart', $payload);

        // Fire abandoned_cart flow trigger.
        do_action('ams_cart_abandoned', $subscriber_id, $payload);

        return true;
    }

    /**
     * Find or create a subscriber by email.
     */
    private function find_or_create_subscriber(string $email, array $extra = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s LIMIT 1",
            $email
        ));

        if ($existing) {
            // Update name fields if provided and currently empty.
            if (!empty($extra['first_name']) || !empty($extra['last_name'])) {
                $updates = [];
                $types = [];

                if (!empty($extra['first_name'])) {
                    $updates['first_name'] = $extra['first_name'];
                    $types[] = '%s';
                }
                if (!empty($extra['last_name'])) {
                    $updates['last_name'] = $extra['last_name'];
                    $types[] = '%s';
                }

                $updates['updated_at'] = current_time('mysql');
                $types[] = '%s';

                $wpdb->update($table, $updates, ['id' => $existing->id], $types, ['%d']);
            }

            return (int) $existing->id;
        }

        // Create new subscriber.
        $wpdb->insert($table, [
            'email'         => $email,
            'first_name'    => $extra['first_name'] ?? '',
            'last_name'     => $extra['last_name'] ?? '',
            'status'        => 'subscribed',
            'source'        => $extra['source'] ?? 'sync',
            'subscribed_at' => current_time('mysql'),
            'unsubscribe_token' => wp_generate_password(32, false),
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * Find subscriber by email.
     */
    private function find_subscriber_by_email(string $email): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s LIMIT 1",
            $email
        ));

        return $row ? (int) $row->id : 0;
    }

    /**
     * Find subscriber by unsubscribe token.
     */
    private function find_subscriber_by_token(string $token): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE unsubscribe_token = %s LIMIT 1",
            $token
        ));

        return $row ? (int) $row->id : 0;
    }

    /**
     * Insert an event into ams_events.
     */
    private function insert_event(int $subscriber_id, string $event_type, array $data, int $order_id = 0, array $product_ids = []): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_events';

        $wpdb->insert($table, [
            'subscriber_id' => $subscriber_id,
            'event_type'    => $event_type,
            'event_data'    => wp_json_encode($data),
            'woo_order_id'  => $order_id ?: null,
            'product_ids'   => !empty($product_ids) ? wp_json_encode($product_ids) : null,
            'created_at'    => current_time('mysql'),
        ], ['%d', '%s', '%s', '%d', '%s', '%s']);
    }
}
