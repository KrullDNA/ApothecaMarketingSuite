<?php
/**
 * AI segment suggestions.
 *
 * Analyses subscriber data distribution and uses OpenAI to suggest
 * 5 segment ideas with pre-filled condition JSON.
 *
 * @package Apotheca\Marketing\AI
 */

declare(strict_types=1);

namespace Apotheca\Marketing\AI;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class SegmentSuggester
{
    private const HOOK = 'ams_ai_segment_suggestions';

    public function __construct()
    {
        add_action(self::HOOK, [$this, 'process']);
    }

    /**
     * Queue a segment suggestion generation request.
     *
     * @return string Request ID for polling.
     */
    public static function queue(): string
    {
        if (!Settings::get('ai_segment_suggestions_enabled', true)) {
            return '';
        }

        $request_id = wp_generate_password(16, false);

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time(),
                self::HOOK,
                [['request_id' => $request_id]],
                'ams'
            );
        }

        set_transient('ams_ai_segments_' . $request_id, ['status' => 'pending'], HOUR_IN_SECONDS);

        return $request_id;
    }

    /**
     * Process the suggestion request (called by Action Scheduler).
     */
    public function process(array $args): void
    {
        $request_id = $args['request_id'] ?? '';

        // Gather data distribution summary.
        $summary = $this->build_data_summary();

        $system = 'You are a marketing segmentation expert. Based on the subscriber data summary, suggest 5 actionable audience segments. Return ONLY a valid JSON array of 5 objects, each with: "name" (segment name), "description" (why this segment matters, 1 sentence), "conditions" (valid AMS condition JSON). The conditions format uses: {"match":"all"|"any","rules":[{"type":"...","operator":"...","value":"..."}]}. Available condition types: total_orders, total_spent, rfm_segment, churn_risk_score, last_order_date, subscribed_date, source, tag, predicted_clv, sms_opt_in. Operators: equals, not_equals, greater_than, less_than, contains, before, after, between. No markdown, no explanation.';

        $user = "Subscriber Data Summary:\n{$summary}\n\nSuggest 5 segments.";

        $result = OpenAiClient::chat($system, $user, 0.7, 1500);

        if ($result['success']) {
            $suggestions = json_decode($result['content'], true);
            if (!is_array($suggestions)) {
                preg_match('/\[.*\]/s', $result['content'], $matches);
                $suggestions = json_decode($matches[0] ?? '[]', true) ?: [];
            }

            set_transient('ams_ai_segments_' . $request_id, [
                'status'      => 'complete',
                'suggestions' => $suggestions,
            ], HOUR_IN_SECONDS);

            OpenAiClient::log(
                'segment_suggestions',
                mb_substr($user, 0, 500),
                mb_substr($result['content'], 0, 1000),
                $result['tokens_used'],
                $result['cost']
            );
        } else {
            set_transient('ams_ai_segments_' . $request_id, [
                'status' => 'error',
                'error'  => $result['error'],
            ], HOUR_IN_SECONDS);
        }
    }

    /**
     * Build a text summary of subscriber data distribution for the AI prompt.
     */
    private function build_data_summary(): string
    {
        global $wpdb;
        $subs = $wpdb->prefix . 'ams_subscribers';
        $sends = $wpdb->prefix . 'ams_sends';

        $lines = [];

        // Total subscribers.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs} WHERE status = 'subscribed'");
        $lines[] = "Total subscribers: {$total} (active: {$active})";

        // RFM segment distribution.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rfm = $wpdb->get_results("SELECT rfm_segment, COUNT(*) as cnt FROM {$subs} WHERE rfm_segment != '' GROUP BY rfm_segment ORDER BY cnt DESC");
        if ($rfm) {
            $parts = array_map(fn($r) => "{$r->rfm_segment}: {$r->cnt}", $rfm);
            $lines[] = "RFM segments: " . implode(', ', $parts);
        }

        // Average CLV.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $avg_clv = $wpdb->get_var("SELECT AVG(predicted_clv) FROM {$subs} WHERE predicted_clv > 0");
        if ($avg_clv) {
            $lines[] = "Average predicted CLV: $" . round((float) $avg_clv, 2);
        }

        // Order distribution.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $no_orders = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs} WHERE total_orders = 0");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $one_order = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs} WHERE total_orders = 1");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $repeat = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs} WHERE total_orders > 1");
        $lines[] = "Order distribution: no orders={$no_orders}, single order={$one_order}, repeat={$repeat}";

        // Churn risk distribution.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $high_churn = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs} WHERE churn_risk_score >= 67 AND status = 'subscribed'");
        $lines[] = "High churn risk (67+): {$high_churn} subscribers";

        // SMS opt-in rate.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sms_in = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs} WHERE sms_opt_in = 1");
        $lines[] = "SMS opted in: {$sms_in}";

        // Top product categories from recent orders.
        if (function_exists('wc_get_orders')) {
            $recent_orders = wc_get_orders(['limit' => 50, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids']);
            $cat_counts = [];
            foreach ($recent_orders as $oid) {
                $order = wc_get_order($oid);
                if (!$order) {
                    continue;
                }
                foreach ($order->get_items() as $item) {
                    $terms = wp_get_post_terms($item->get_product_id(), 'product_cat', ['fields' => 'names']);
                    if (!is_wp_error($terms)) {
                        foreach ($terms as $term_name) {
                            $cat_counts[$term_name] = ($cat_counts[$term_name] ?? 0) + 1;
                        }
                    }
                }
            }
            arsort($cat_counts);
            $top_cats = array_slice($cat_counts, 0, 5, true);
            if ($top_cats) {
                $parts = array_map(fn($n, $c) => "{$n} ({$c})", array_keys($top_cats), array_values($top_cats));
                $lines[] = "Top product categories (recent 50 orders): " . implode(', ', $parts);
            }
        }

        // Recent campaign open rates.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $campaign_stats = $wpdb->get_row(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
             FROM {$sends}
             WHERE channel = 'email' AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        if ($campaign_stats && (int) $campaign_stats->total > 0) {
            $open_rate = round(((int) $campaign_stats->opened / (int) $campaign_stats->total) * 100, 1);
            $lines[] = "30-day email open rate: {$open_rate}% ({$campaign_stats->total} sent)";
        }

        return implode("\n", $lines);
    }
}
