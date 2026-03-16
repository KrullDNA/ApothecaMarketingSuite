<?php
/**
 * Judge.me review importer.
 *
 * Pulls reviews from Judge.me public API when the Judge.me plugin is
 * active and an API key is configured. Stores into ams_reviews_cache.
 *
 * @package Apotheca\Marketing\Reviews
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Reviews;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class JudgeMeImporter
{
    /**
     * Check if Judge.me integration is available.
     */
    public static function is_available(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Check common Judge.me plugin slugs.
        $judgeme_active = is_plugin_active('developer-flavor/developer-flavor.php')
            || is_plugin_active('developer-flavor-starter/developer-flavor-starter.php')
            || is_plugin_active('developer-flavor-for-woocommerce/developer-flavor-for-woocommerce.php')
            || is_plugin_active('developer-flavor-product-reviews-for-woocommerce/developer-flavor-product-reviews-for-woocommerce.php');

        // Also check by class existence as fallback.
        if (!$judgeme_active) {
            $judgeme_active = class_exists('JudgeMe') || class_exists('Developer_Flavor');
        }

        return $judgeme_active;
    }

    /**
     * Get the encrypted API key.
     */
    private function get_api_key(): string
    {
        $settings = Settings::all();
        $encrypted = $settings['judgeme_api_key'] ?? '';

        if (!$encrypted) {
            return '';
        }

        // Decrypt using the same AES-256-CBC pattern as other credentials.
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'ams-fallback-key';
        $data = base64_decode($encrypted);
        if (false === $data || strlen($data) < 17) {
            return '';
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted ?: '';
    }

    /**
     * Encrypt and store the API key.
     */
    public static function store_api_key(string $api_key): void
    {
        if (empty($api_key)) {
            Settings::set('judgeme_api_key', '');
            return;
        }

        $key = defined('AUTH_KEY') ? AUTH_KEY : 'ams-fallback-key';
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($api_key, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $encrypted = base64_encode($iv . $ciphertext);

        Settings::set('judgeme_api_key', $encrypted);
    }

    /**
     * Import Judge.me reviews.
     *
     * @return int Number of reviews imported, or -1 on error.
     */
    public function import(): int
    {
        if (!self::is_available()) {
            return 0;
        }

        $api_key = $this->get_api_key();
        if (!$api_key) {
            return 0;
        }

        $shop_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!$shop_domain) {
            return -1;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ams_reviews_cache';
        $min_rating = (int) Settings::get('reviews_min_rating', 4);
        $imported = 0;
        $page = 1;
        $per_page = 100;

        // Build rating filter string.
        $rating_values = [];
        for ($r = $min_rating; $r <= 5; $r++) {
            $rating_values[] = $r;
        }
        $rating_filter = implode(',', $rating_values);

        while (true) {
            $url = add_query_arg([
                'api_token'   => $api_key,
                'shop_domain' => $shop_domain,
                'per_page'    => $per_page,
                'page'        => $page,
                'rating'      => $rating_filter,
            ], 'https://judge.me/api/v1/reviews');

            $response = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => ['Accept' => 'application/json'],
            ]);

            if (is_wp_error($response)) {
                return -1;
            }

            $status = wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                return -1;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $reviews = $body['reviews'] ?? [];

            if (empty($reviews)) {
                break;
            }

            foreach ($reviews as $review) {
                $rating = (int) ($review['rating'] ?? 0);
                if ($rating < $min_rating) {
                    continue;
                }

                $product_id = (int) ($review['product_external_id'] ?? 0);
                $email_hash = hash('sha256', strtolower(trim($review['reviewer']['email'] ?? '')));
                $review_date = $review['created_at'] ?? '';

                if ($review_date) {
                    $review_date = gmdate('Y-m-d H:i:s', strtotime($review_date));
                }

                // Check for existing.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE source = 'judgeme' AND product_id = %d AND reviewer_email_hash = %s AND review_date = %s",
                    $product_id,
                    $email_hash,
                    $review_date
                ));

                if ($exists) {
                    continue;
                }

                // Resolve product details from WooCommerce.
                $product = $product_id && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
                $product_name = $product ? $product->get_name() : ($review['product_title'] ?? '');
                $product_url = $product ? $product->get_permalink() : '';
                $product_image = $product
                    ? (wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') ?: '')
                    : ($review['product_image_url'] ?? '');

                $wpdb->insert($table, [
                    'source'              => 'judgeme',
                    'product_id'          => $product_id,
                    'reviewer_name'       => sanitize_text_field($review['reviewer']['name'] ?? ''),
                    'reviewer_email_hash' => $email_hash,
                    'rating'              => $rating,
                    'review_title'        => sanitize_text_field($review['title'] ?? ''),
                    'review_body'         => sanitize_textarea_field($review['body'] ?? ''),
                    'review_date'         => $review_date,
                    'verified_purchase'   => (int) ($review['verified'] ?? 0),
                    'product_name'        => $product_name,
                    'product_image_url'   => $product_image,
                    'product_url'         => $product_url,
                    'cached_at'           => current_time('mysql', true),
                ], [
                    '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
                ]);

                $imported++;
            }

            // If fewer than per_page, we're done.
            if (count($reviews) < $per_page) {
                break;
            }

            $page++;
        }

        return $imported;
    }

    /**
     * Test the Judge.me API connection.
     *
     * @return array{success: bool, message: string}
     */
    public function test_connection(): array
    {
        if (!self::is_available()) {
            return ['success' => false, 'message' => 'Judge.me plugin is not detected.'];
        }

        $api_key = $this->get_api_key();
        if (!$api_key) {
            return ['success' => false, 'message' => 'No API key configured.'];
        }

        $shop_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $url = add_query_arg([
            'api_token'   => $api_key,
            'shop_domain' => $shop_domain,
            'per_page'    => 1,
            'page'        => 1,
        ], 'https://judge.me/api/v1/reviews');

        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Connection failed: ' . $response->get_error_message()];
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return ['success' => false, 'message' => 'API returned status ' . $status . '.'];
        }

        return ['success' => true, 'message' => 'Connection successful.'];
    }
}
