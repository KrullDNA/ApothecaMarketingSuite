<?php
/**
 * Base class for flow trigger handlers.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

use Apotheca\Marketing\Flows\EnrolmentRepository;
use Apotheca\Marketing\Flows\FlowRepository;
use Apotheca\Marketing\Flows\StepExecutor;

defined('ABSPATH') || exit;

abstract class AbstractTrigger implements TriggerInterface
{
    protected FlowRepository $flows;
    protected EnrolmentRepository $enrolments;
    protected StepExecutor $executor;

    public function __construct()
    {
        $this->flows = new FlowRepository();
        $this->enrolments = new EnrolmentRepository();
        $this->executor = new StepExecutor();
    }

    /**
     * Get the trigger type string for this handler.
     */
    abstract protected function get_trigger_type(): string;

    /**
     * Enrol subscriber in all active flows matching this trigger.
     */
    protected function enrol_subscriber(int $subscriber_id): void
    {
        $active_flows = $this->flows->get_active_by_trigger($this->get_trigger_type());

        foreach ($active_flows as $flow) {
            $enrolment_id = $this->enrolments->enrol((int) $flow->id, $subscriber_id);
            if ($enrolment_id > 0) {
                $this->executor->execute($enrolment_id);
            }
        }
    }
}
