<?php
/**
 * Analytics aggregator — nightly cron to populate ams_analytics_daily.
 *
 * All dashboard queries read from this pre-aggregated table.
 *
 * @package Apotheca\Marketing\Analytics
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Analytics;

defined('ABSPATH') || exit;

final class AnalyticsAggregator
{
    private const HOOK = 'ams_analytics_aggregate';

    private string $daily_table;
    private string $sends_table;
    private string $attr_table;
    private string $subs_table;

    public function __construct()
    {
        global $wpdb;
        $this->daily_table = $wpdb->prefix . 'ams_analytics_daily';
        $this->sends_table = $wpdb->prefix . 'ams_sends';
        $this->attr_table  = $wpdb->prefix . 'ams_attributions';
        $this->subs_table  = $wpdb->prefix . 'ams_subscribers';

        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'aggregate']);
    }

    /**
     * Schedule nightly aggregation.
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
     * Aggregate metrics for yesterday (and today so far).
     */
    public function aggregate(): void
    {
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
        $today     = gmdate('Y-m-d');

        $this->aggregate_date($yesterday);
        $this->aggregate_date($today);
    }

    /**
     * Aggregate all metrics for a given date.
     */
    public function aggregate_date(string $date): void
    {
        global $wpdb;

        $date_start = $date . ' 00:00:00';
        $date_end   = $date . ' 23:59:59';

        // Email sends metrics.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $email_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'delivered' OR opened_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN bounced_at IS NOT NULL THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed,
                SUM(revenue_attributed) as revenue
             FROM {$this->sends_table}
             WHERE channel = 'email' AND sent_at BETWEEN %s AND %s",
            $date_start,
            $date_end
        ));

        if ($email_stats) {
            $this->upsert_metric($date, 'email_sent', (float) $email_stats->total_sent);
            $this->upsert_metric($date, 'email_delivered', (float) $email_stats->delivered);
            $this->upsert_metric($date, 'email_opened', (float) $email_stats->opened);
            $this->upsert_metric($date, 'email_clicked', (float) $email_stats->clicked);
            $this->upsert_metric($date, 'email_bounced', (float) $email_stats->bounced);
            $this->upsert_metric($date, 'email_unsubscribed', (float) $email_stats->unsubscribed);
            $this->upsert_metric($date, 'email_revenue', (float) $email_stats->revenue);
        }

        // SMS sends metrics.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sms_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN ('failed','permanently_failed') THEN 1 ELSE 0 END) as failed,
                SUM(revenue_attributed) as revenue
             FROM {$this->sends_table}
             WHERE channel = 'sms' AND sent_at BETWEEN %s AND %s",
            $date_start,
            $date_end
        ));

        if ($sms_stats) {
            $this->upsert_metric($date, 'sms_sent', (float) $sms_stats->total_sent);
            $this->upsert_metric($date, 'sms_delivered', (float) $sms_stats->delivered);
            $this->upsert_metric($date, 'sms_failed', (float) $sms_stats->failed);
            $this->upsert_metric($date, 'sms_revenue', (float) $sms_stats->revenue);
        }

        // Attribution revenue.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $attr_rev = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(order_total) FROM {$this->attr_table} WHERE attributed_at BETWEEN %s AND %s",
            $date_start,
            $date_end
        ));
        $this->upsert_metric($date, 'total_revenue', (float) ($attr_rev ?: 0));

        // Subscriber counts.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $new_subs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->subs_table} WHERE created_at BETWEEN %s AND %s",
            $date_start,
            $date_end
        ));
        $this->upsert_metric($date, 'new_subscribers', (float) ($new_subs ?: 0));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $unsubs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->subs_table} WHERE unsubscribed_at BETWEEN %s AND %s",
            $date_start,
            $date_end
        ));
        $this->upsert_metric($date, 'unsubscribes', (float) ($unsubs ?: 0));

        // Total active subscribers (snapshot).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->subs_table} WHERE status = 'subscribed'"
        );
        $this->upsert_metric($date, 'active_subscribers', (float) ($active ?: 0));
    }

    /**
     * Upsert a single metric value using INSERT ... ON DUPLICATE KEY UPDATE.
     */
    private function upsert_metric(string $date, string $key, float $value): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->daily_table} (date, metric_key, metric_value)
             VALUES (%s, %s, %f)
             ON DUPLICATE KEY UPDATE metric_value = %f",
            $date,
            $key,
            $value,
            $value
        ));
    }
}
