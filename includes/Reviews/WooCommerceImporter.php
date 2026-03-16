<?php
/**
 * WooCommerce native review importer.
 *
 * Pulls approved WC reviews via get_comments() and caches them
 * in the ams_reviews_cache table.
 *
 * @package Apotheca\Marketing\Reviews
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Reviews;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class WooCommerceImporter
{
    /**
     * Import WooCommerce reviews in batches.
     *
     * @param int $batch_size Number of reviews per batch.
     * @return int Number of reviews imported.
     */
    public function import(int $batch_size = 200): int
    {
        global $wpdb;

        $min_rating = (int) Settings::get('reviews_min_rating', 4);
        $table = $wpdb->prefix . 'ams_reviews_cache';
        $imported = 0;
        $offset = 0;

        while (true) {
            $comments = get_comments([
                'type'    => 'review',
                'status'  => 'approve',
                'number'  => $batch_size,
                'offset'  => $offset,
                'orderby' => 'comment_date_gmt',
                'order'   => 'DESC',
                'meta_query' => [
                    [
                        'key'     => 'rating',
                        'value'   => $min_rating,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]);

            if (empty($comments)) {
                break;
            }

            foreach ($comments as $comment) {
                $rating = (int) get_comment_meta($comment->comment_ID, 'rating', true);
                if ($rating < $min_rating) {
                    continue;
                }

                $product_id = (int) $comment->comment_post_ID;
                $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
                $verified = (int) get_comment_meta($comment->comment_ID, 'verified', true);

                $product_name = $product ? $product->get_name() : get_the_title($product_id);
                $product_url = $product ? $product->get_permalink() : get_permalink($product_id);
                $product_image = $product
                    ? (wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') ?: '')
                    : '';

                // Upsert: avoid duplicates by checking source + product_id + reviewer_email_hash + review_date.
                $email_hash = hash('sha256', strtolower(trim($comment->comment_author_email)));
                $review_date = $comment->comment_date_gmt;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE source = 'woocommerce' AND product_id = %d AND reviewer_email_hash = %s AND review_date = %s",
                    $product_id,
                    $email_hash,
                    $review_date
                ));

                if ($exists) {
                    continue;
                }

                $wpdb->insert($table, [
                    'source'              => 'woocommerce',
                    'product_id'          => $product_id,
                    'reviewer_name'       => sanitize_text_field($comment->comment_author),
                    'reviewer_email_hash' => $email_hash,
                    'rating'              => $rating,
                    'review_title'        => '',
                    'review_body'         => sanitize_textarea_field($comment->comment_content),
                    'review_date'         => $review_date,
                    'verified_purchase'   => $verified,
                    'product_name'        => $product_name,
                    'product_image_url'   => $product_image,
                    'product_url'         => $product_url,
                    'cached_at'           => current_time('mysql', true),
                ], [
                    '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
                ]);

                $imported++;
            }

            $offset += $batch_size;

            // Safety: if we fetched fewer than batch_size, we're done.
            if (count($comments) < $batch_size) {
                break;
            }
        }

        return $imported;
    }
}
