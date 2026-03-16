<?php
/**
 * Review selection logic — contextual review retrieval for emails.
 *
 * Called by TokenReplacer at send time to populate review blocks
 * with the most relevant reviews for each subscriber.
 *
 * @package Apotheca\Marketing\Reviews
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Reviews;

defined('ABSPATH') || exit;

final class ReviewSelector
{
    /**
     * Get reviews for a given context.
     *
     * @param int    $subscriber_id The subscriber ID.
     * @param array  $flow_context  Flow context data (trigger_type, order, cart, etc.).
     * @param string $mode          Selection mode: auto_contextual, specific_product, top_rated_sitewide, most_recent_sitewide.
     * @param int    $product_id    Product ID for specific_product mode.
     * @param int    $max           Maximum reviews to return.
     * @return array Array of review objects from ams_reviews_cache.
     */
    public static function get_reviews_for_context(
        int $subscriber_id,
        array $flow_context = [],
        string $mode = 'auto_contextual',
        int $product_id = 0,
        int $max = 3
    ): array {
        return match ($mode) {
            'specific_product'     => self::get_for_product($product_id, $max),
            'top_rated_sitewide'   => self::get_top_rated_sitewide($max),
            'most_recent_sitewide' => self::get_most_recent_sitewide($max),
            default                => self::get_auto_contextual($subscriber_id, $flow_context, $max),
        };
    }

    /**
     * Auto-contextual mode: picks reviews based on flow trigger context.
     */
    private static function get_auto_contextual(int $subscriber_id, array $flow_context, int $max): array
    {
        $trigger_type = $flow_context['trigger_type'] ?? '';

        // 1. Abandoned cart context.
        if ($trigger_type === 'abandoned_cart' || !empty($flow_context['abandoned_cart'])) {
            $reviews = self::get_for_abandoned_cart($subscriber_id, $max);
            if (!empty($reviews)) {
                return $reviews;
            }
        }

        // 2. Win-back context.
        if ($trigger_type === 'win_back' || !empty($flow_context['win_back'])) {
            $reviews = self::get_for_win_back($subscriber_id, $max);
            if (!empty($reviews)) {
                return $reviews;
            }
        }

        // 3. Fallback: sitewide verified 5-star reviews.
        return self::get_sitewide_verified($max);
    }

    /**
     * Abandoned cart: fetch reviews for products in the subscriber's most recent abandoned cart.
     */
    private static function get_for_abandoned_cart(int $subscriber_id, int $max): array
    {
        global $wpdb;
        $events_table = $wpdb->prefix . 'ams_events';

        // Get the most recent abandoned cart event for this subscriber.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT product_ids FROM {$events_table}
             WHERE subscriber_id = %d AND event_type = 'abandoned_cart'
             ORDER BY created_at DESC LIMIT 1",
            $subscriber_id
        ));

        if (!$event || empty($event->product_ids)) {
            return [];
        }

        $product_ids = json_decode($event->product_ids, true);
        if (!is_array($product_ids) || empty($product_ids)) {
            return [];
        }

        return self::get_for_product_ids($product_ids, 4, $max);
    }

    /**
     * Win-back: fetch reviews for products in the subscriber's most purchased category.
     */
    private static function get_for_win_back(int $subscriber_id, int $max): array
    {
        global $wpdb;
        $events_table = $wpdb->prefix . 'ams_events';

        // Get all completed purchase events for this subscriber.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT product_ids FROM {$events_table}
             WHERE subscriber_id = %d AND event_type = 'completed_purchase'",
            $subscriber_id
        ));

        if (empty($events)) {
            return [];
        }

        // Collect all purchased product IDs and map to categories.
        $category_counts = [];
        foreach ($events as $event) {
            $pids = json_decode($event->product_ids, true);
            if (!is_array($pids)) {
                continue;
            }
            foreach ($pids as $pid) {
                $product = function_exists('wc_get_product') ? wc_get_product((int) $pid) : null;
                if (!$product) {
                    continue;
                }
                $cat_ids = $product->get_category_ids();
                foreach ($cat_ids as $cat_id) {
                    $category_counts[$cat_id] = ($category_counts[$cat_id] ?? 0) + 1;
                }
            }
        }

        if (empty($category_counts)) {
            return [];
        }

        // Find the most frequent category.
        arsort($category_counts);
        $top_category_id = (int) array_key_first($category_counts);

        // Get product IDs in that category.
        if (!function_exists('wc_get_products')) {
            return [];
        }
        $product_ids = wc_get_products([
            'status'   => 'publish',
            'category' => [get_term($top_category_id)->slug ?? ''],
            'limit'    => 50,
            'return'   => 'ids',
        ]);

        if (empty($product_ids)) {
            return [];
        }

        return self::get_for_product_ids($product_ids, 5, $max);
    }

    /**
     * Sitewide verified 5-star reviews (fallback).
     */
    private static function get_sitewide_verified(int $max): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE rating = 5 AND verified_purchase = 1
             ORDER BY review_date DESC
             LIMIT %d",
            $max
        )) ?: [];
    }

    /**
     * Get reviews for a specific product.
     */
    private static function get_for_product(int $product_id, int $max): array
    {
        if (!$product_id) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE product_id = %d AND rating >= 4
             ORDER BY rating DESC, review_date DESC
             LIMIT %d",
            $product_id,
            $max
        )) ?: [];
    }

    /**
     * Get reviews for a set of product IDs with minimum rating.
     */
    private static function get_for_product_ids(array $product_ids, int $min_rating, int $max): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';

        $ids_clean = array_map('intval', $product_ids);
        $placeholders = implode(',', array_fill(0, count($ids_clean), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE product_id IN ({$placeholders}) AND rating >= %d
             ORDER BY rating DESC, review_date DESC
             LIMIT %d",
            ...array_merge($ids_clean, [$min_rating, $max])
        );

        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Top rated sitewide.
     */
    private static function get_top_rated_sitewide(int $max): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             ORDER BY rating DESC, review_date DESC
             LIMIT %d",
            $max
        )) ?: [];
    }

    /**
     * Most recent sitewide (rating >= 4).
     */
    private static function get_most_recent_sitewide(int $max): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE rating >= 4
             ORDER BY review_date DESC
             LIMIT %d",
            $max
        )) ?: [];
    }

    /**
     * Render reviews as HTML for email embedding.
     *
     * @param array $reviews Array of review objects from ams_reviews_cache.
     * @return string HTML string for email insertion.
     */
    public static function render_html(array $reviews): string
    {
        if (empty($reviews)) {
            return '';
        }

        $html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;">';

        foreach ($reviews as $review) {
            $stars = str_repeat('&#9733;', (int) $review->rating) . str_repeat('&#9734;', 5 - (int) $review->rating);

            $html .= '<tr><td style="padding:12px 0;border-bottom:1px solid #f3f4f6;">';

            // Product info (if available).
            if (!empty($review->product_name)) {
                $html .= '<div style="font-size:12px;color:#6b7280;margin-bottom:4px;">';
                if (!empty($review->product_url)) {
                    $html .= '<a href="' . esc_url($review->product_url) . '" style="color:#6b7280;text-decoration:none;">'
                        . esc_html($review->product_name) . '</a>';
                } else {
                    $html .= esc_html($review->product_name);
                }
                $html .= '</div>';
            }

            // Stars.
            $html .= '<div style="color:#f59e0b;font-size:16px;line-height:1;">' . $stars . '</div>';

            // Title.
            if (!empty($review->review_title)) {
                $html .= '<div style="font-weight:600;font-size:14px;margin-top:4px;">' . esc_html($review->review_title) . '</div>';
            }

            // Body (trimmed).
            $body = wp_trim_words($review->review_body ?? '', 30);
            if ($body) {
                $html .= '<p style="margin:4px 0 2px;font-size:14px;color:#374151;">' . esc_html($body) . '</p>';
            }

            // Reviewer + verified badge.
            $html .= '<span style="font-size:12px;color:#9ca3af;">— ' . esc_html($review->reviewer_name);
            if (!empty($review->verified_purchase)) {
                $html .= ' <span style="color:#10b981;">&#10003; Verified</span>';
            }
            $html .= '</span>';

            $html .= '</td></tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
