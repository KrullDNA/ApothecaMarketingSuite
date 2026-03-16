<?php
/**
 * AI product recommendation engine (algorithmic — no OpenAI).
 *
 * Generates personalised product recommendations based on subscriber's
 * purchase history, viewed products, and algorithmic scoring.
 *
 * Scoring: same category (+3), on sale (+2), high rating (+1), new (+1).
 *
 * @package Apotheca\Marketing\AI
 */

declare(strict_types=1);

namespace Apotheca\Marketing\AI;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class ProductRecommender
{
    /**
     * Get top N recommended products for a subscriber.
     *
     * @return \WC_Product[] Top recommended products.
     */
    public static function recommend(int $subscriber_id, int $count = 3): array
    {
        if (!Settings::get('ai_product_recs_enabled', true)) {
            return [];
        }

        if (!function_exists('wc_get_products')) {
            return [];
        }

        global $wpdb;
        $events_table = $wpdb->prefix . 'ams_events';

        // Get subscriber's purchased product IDs.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $purchased_rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT product_ids FROM {$events_table}
             WHERE subscriber_id = %d AND event_type = 'placed_order' AND product_ids IS NOT NULL",
            $subscriber_id
        ));

        $purchased_ids = [];
        foreach ($purchased_rows as $json) {
            $ids = json_decode($json, true);
            if (is_array($ids)) {
                $purchased_ids = array_merge($purchased_ids, $ids);
            }
        }
        $purchased_ids = array_unique(array_map('intval', $purchased_ids));

        // Get categories from purchased products.
        $purchased_cats = [];
        foreach ($purchased_ids as $pid) {
            $terms = wp_get_post_terms($pid, 'product_cat', ['fields' => 'ids']);
            if (!is_wp_error($terms)) {
                $purchased_cats = array_merge($purchased_cats, $terms);
            }
        }
        $purchased_cats = array_unique($purchased_cats);

        // Get viewed product categories too.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $viewed_rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT product_ids FROM {$events_table}
             WHERE subscriber_id = %d AND event_type = 'viewed_product' AND product_ids IS NOT NULL",
            $subscriber_id
        ));

        foreach ($viewed_rows as $json) {
            $ids = json_decode($json, true);
            if (is_array($ids)) {
                foreach ($ids as $vid) {
                    $terms = wp_get_post_terms((int) $vid, 'product_cat', ['fields' => 'ids']);
                    if (!is_wp_error($terms)) {
                        $purchased_cats = array_merge($purchased_cats, $terms);
                    }
                }
            }
        }
        $purchased_cats = array_unique($purchased_cats);

        // Query in-stock products not already purchased.
        $args = [
            'status'       => 'publish',
            'stock_status' => 'instock',
            'limit'        => 50,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'exclude'      => $purchased_ids,
        ];

        if (!empty($purchased_cats)) {
            $args['category'] = [];
            foreach ($purchased_cats as $cat_id) {
                $term = get_term($cat_id, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $args['category'][] = $term->slug;
                }
            }
        }

        $products = wc_get_products($args);

        if (empty($products)) {
            // Fallback: get any recent products.
            $products = wc_get_products([
                'status'       => 'publish',
                'stock_status' => 'instock',
                'limit'        => 20,
                'orderby'      => 'date',
                'order'        => 'DESC',
                'exclude'      => $purchased_ids,
            ]);
        }

        if (empty($products)) {
            return [];
        }

        // Score each product.
        $scored = [];
        $thirty_days_ago = strtotime('-30 days');

        foreach ($products as $product) {
            $score = 0;

            // Same category as purchased/viewed (+3).
            $prod_cats = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
            if (!is_wp_error($prod_cats) && array_intersect($prod_cats, $purchased_cats)) {
                $score += 3;
            }

            // On sale (+2).
            if ($product->is_on_sale()) {
                $score += 2;
            }

            // High rating (+1).
            if ((float) $product->get_average_rating() >= 4.0) {
                $score += 1;
            }

            // New — published in last 30 days (+1).
            $date_created = $product->get_date_created();
            if ($date_created && $date_created->getTimestamp() >= $thirty_days_ago) {
                $score += 1;
            }

            $scored[] = ['product' => $product, 'score' => $score];
        }

        // Sort by score descending.
        usort($scored, fn($a, $b) => $b['score'] - $a['score']);

        return array_map(fn($s) => $s['product'], array_slice($scored, 0, $count));
    }

    /**
     * Render product recommendations as HTML for email token replacement.
     */
    public static function render_html(int $subscriber_id, int $count = 3): string
    {
        $products = self::recommend($subscriber_id, $count);

        if (empty($products)) {
            return '';
        }

        $custom_template = Settings::get('ai_product_card_template', '');

        $html = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
        $html .= '<tr>';

        foreach ($products as $product) {
            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
            $name      = esc_html($product->get_name());
            $price     = $product->get_price_html();
            $url       = esc_url($product->get_permalink());

            if (!empty($custom_template)) {
                $card = str_replace(
                    ['{{rec_image}}', '{{rec_name}}', '{{rec_price}}', '{{rec_url}}'],
                    [$image_url, $name, strip_tags($price), $url],
                    $custom_template
                );
            } else {
                $card = '<td style="padding:10px;text-align:center;width:33%;" valign="top">';
                if ($image_url) {
                    $card .= '<a href="' . $url . '" style="text-decoration:none;">';
                    $card .= '<img src="' . esc_url($image_url) . '" width="150" height="150" alt="' . $name . '" style="display:block;margin:0 auto 8px;border-radius:4px;" />';
                    $card .= '</a>';
                }
                $card .= '<div style="font-size:14px;font-weight:600;margin-bottom:4px;">';
                $card .= '<a href="' . $url . '" style="color:#111827;text-decoration:none;">' . $name . '</a>';
                $card .= '</div>';
                $card .= '<div style="font-size:13px;color:#6b7280;margin-bottom:8px;">' . $price . '</div>';
                $card .= '<a href="' . $url . '" style="display:inline-block;padding:8px 16px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:4px;font-size:13px;">Shop Now</a>';
                $card .= '</td>';
            }

            $html .= $card;
        }

        $html .= '</tr></table>';

        return $html;
    }
}
