<?php
/**
 * Segment calculator — evaluates segments against all subscribers.
 *
 * Supports lazy evaluation (on demand) and background recalculation
 * via Action Scheduler every 6 hours.
 *
 * @package Apotheca\Marketing\Segments
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Segments;

defined('ABSPATH') || exit;

final class SegmentCalculator
{
    private const RECALC_HOOK = 'ams_segment_recalculate';

    private SegmentRepository $segments;
    private ConditionEvaluator $evaluator;

    public function __construct()
    {
        $this->segments = new SegmentRepository();
        $this->evaluator = new ConditionEvaluator();

        add_action('init', [$this, 'schedule_recalculation']);
        add_action(self::RECALC_HOOK, [$this, 'recalculate_all']);
    }

    /**
     * Schedule recurring background recalculation every 6 hours.
     */
    public function schedule_recalculation(): void
    {
        if (!function_exists('as_has_scheduled_action')) {
            return;
        }

        if (!as_has_scheduled_action(self::RECALC_HOOK)) {
            as_schedule_recurring_action(
                time(),
                6 * HOUR_IN_SECONDS,
                self::RECALC_HOOK,
                [],
                'ams'
            );
        }
    }

    /**
     * Recalculate all segments (background job).
     */
    public function recalculate_all(): void
    {
        $all_segments = $this->segments->list();

        foreach ($all_segments as $segment) {
            $count = $this->calculate($segment);
            $this->segments->update_count((int) $segment->id, $count);
        }
    }

    /**
     * Calculate subscriber count for a single segment.
     */
    public function calculate(object $segment): int
    {
        $conditions = json_decode($segment->conditions ?: '{}', true) ?: [];
        if (empty($conditions)) {
            return 0;
        }

        return $this->count_matching_subscribers($conditions);
    }

    /**
     * Count subscribers matching conditions (for live preview).
     */
    public function count_matching_subscribers(array $conditions): int
    {
        global $wpdb;

        $subscribers_table = $wpdb->prefix . 'ams_subscribers';
        $batch_size = 500;
        $offset = 0;
        $count = 0;

        do {
            $subscribers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$subscribers_table} WHERE status = 'subscribed' ORDER BY id ASC LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            if (empty($subscribers)) {
                break;
            }

            foreach ($subscribers as $subscriber) {
                if ($this->evaluator->matches($subscriber, $conditions)) {
                    $count++;
                }
            }

            $offset += $batch_size;
        } while (count($subscribers) === $batch_size);

        return $count;
    }

    /**
     * Get subscriber IDs matching a segment's conditions.
     *
     * @return int[]
     */
    public function get_matching_subscriber_ids(array $conditions, int $limit = 0): array
    {
        global $wpdb;

        $subscribers_table = $wpdb->prefix . 'ams_subscribers';
        $batch_size = 500;
        $offset = 0;
        $ids = [];

        do {
            $subscribers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$subscribers_table} WHERE status = 'subscribed' ORDER BY id ASC LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            if (empty($subscribers)) {
                break;
            }

            foreach ($subscribers as $subscriber) {
                if ($this->evaluator->matches($subscriber, $conditions)) {
                    $ids[] = (int) $subscriber->id;

                    if ($limit > 0 && count($ids) >= $limit) {
                        return $ids;
                    }
                }
            }

            $offset += $batch_size;
        } while (count($subscribers) === $batch_size);

        return $ids;
    }
}
