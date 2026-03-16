<?php
/**
 * RFM scoring engine.
 *
 * Calculates Recency, Frequency, Monetary scores (1-5) for all subscribers
 * on a nightly Action Scheduler job. Assigns named RFM segments.
 *
 * @package Apotheca\Marketing\Analytics
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Analytics;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class RfmEngine
{
    private const HOOK = 'ams_rfm_nightly';

    public function __construct()
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'calculate_all']);
    }

    /**
     * Schedule the nightly RFM calculation.
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
     * Calculate RFM scores for all subscribers with order history.
     */
    public function calculate_all(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ams_subscribers';

        // Get all subscribers who have placed at least one order.
        $subscribers = $wpdb->get_results(
            "SELECT id, email, total_orders, total_spent, last_order_date, rfm_segment
             FROM {$table}
             WHERE total_orders > 0 AND last_order_date IS NOT NULL"
        );

        if (empty($subscribers)) {
            return;
        }

        // Calculate quintile boundaries for F and M.
        $frequencies = array_map(fn($s) => (int) $s->total_orders, $subscribers);
        $monetaries = array_map(fn($s) => (float) $s->total_spent, $subscribers);

        sort($frequencies);
        sort($monetaries);

        $f_boundaries = $this->quintile_boundaries($frequencies);
        $m_boundaries = $this->quintile_boundaries($monetaries);

        $now = time();
        $repo = new SubscriberRepository();

        foreach ($subscribers as $sub) {
            $days_since = $sub->last_order_date
                ? max(0, (int) (($now - strtotime($sub->last_order_date)) / DAY_IN_SECONDS))
                : 9999;

            $r = $this->recency_score($days_since);
            $f = $this->score_by_boundaries((int) $sub->total_orders, $f_boundaries);
            $m = $this->score_by_boundaries((float) $sub->total_spent, $m_boundaries);

            $rfm_score = "{$r}{$f}{$m}";
            $rfm_segment = $this->assign_segment($r, $f, $m);

            $old_segment = $sub->rfm_segment ?? '';

            $repo->update((int) $sub->id, [
                'rfm_score'   => $rfm_score,
                'rfm_segment' => $rfm_segment,
            ]);

            // Fire segment change action if changed.
            if ($old_segment !== '' && $old_segment !== $rfm_segment) {
                do_action('ams_rfm_segment_changed', (int) $sub->id, $old_segment, $rfm_segment);
            }
        }
    }

    /**
     * Recency score (1-5): days since last order.
     * 5 = most recent (0-14 days), 1 = least recent (180+ days).
     */
    private function recency_score(int $days_since): int
    {
        return match (true) {
            $days_since <= 14  => 5,
            $days_since <= 30  => 4,
            $days_since <= 60  => 3,
            $days_since <= 180 => 2,
            default            => 1,
        };
    }

    /**
     * Calculate quintile boundaries from a sorted array of values.
     *
     * @return float[] Array of 4 boundary values (20th, 40th, 60th, 80th percentile).
     */
    private function quintile_boundaries(array $sorted_values): array
    {
        $count = count($sorted_values);
        if ($count < 5) {
            // Not enough data for meaningful quintiles; use simple ranges.
            $max = end($sorted_values) ?: 1;
            $step = $max / 5;
            return [$step, $step * 2, $step * 3, $step * 4];
        }

        return [
            $sorted_values[(int) floor($count * 0.2)],
            $sorted_values[(int) floor($count * 0.4)],
            $sorted_values[(int) floor($count * 0.6)],
            $sorted_values[(int) floor($count * 0.8)],
        ];
    }

    /**
     * Score a value (1-5) based on quintile boundaries.
     */
    private function score_by_boundaries(float $value, array $boundaries): int
    {
        return match (true) {
            $value <= $boundaries[0] => 1,
            $value <= $boundaries[1] => 2,
            $value <= $boundaries[2] => 3,
            $value <= $boundaries[3] => 4,
            default                  => 5,
        };
    }

    /**
     * Assign a named RFM segment based on R, F, M scores.
     *
     * Order matters — first match wins.
     */
    private function assign_segment(int $r, int $f, int $m): string
    {
        // Champions: R>=4, F>=4, M>=4
        if ($r >= 4 && $f >= 4 && $m >= 4) {
            return 'Champions';
        }

        // Big Spenders: M=5 (any R, F)
        if ($m === 5) {
            return 'Big Spenders';
        }

        // Loyal: R>=3, F>=4
        if ($r >= 3 && $f >= 4) {
            return 'Loyal';
        }

        // New Customers: R=5, F=1
        if ($r === 5 && $f === 1) {
            return 'New Customers';
        }

        // Potential: R>=4, F<=2
        if ($r >= 4 && $f <= 2) {
            return 'Potential';
        }

        // At Risk: R<=2, F>=3, M>=3
        if ($r <= 2 && $f >= 3 && $m >= 3) {
            return 'At Risk';
        }

        // About to Sleep: R<=3, F<=2, M<=2
        if ($r <= 3 && $f <= 2 && $m <= 2) {
            return 'About to Sleep';
        }

        // Lost: R=1, F<=2
        if ($r === 1 && $f <= 2) {
            return 'Lost';
        }

        return 'Other';
    }
}
