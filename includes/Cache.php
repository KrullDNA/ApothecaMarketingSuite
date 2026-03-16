<?php
/**
 * Object cache helper for high-traffic queries.
 *
 * Uses wp_cache_get / wp_cache_set with a default 1-hour TTL.
 * All cache keys are namespaced under 'ams_' group.
 *
 * @package Apotheca\Marketing
 */

declare(strict_types=1);

namespace Apotheca\Marketing;

defined('ABSPATH') || exit;

final class Cache
{
    private const GROUP = 'ams';
    private const TTL   = HOUR_IN_SECONDS;

    /**
     * Get a cached value or compute and cache it.
     *
     * @param string   $key      Cache key.
     * @param callable $callback Callback that returns the value to cache.
     * @param int      $ttl      TTL in seconds (default 1 hour).
     * @return mixed
     */
    public static function remember(string $key, callable $callback, int $ttl = self::TTL): mixed
    {
        $cached = wp_cache_get($key, self::GROUP);
        if (false !== $cached) {
            return $cached;
        }

        $value = $callback();
        wp_cache_set($key, $value, self::GROUP, $ttl);
        return $value;
    }

    /**
     * Invalidate a specific cache key.
     */
    public static function forget(string $key): void
    {
        wp_cache_delete($key, self::GROUP);
    }

    /**
     * Get active subscriber count (cached 1 hour).
     */
    public static function subscriber_count(): int
    {
        return (int) self::remember('subscriber_count', function (): int {
            global $wpdb;
            $table = $wpdb->prefix . 'ams_subscribers';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'subscribed'");
        });
    }

    /**
     * Get segment subscriber counts (cached 1 hour).
     *
     * @return array<int, int> segment_id => count
     */
    public static function segment_counts(): array
    {
        return self::remember('segment_counts', function (): array {
            global $wpdb;
            $table = $wpdb->prefix . 'ams_segments';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results("SELECT id, subscriber_count FROM {$table}");
            $counts = [];
            foreach ($rows ?: [] as $row) {
                $counts[(int) $row->id] = (int) $row->subscriber_count;
            }
            return $counts;
        });
    }

    /**
     * Get reviews cache count by product (cached 1 hour).
     */
    public static function reviews_count(?int $product_id = null): int
    {
        $key = $product_id ? "reviews_count_{$product_id}" : 'reviews_count_total';
        return (int) self::remember($key, function () use ($product_id): int {
            global $wpdb;
            $table = $wpdb->prefix . 'ams_reviews_cache';
            if ($product_id) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE product_id = %d",
                    $product_id
                ));
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        });
    }
}
