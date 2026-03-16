<?php
/**
 * Win-back trigger — fires when last_order_date > X days.
 *
 * Runs via Action Scheduler daily check.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

final class WinBack extends AbstractTrigger
{
    private const HOOK = 'ams_flow_win_back_check';

    protected function get_trigger_type(): string
    {
        return 'win_back';
    }

    public function register(): void
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'check']);
    }

    /**
     * Schedule daily check.
     */
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
     * Check for subscribers who haven't ordered in X days.
     */
    public function check(): void
    {
        global $wpdb;

        $active_flows = $this->flows->get_active_by_trigger('win_back');
        if (empty($active_flows)) {
            return;
        }

        $subscribers_table = $wpdb->prefix . 'ams_subscribers';

        foreach ($active_flows as $flow) {
            $config = json_decode($flow->trigger_config ?: '{}', true) ?: [];
            $days = (int) ($config['days_since_last_order'] ?? 90);
            $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

            $subscribers = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$subscribers_table}
                 WHERE status = 'subscribed'
                   AND last_order_date IS NOT NULL
                   AND last_order_date <= %s
                   AND total_orders > 0
                 LIMIT 200",
                $cutoff
            ));

            foreach ($subscribers ?: [] as $sub) {
                $enrolment_id = $this->enrolments->enrol((int) $flow->id, (int) $sub->id);
                if ($enrolment_id > 0) {
                    $this->executor->execute($enrolment_id);
                }
            }
        }
    }
}
