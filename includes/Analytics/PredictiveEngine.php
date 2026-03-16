<?php
/**
 * Predictive analytics engine.
 *
 * Calculates predicted next order date, predicted CLV (12-month),
 * and churn risk score (0-100) on a nightly Action Scheduler job.
 *
 * @package Apotheca\Marketing\Analytics
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Analytics;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class PredictiveEngine
{
    private const HOOK = 'ams_predictive_nightly';

    public function __construct()
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'calculate_all']);
    }

    /**
     * Schedule the nightly predictive analytics job.
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
     * Calculate predictive metrics for all subscribers with order history.
     */
    public function calculate_all(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ams_subscribers';
        $events_table = $wpdb->prefix . 'ams_events';
        $batch_size = 500;
        $offset = 0;
        $repo = new SubscriberRepository();

        do {
            $subscribers = $wpdb->get_results($wpdb->prepare(
                "SELECT id, email, total_orders, total_spent, last_order_date
                 FROM {$table}
                 WHERE total_orders > 0 AND last_order_date IS NOT NULL
                 ORDER BY id ASC
                 LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            if (empty($subscribers)) {
                break;
            }

            foreach ($subscribers as $sub) {
                // Get all order dates for this subscriber.
                $order_dates = $wpdb->get_col($wpdb->prepare(
                    "SELECT created_at FROM {$events_table}
                     WHERE subscriber_id = %d AND event_type = 'placed_order'
                     ORDER BY created_at ASC",
                    (int) $sub->id
                ));

                $avg_gap = $this->calculate_average_order_gap($order_dates);
                $predicted_next = $this->predict_next_order($sub->last_order_date, $avg_gap);
                $predicted_clv = $this->predict_clv($sub, $avg_gap);
                $churn_risk = $this->calculate_churn_risk($sub->last_order_date, $avg_gap);

                $repo->update((int) $sub->id, [
                    'predicted_next_order' => $predicted_next,
                    'predicted_clv'        => $predicted_clv,
                    'churn_risk_score'     => $churn_risk,
                ]);
            }

            $offset += $batch_size;
        } while (count($subscribers) === $batch_size);
    }

    /**
     * Calculate the average number of days between orders.
     *
     * @param string[] $order_dates
     */
    private function calculate_average_order_gap(array $order_dates): float
    {
        if (count($order_dates) < 2) {
            return 0.0;
        }

        $timestamps = array_map('strtotime', $order_dates);
        sort($timestamps);

        $gaps = [];
        for ($i = 1, $count = count($timestamps); $i < $count; $i++) {
            $gap_days = ($timestamps[$i] - $timestamps[$i - 1]) / DAY_IN_SECONDS;
            if ($gap_days > 0) {
                $gaps[] = $gap_days;
            }
        }

        return empty($gaps) ? 0.0 : array_sum($gaps) / count($gaps);
    }

    /**
     * Predict the next order date based on last order + average gap.
     */
    private function predict_next_order(?string $last_order_date, float $avg_gap): ?string
    {
        if (empty($last_order_date) || $avg_gap <= 0) {
            return null;
        }

        $last_ts = strtotime($last_order_date);
        if ($last_ts === false) {
            return null;
        }

        $predicted_ts = $last_ts + (int) ($avg_gap * DAY_IN_SECONDS);

        // If the predicted date is in the past, project forward.
        $now = time();
        while ($predicted_ts < $now) {
            $predicted_ts += (int) ($avg_gap * DAY_IN_SECONDS);
        }

        return gmdate('Y-m-d H:i:s', $predicted_ts);
    }

    /**
     * Predict 12-month CLV: average order value x predicted order frequency over 12 months.
     */
    private function predict_clv(object $subscriber, float $avg_gap): float
    {
        $total_orders = (int) ($subscriber->total_orders ?? 0);
        $total_spent = (float) ($subscriber->total_spent ?? 0);

        if ($total_orders === 0) {
            return 0.0;
        }

        $aov = $total_spent / $total_orders;

        if ($avg_gap <= 0) {
            // Single order — assume one more order in 12 months.
            return round($aov, 2);
        }

        // Predicted orders in 12 months.
        $predicted_orders = 365.0 / $avg_gap;

        return round($aov * $predicted_orders, 2);
    }

    /**
     * Calculate churn risk score (0-100).
     *
     * Score = (days since last order / average reorder interval), normalised to 0-100.
     * Score > 70 = high risk.
     */
    private function calculate_churn_risk(?string $last_order_date, float $avg_gap): int
    {
        if (empty($last_order_date)) {
            return 100;
        }

        $last_ts = strtotime($last_order_date);
        if ($last_ts === false) {
            return 100;
        }

        $days_since = max(0, (time() - $last_ts) / DAY_IN_SECONDS);

        if ($avg_gap <= 0) {
            // Only one order — use 90-day default interval.
            $avg_gap = 90.0;
        }

        // Ratio of time elapsed vs expected reorder interval.
        $ratio = $days_since / $avg_gap;

        // Normalise: ratio 0 = 0 risk, ratio 1 = 50 risk, ratio 2+ = 100 risk.
        $score = min(100, (int) round($ratio * 50));

        return max(0, $score);
    }
}
