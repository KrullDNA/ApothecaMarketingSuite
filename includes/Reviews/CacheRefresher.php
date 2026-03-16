<?php
/**
 * Nightly review cache refresh via Action Scheduler.
 *
 * Orchestrates WooCommerce + Judge.me importers, purges stale entries.
 *
 * @package Apotheca\Marketing\Reviews
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Reviews;

defined('ABSPATH') || exit;

final class CacheRefresher
{
    private const HOOK = 'ams_refresh_reviews_cache';

    public function __construct()
    {
        add_action(self::HOOK, [$this, 'refresh']);

        // Schedule nightly if not already scheduled.
        add_action('init', [$this, 'schedule']);
    }

    /**
     * Ensure the nightly job is scheduled.
     */
    public function schedule(): void
    {
        if (!function_exists('as_has_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (!as_has_scheduled_action(self::HOOK)) {
            as_schedule_recurring_action(
                strtotime('tomorrow 3:00am'),
                DAY_IN_SECONDS,
                self::HOOK,
                [],
                'ams-reviews'
            );
        }
    }

    /**
     * Run the full cache refresh.
     */
    public function refresh(): void
    {
        // 1. Purge stale entries older than 48 hours.
        $this->purge_stale();

        // 2. Import WooCommerce native reviews.
        $wc_importer = new WooCommerceImporter();
        $wc_importer->import(200);

        // 3. Import Judge.me reviews (if available).
        $judgeme_importer = new JudgeMeImporter();
        $judgeme_importer->import();

        // 4. Update last refresh timestamp.
        update_option('ams_reviews_last_refresh', current_time('mysql'));
    }

    /**
     * Delete cache entries older than 48 hours to keep data fresh.
     */
    private function purge_stale(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';
        $cutoff = gmdate('Y-m-d H:i:s', time() - (48 * HOUR_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE cached_at < %s",
            $cutoff
        ));
    }
}
