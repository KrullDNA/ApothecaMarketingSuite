<?php
/**
 * Interface for flow trigger handlers.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

interface TriggerInterface
{
    /**
     * Register WordPress hooks for this trigger.
     */
    public function register(): void;
}
