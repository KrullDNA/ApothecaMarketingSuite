<?php
/**
 * Universal token replacer for email and SMS content.
 *
 * Handles {{ }} tokens and conditional {{if}}...{{else}}...{{/if}} syntax.
 *
 * @package Apotheca\Marketing\Analytics
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Analytics;

defined('ABSPATH') || exit;

final class TokenReplacer
{
    /**
     * Replace all tokens in content.
     *
     * @param string              $content    The content with tokens.
     * @param object|null         $subscriber Subscriber row from ams_subscribers.
     * @param array<string,mixed> $context    Extra context: order, cart, product, coupon data.
     */
    public static function replace(string $content, ?object $subscriber = null, array $context = []): string
    {
        // Process conditionals first.
        $content = self::process_conditionals($content, $subscriber, $context);

        // Build token map.
        $tokens = self::build_token_map($subscriber, $context);

        return str_replace(array_keys($tokens), array_values($tokens), $content);
    }

    /**
     * Build the full token replacement map.
     *
     * @return array<string,string>
     */
    private static function build_token_map(?object $subscriber, array $context): array
    {
        $tokens = [];

        // Subscriber tokens.
        $tokens['{{first_name}}']  = $subscriber->first_name ?? '';
        $tokens['{{last_name}}']   = $subscriber->last_name ?? '';
        $tokens['{{email}}']       = $subscriber->email ?? '';
        $tokens['{{phone}}']       = $subscriber->phone ?? '';
        $tokens['{{city}}']        = $context['city'] ?? '';
        $tokens['{{country}}']     = $context['country'] ?? '';

        // Shop tokens.
        $tokens['{{shop_name}}']     = get_bloginfo('name');
        $tokens['{{shop_url}}']      = home_url();
        $tokens['{{shop_logo_url}}'] = $context['shop_logo_url'] ?? '';

        // Order tokens.
        $order = $context['order'] ?? null;
        if ($order instanceof \WC_Order) {
            $tokens['{{order_number}}']      = (string) $order->get_order_number();
            $tokens['{{order_date}}']        = $order->get_date_created() ? $order->get_date_created()->date('F j, Y') : '';
            $tokens['{{order_total}}']       = $order->get_formatted_order_total();
            $tokens['{{order_status}}']      = wc_get_order_status_name($order->get_status());
            $tokens['{{order_items_table}}'] = self::build_order_items_table($order);
            $tokens['{{shipping_address}}']  = $order->get_formatted_shipping_address() ?: '';
            $tokens['{{billing_address}}']   = $order->get_formatted_billing_address() ?: '';
            // Populate city/country from order if not already set.
            if (empty($tokens['{{city}}'])) {
                $tokens['{{city}}'] = $order->get_billing_city();
            }
            if (empty($tokens['{{country}}'])) {
                $tokens['{{country}}'] = $order->get_billing_country();
            }
        } else {
            $tokens['{{order_number}}']      = $context['order_number'] ?? '';
            $tokens['{{order_date}}']        = $context['order_date'] ?? '';
            $tokens['{{order_total}}']       = $context['order_total'] ?? '';
            $tokens['{{order_status}}']      = $context['order_status'] ?? '';
            $tokens['{{order_items_table}}'] = $context['order_items_table'] ?? '';
            $tokens['{{shipping_address}}']  = $context['shipping_address'] ?? '';
            $tokens['{{billing_address}}']   = $context['billing_address'] ?? '';
        }

        // Cart tokens.
        $tokens['{{cart_url}}']        = $context['cart_url'] ?? (function_exists('wc_get_cart_url') ? wc_get_cart_url() : '');
        $tokens['{{cart_items_table}}'] = $context['cart_items_table'] ?? '';
        $tokens['{{cart_total}}']      = $context['cart_total'] ?? '';
        $tokens['{{cart_item_count}}'] = (string) ($context['cart_item_count'] ?? '');

        // Product tokens.
        $tokens['{{product_name}}']      = $context['product_name'] ?? '';
        $tokens['{{product_url}}']       = $context['product_url'] ?? '';
        $tokens['{{product_image_url}}'] = $context['product_image_url'] ?? '';
        $tokens['{{product_price}}']     = $context['product_price'] ?? '';

        // Coupon tokens.
        $tokens['{{coupon_code}}']     = $context['coupon_code'] ?? '';
        $tokens['{{coupon_discount}}'] = $context['coupon_discount'] ?? '';
        $tokens['{{coupon_expiry}}']   = $context['coupon_expiry'] ?? '';

        // Unsubscribe / preferences tokens.
        $unsub_url = '';
        if (!empty($subscriber->unsubscribe_token)) {
            $unsub_url = add_query_arg(
                ['token' => $subscriber->unsubscribe_token],
                home_url('/ams-unsubscribe/')
            );
        }
        $tokens['{{unsubscribe_url}}']        = $unsub_url;
        $tokens['{{manage_preferences_url}}'] = $context['manage_preferences_url'] ?? $unsub_url;

        return $tokens;
    }

    /**
     * Process {{if field}}...{{else}}...{{/if}} conditionals.
     */
    private static function process_conditionals(string $content, ?object $subscriber, array $context): string
    {
        // Pattern: {{if field_name}}content{{else}}alt_content{{/if}}
        // Or:      {{if field_name}}content{{/if}}
        $pattern = '/\{\{if\s+(\w+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s';

        return preg_replace_callback($pattern, function (array $matches) use ($subscriber, $context) {
            $field = $matches[1];
            $if_content = $matches[2];
            $else_content = $matches[3] ?? '';

            $value = self::resolve_field_value($field, $subscriber, $context);

            return !empty($value) ? $if_content : $else_content;
        }, $content) ?? $content;
    }

    /**
     * Resolve a field name to its value for conditional evaluation.
     */
    private static function resolve_field_value(string $field, ?object $subscriber, array $context): string
    {
        // Check subscriber fields first.
        if ($subscriber && isset($subscriber->$field)) {
            return (string) $subscriber->$field;
        }

        // Check context.
        if (isset($context[$field])) {
            return (string) $context[$field];
        }

        return '';
    }

    /**
     * Build an HTML table of order items for email.
     */
    private static function build_order_items_table(\WC_Order $order): string
    {
        $rows = '';
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $image_url = $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '';
            $rows .= '<tr>';
            if ($image_url) {
                $rows .= '<td style="padding:8px;"><img src="' . esc_url($image_url) . '" width="60" height="60" alt="" style="display:block;" /></td>';
            }
            $rows .= '<td style="padding:8px;">' . esc_html($item->get_name()) . ' &times; ' . esc_html((string) $item->get_quantity()) . '</td>';
            $rows .= '<td style="padding:8px;text-align:right;">' . wc_price((float) $item->get_total()) . '</td>';
            $rows .= '</tr>';
        }

        return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $rows . '</table>';
    }
}
