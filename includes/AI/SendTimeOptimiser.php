<?php
/**
 * AI send-time optimisation.
 *
 * Nightly Action Scheduler job calculates each subscriber's best send hour
 * based on historical open data from ams_sends.
 * Default: 10am for subscribers with < 5 sends.
 *
 * @package Apotheca\Marketing\AI
 */

declare(strict_types=1);

namespace Apotheca\Marketing\AI;

use Apotheca\Marketing\Settings;
use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class SendTimeOptimiser
{
    private const HOOK      = 'ams_send_time_optimise';
    private const BATCH     = 500;
    private const DEFAULT_HOUR = 10;

    public function __construct()
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'calculate_all']);
    }

    /**
     * Schedule nightly calculation.
     */
    public function schedule(): void
    {
        if (!Settings::get('ai_send_time_enabled', true)) {
            return;
        }

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
     * Calculate best send hour for all subscribers.
     */
    public function calculate_all(): void
    {
        if (!Settings::get('ai_send_time_enabled', true)) {
            return;
        }

        global $wpdb;
        $subs_table  = $wpdb->prefix . 'ams_subscribers';
        $sends_table = $wpdb->prefix . 'ams_sends';

        $offset = 0;

        while (true) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $subscribers = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$subs_table} WHERE status = 'subscribed' ORDER BY id ASC LIMIT %d OFFSET %d",
                self::BATCH,
                $offset
            ));

            if (empty($subscribers)) {
                break;
            }

            foreach ($subscribers as $sub) {
                $this->calculate_for_subscriber((int) $sub->id);
            }

            $offset += self::BATCH;
        }
    }

    /**
     * Calculate best send hour for a single subscriber.
     */
    public function calculate_for_subscriber(int $subscriber_id): int
    {
        global $wpdb;
        $sends_table = $wpdb->prefix . 'ams_sends';
        $repo = new SubscriberRepository();

        // Count total sends for this subscriber.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $send_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$sends_table} WHERE subscriber_id = %d AND sent_at IS NOT NULL",
            $subscriber_id
        ));

        if ($send_count < 5) {
            $repo->update($subscriber_id, ['best_send_hour' => self::DEFAULT_HOUR]);
            return self::DEFAULT_HOUR;
        }

        // Get open hours distribution.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $hours = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(opened_at) as hour, COUNT(*) as cnt
             FROM {$sends_table}
             WHERE subscriber_id = %d AND opened_at IS NOT NULL
             GROUP BY HOUR(opened_at)
             ORDER BY cnt DESC
             LIMIT 1",
            $subscriber_id
        ));

        $best_hour = self::DEFAULT_HOUR;
        if (!empty($hours) && isset($hours[0]->hour)) {
            $best_hour = (int) $hours[0]->hour;
        }

        $repo->update($subscriber_id, ['best_send_hour' => $best_hour]);

        return $best_hour;
    }

    /**
     * Get per-subscriber scheduled times for a campaign date.
     *
     * Returns an array of [subscriber_id => unix_timestamp] for scheduling
     * individual sends at each subscriber's optimal hour.
     *
     * @param int[]  $subscriber_ids
     * @param string $date           Y-m-d format date.
     * @return array<int, int>
     */
    public static function get_optimised_send_times(array $subscriber_ids, string $date): array
    {
        if (empty($subscriber_ids)) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';

        $placeholders = implode(',', array_fill(0, count($subscriber_ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, best_send_hour FROM {$table} WHERE id IN ({$placeholders})",
                ...$subscriber_ids
            )
        );

        $times = [];
        foreach ($rows ?: [] as $row) {
            $hour = (int) $row->best_send_hour;
            $times[(int) $row->id] = strtotime("{$date} {$hour}:00:00");
        }

        return $times;
    }
}
