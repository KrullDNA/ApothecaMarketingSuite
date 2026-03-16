<?php
/**
 * Abandoned cart detection via Action Scheduler.
 *
 * Runs every 60 minutes. Flags checkout starts with no subsequent order
 * within the configurable timeout period.
 *
 * @package Apotheca\Marketing\Events
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Events;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class AbandonedCartDetector
{
    private const HOOK = 'ams_abandoned_cart_check';

    public function __construct()
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'detect']);
    }

    /**
     * Schedule the recurring check if not already scheduled.
     */
    public function schedule(): void
    {
        if (!function_exists('as_has_scheduled_action')) {
            return;
        }

        if (!as_has_scheduled_action(self::HOOK)) {
            as_schedule_recurring_action(
                time(),
                HOUR_IN_SECONDS,
                self::HOOK,
                [],
                'ams'
            );
        }
    }

    /**
     * Detect abandoned carts.
     *
     * Finds subscribers who started checkout but have not placed an order
     * within the timeout window (default 60 minutes).
     */
    public function detect(): void
    {
        global $wpdb;

        $timeout_minutes = (int) Settings::get('abandoned_cart_timeout', 60);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($timeout_minutes * 60));
        $events_table = $wpdb->prefix . 'ams_events';

        // Find started_checkout events older than cutoff that don't have
        // a subsequent placed_order event for the same subscriber.
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT e.subscriber_id, e.event_data, e.created_at
             FROM {$events_table} e
             WHERE e.event_type = 'started_checkout'
               AND e.created_at <= %s
               AND NOT EXISTS (
                   SELECT 1 FROM {$events_table} e2
                   WHERE e2.subscriber_id = e.subscriber_id
                     AND e2.event_type = 'placed_order'
                     AND e2.created_at > e.created_at
               )
               AND NOT EXISTS (
                   SELECT 1 FROM {$events_table} e3
                   WHERE e3.subscriber_id = e.subscriber_id
                     AND e3.event_type = 'abandoned_cart'
                     AND e3.created_at > e.created_at
               )
             ORDER BY e.created_at DESC
             LIMIT 200",
            $cutoff
        ));

        if (empty($results)) {
            return;
        }

        $event_tracker = new EventTracker();

        foreach ($results as $row) {
            $event_data = json_decode($row->event_data, true) ?: [];

            $event_tracker->record_event(
                (int) $row->subscriber_id,
                'abandoned_cart',
                [
                    'original_checkout_at' => $row->created_at,
                    'cart_total'           => $event_data['cart_total'] ?? 0,
                    'items'                => $event_data['items'] ?? [],
                ]
            );

            do_action('ams_cart_abandoned', (int) $row->subscriber_id, $event_data);
        }
    }
}
