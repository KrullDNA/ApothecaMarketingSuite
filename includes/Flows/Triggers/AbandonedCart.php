<?php
/**
 * Abandoned cart trigger — fires when ams_events has abandoned_cart event.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

final class AbandonedCart extends AbstractTrigger
{
    protected function get_trigger_type(): string
    {
        return 'abandoned_cart';
    }

    public function register(): void
    {
        add_action('ams_cart_abandoned', [$this, 'on_cart_abandoned'], 10, 2);
    }

    /**
     * Fired by AbandonedCartDetector when a cart is flagged abandoned.
     */
    public function on_cart_abandoned(int $subscriber_id, array $event_data): void
    {
        $this->enrol_subscriber($subscriber_id);
    }
}
