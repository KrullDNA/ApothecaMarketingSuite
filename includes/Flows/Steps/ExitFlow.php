<?php
/**
 * Exit flow step processor.
 *
 * Handled by StepExecutor — exits the enrolment.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

defined('ABSPATH') || exit;

final class ExitFlow implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        // Exit handling is in StepExecutor::execute().
        return true;
    }
}
