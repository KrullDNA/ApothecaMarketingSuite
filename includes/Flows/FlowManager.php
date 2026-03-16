<?php
/**
 * Flow manager — central orchestrator that registers all triggers
 * and wires the unsubscribe auto-exit hook.
 *
 * @package Apotheca\Marketing\Flows
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows;

use Apotheca\Marketing\Flows\Triggers\WelcomeSeries;
use Apotheca\Marketing\Flows\Triggers\AbandonedCart;
use Apotheca\Marketing\Flows\Triggers\PostPurchase;
use Apotheca\Marketing\Flows\Triggers\WinBack;
use Apotheca\Marketing\Flows\Triggers\BrowseAbandonment;
use Apotheca\Marketing\Flows\Triggers\Birthday;
use Apotheca\Marketing\Flows\Triggers\RfmChange;
use Apotheca\Marketing\Flows\Triggers\CustomEvent;

defined('ABSPATH') || exit;

final class FlowManager
{
    public function __construct()
    {
        $this->register_triggers();
        $this->register_hooks();

        // Initialise the step executor (registers its Action Scheduler handler).
        new StepExecutor();
    }

    /**
     * Register all trigger handlers.
     */
    private function register_triggers(): void
    {
        $triggers = [
            new WelcomeSeries(),
            new AbandonedCart(),
            new PostPurchase(),
            new WinBack(),
            new BrowseAbandonment(),
            new Birthday(),
            new RfmChange(),
            new CustomEvent(),
        ];

        foreach ($triggers as $trigger) {
            $trigger->register();
        }
    }

    /**
     * Register additional flow-related hooks.
     */
    private function register_hooks(): void
    {
        // Auto-exit all active enrolments when a subscriber unsubscribes.
        add_action('ams_subscriber_unsubscribed', [$this, 'exit_flows_on_unsubscribe']);
    }

    /**
     * Exit all active flow enrolments for an unsubscribed subscriber.
     */
    public function exit_flows_on_unsubscribe(int $subscriber_id): void
    {
        $repo = new EnrolmentRepository();
        $repo->exit_all_for_subscriber($subscriber_id, 'unsubscribed');
    }
}
