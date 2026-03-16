<?php
/**
 * Custom event trigger — fires on any custom ams_events entry.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

final class CustomEvent extends AbstractTrigger
{
    protected function get_trigger_type(): string
    {
        return 'custom_event';
    }

    public function register(): void
    {
        add_action('ams_event_recorded', [$this, 'on_event_recorded'], 10, 3);
    }

    /**
     * Fired by EventTracker for every recorded event.
     */
    public function on_event_recorded(int $subscriber_id, string $event_type, array $event_data): void
    {
        // Skip standard WooCommerce events — only custom events trigger this.
        $standard_events = [
            'placed_order', 'completed_purchase', 'refund_requested',
            'viewed_product', 'added_to_cart', 'started_checkout',
            'abandoned_cart', 'wrote_review', 'browse_abandonment_triggered',
        ];

        if (in_array($event_type, $standard_events, true)) {
            return;
        }

        $active_flows = $this->flows->get_active_by_trigger('custom_event');

        foreach ($active_flows as $flow) {
            $config = json_decode($flow->trigger_config ?: '{}', true) ?: [];

            // If a specific event_type is configured, check match.
            if (!empty($config['event_type']) && $config['event_type'] !== $event_type) {
                continue;
            }

            $enrolment_id = $this->enrolments->enrol((int) $flow->id, $subscriber_id);
            if ($enrolment_id > 0) {
                $this->executor->execute($enrolment_id);
            }
        }
    }
}
