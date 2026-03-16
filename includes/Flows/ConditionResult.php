<?php
/**
 * Result object for condition branch steps.
 *
 * @package Apotheca\Marketing\Flows
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows;

defined('ABSPATH') || exit;

final class ConditionResult
{
    public function __construct(
        public readonly bool $matched,
        public readonly ?int $next_step_id = null
    ) {}
}
