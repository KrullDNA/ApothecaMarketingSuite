<?php
/**
 * Interface for flow step processors.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

defined('ABSPATH') || exit;

interface StepProcessorInterface
{
    /**
     * Process a step for a subscriber.
     *
     * @param object $subscriber The subscriber record.
     * @param object $step       The flow step record.
     * @param object $enrolment  The flow enrolment record.
     * @return mixed
     */
    public function process(object $subscriber, object $step, object $enrolment): mixed;
}
