<?php
/**
 * Wait/delay flow step processor.
 *
 * This step is handled by the StepExecutor which reads delay_value
 * and delay_unit from the step record and schedules accordingly.
 * The process method is a no-op — the actual delay is in StepExecutor.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

defined('ABSPATH') || exit;

final class Wait implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        // Delay handling is in StepExecutor::execute().
        return true;
    }
}
