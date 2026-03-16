<?php
/**
 * Browse abandonment trigger — fires on viewed_product with no add_to_cart within 30 min.
 *
 * Runs via Action Scheduler periodic check.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

final class BrowseAbandonment extends AbstractTrigger
{
    private const HOOK = 'ams_flow_browse_abandon_check';

    protected function get_trigger_type(): string
    {
        return 'browse_abandonment';
    }

    public function register(): void
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'check']);
    }

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
     * Find viewed_product events with no subsequent add_to_cart within 30 minutes.
     */
    public function check(): void
    {
        global $wpdb;

        $active_flows = $this->flows->get_active_by_trigger('browse_abandonment');
        if (empty($active_flows)) {
            return;
        }

        $events_table = $wpdb->prefix . 'ams_events';
        $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * MINUTE_IN_SECONDS));

        // Find viewed_product events older than 30 min with no subsequent add_to_cart.
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT e.subscriber_id
             FROM {$events_table} e
             WHERE e.event_type = 'viewed_product'
               AND e.created_at <= %s
               AND NOT EXISTS (
                   SELECT 1 FROM {$events_table} e2
                   WHERE e2.subscriber_id = e.subscriber_id
                     AND e2.event_type = 'added_to_cart'
                     AND e2.created_at > e.created_at
               )
               AND NOT EXISTS (
                   SELECT 1 FROM {$events_table} e3
                   WHERE e3.subscriber_id = e.subscriber_id
                     AND e3.event_type = 'browse_abandonment_triggered'
                     AND e3.created_at > e.created_at
               )
             LIMIT 100",
            $cutoff
        ));

        if (empty($results)) {
            return;
        }

        $event_tracker = new \Apotheca\Marketing\Events\EventTracker();

        foreach ($results as $row) {
            $subscriber_id = (int) $row->subscriber_id;

            // Mark as triggered so we don't re-fire.
            $event_tracker->record_event($subscriber_id, 'browse_abandonment_triggered');

            $this->enrol_subscriber($subscriber_id);
        }
    }
}
