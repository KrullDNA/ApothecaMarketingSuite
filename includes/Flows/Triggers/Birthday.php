<?php
/**
 * Birthday trigger — fires on subscriber birthday field.
 *
 * Checks daily via Action Scheduler.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

final class Birthday extends AbstractTrigger
{
    private const HOOK = 'ams_flow_birthday_check';

    protected function get_trigger_type(): string
    {
        return 'birthday';
    }

    public function register(): void
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'check']);
    }

    public function schedule(): void
    {
        if (!function_exists('as_has_scheduled_action')) {
            return;
        }

        if (!as_has_scheduled_action(self::HOOK)) {
            as_schedule_recurring_action(
                time(),
                DAY_IN_SECONDS,
                self::HOOK,
                [],
                'ams'
            );
        }
    }

    /**
     * Find subscribers whose birthday matches today.
     */
    public function check(): void
    {
        global $wpdb;

        $active_flows = $this->flows->get_active_by_trigger('birthday');
        if (empty($active_flows)) {
            return;
        }

        $subscribers_table = $wpdb->prefix . 'ams_subscribers';
        $today_month_day = gmdate('m-d');

        // Birthday is stored in custom_fields JSON as {"birthday": "MM-DD"} or {"birthday_month": "MM", "birthday_day": "DD"}.
        // Use JSON_EXTRACT for MySQL 5.7+ / MariaDB 10.2+.
        $subscribers = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$subscribers_table}
             WHERE status = 'subscribed'
               AND custom_fields IS NOT NULL
               AND (
                   JSON_UNQUOTE(JSON_EXTRACT(custom_fields, '$.birthday')) = %s
                   OR (
                       JSON_UNQUOTE(JSON_EXTRACT(custom_fields, '$.birthday_month')) = %s
                       AND JSON_UNQUOTE(JSON_EXTRACT(custom_fields, '$.birthday_day')) = %s
                   )
               )
             LIMIT 200",
            $today_month_day,
            gmdate('m'),
            gmdate('d')
        ));

        foreach ($subscribers ?: [] as $sub) {
            $this->enrol_subscriber((int) $sub->id);
        }
    }
}
