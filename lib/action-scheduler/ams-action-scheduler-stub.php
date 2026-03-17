<?php
/**
 * Action Scheduler stub for AMS standalone deployment.
 *
 * Provides the minimum ActionScheduler class and public API functions
 * so the plugin can activate and run without fatal errors.
 *
 * For full scheduled action support in production, replace the
 * lib/action-scheduler/ directory contents with the complete
 * Action Scheduler 3.x release from:
 * https://github.com/woocommerce/action-scheduler/releases
 *
 * This stub uses WordPress cron as a lightweight fallback to ensure
 * scheduled actions still fire (single and recurring).
 *
 * @package ActionScheduler
 */

defined('ABSPATH') || exit;

if (!class_exists('ActionScheduler')) {
    /**
     * Minimal ActionScheduler class stub.
     */
    final class ActionScheduler
    {
        /** @var string */
        private static $version = '3.8.2';

        public static function init(): void {}

        public static function runner(): ?object
        {
            return null;
        }

        public static function store(): ?object
        {
            return null;
        }

        public static function is_initialized(): bool
        {
            return true;
        }
    }
}

// ---- Public API functions (WP Cron fallback) ----

if (!function_exists('as_schedule_single_action')) {
    /**
     * Schedule a single action.
     */
    function as_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): int
    {
        $event_key = 'ams_as_' . md5($hook . wp_json_encode($args));
        if (!wp_next_scheduled($hook, $args)) {
            wp_schedule_single_event($timestamp, $hook, $args);
        }
        update_option($event_key, $timestamp);
        return (int) crc32($event_key);
    }
}

if (!function_exists('as_schedule_recurring_action')) {
    /**
     * Schedule a recurring action.
     */
    function as_schedule_recurring_action(int $timestamp, int $interval, string $hook, array $args = [], string $group = ''): int
    {
        if (!wp_next_scheduled($hook, $args)) {
            $recurrence = 'hourly';
            if ($interval >= DAY_IN_SECONDS) {
                $recurrence = 'daily';
            } elseif ($interval >= 12 * HOUR_IN_SECONDS) {
                $recurrence = 'twicedaily';
            }
            wp_schedule_event($timestamp, $recurrence, $hook, $args);
        }
        return (int) crc32('ams_as_' . md5($hook . wp_json_encode($args)));
    }
}

if (!function_exists('as_enqueue_async_action')) {
    /**
     * Enqueue an async action (runs as soon as possible).
     */
    function as_enqueue_async_action(string $hook, array $args = [], string $group = ''): int
    {
        return as_schedule_single_action(time(), $hook, $args, $group);
    }
}

if (!function_exists('as_has_scheduled_action')) {
    /**
     * Check if a scheduled action exists.
     */
    function as_has_scheduled_action(string $hook, ?array $args = null, string $group = ''): bool
    {
        return (bool) wp_next_scheduled($hook, $args ?? []);
    }
}

if (!function_exists('as_unschedule_action')) {
    /**
     * Unschedule a single action.
     */
    function as_unschedule_action(string $hook, ?array $args = null, string $group = ''): ?int
    {
        $timestamp = wp_next_scheduled($hook, $args ?? []);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook, $args ?? []);
        }
        return $timestamp ? (int) $timestamp : null;
    }
}

if (!function_exists('as_unschedule_all_actions')) {
    /**
     * Unschedule all actions with a given hook.
     */
    function as_unschedule_all_actions(string $hook, ?array $args = null, string $group = ''): void
    {
        wp_clear_scheduled_hook($hook, $args ?? []);
    }
}

if (!function_exists('as_next_scheduled_action')) {
    /**
     * Get the next scheduled timestamp for a hook.
     */
    function as_next_scheduled_action(string $hook, ?array $args = null, string $group = ''): ?int
    {
        $next = wp_next_scheduled($hook, $args ?? []);
        return $next ? (int) $next : null;
    }
}

if (!function_exists('as_get_scheduled_actions')) {
    /**
     * Get scheduled actions. Returns empty array in stub mode.
     */
    function as_get_scheduled_actions(array $args = [], string $return_format = 'OBJECT'): array
    {
        return [];
    }
}
