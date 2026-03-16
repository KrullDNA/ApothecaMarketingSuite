<?php
/**
 * AI email body generator.
 *
 * Generates structured email content (preheader, headline, body, CTA)
 * via OpenAI. Runs async via Action Scheduler.
 *
 * @package Apotheca\Marketing\AI
 */

declare(strict_types=1);

namespace Apotheca\Marketing\AI;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class EmailBodyGenerator
{
    private const HOOK = 'ams_ai_generate_email_body';

    public function __construct()
    {
        add_action(self::HOOK, [$this, 'process']);
    }

    /**
     * Queue an email body generation request.
     *
     * @return string Request ID for polling.
     */
    public static function queue(array $context): string
    {
        if (!Settings::get('ai_email_body_enabled', true)) {
            return '';
        }

        $request_id = wp_generate_password(16, false);

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time(),
                self::HOOK,
                [array_merge($context, ['request_id' => $request_id])],
                'ams'
            );
        }

        set_transient('ams_ai_email_body_' . $request_id, ['status' => 'pending'], HOUR_IN_SECONDS);

        return $request_id;
    }

    /**
     * Process the generation request (called by Action Scheduler).
     */
    public function process(array $args): void
    {
        $request_id  = $args['request_id'] ?? '';
        $goal        = sanitize_text_field($args['goal'] ?? 'promotion');
        $tone        = sanitize_text_field($args['tone'] ?? 'friendly');
        $key_message = sanitize_text_field($args['key_message'] ?? '');
        $product_ids = array_map('intval', (array) ($args['product_ids'] ?? []));
        $brand_name  = sanitize_text_field($args['brand_name'] ?? get_bloginfo('name'));

        // Build product context from WooCommerce.
        $product_context = '';
        if (!empty($product_ids) && function_exists('wc_get_product')) {
            $products = [];
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if ($product) {
                    $products[] = $product->get_name() . ' (' . wc_price($product->get_price()) . ')';
                }
            }
            if ($products) {
                $product_context = 'Featured products: ' . implode(', ', $products);
            }
        }

        $system = 'You are an expert email marketing copywriter. Generate a structured email in JSON format with these exact keys: "preheader" (max 100 chars), "headline" (compelling, max 60 chars), "body_paragraphs" (array of 2-3 HTML paragraphs), "cta_text" (button text, max 25 chars). Return ONLY valid JSON, no markdown.';

        $user = "Brand: {$brand_name}\n";
        $user .= "Campaign goal: {$goal}\n";
        $user .= "Tone: {$tone}\n";
        if ($key_message) {
            $user .= "Key message: {$key_message}\n";
        }
        if ($product_context) {
            $user .= "{$product_context}\n";
        }
        $user .= "\nGenerate the email content.";

        $result = OpenAiClient::chat($system, $user, 0.7, 1000);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            if (!is_array($parsed) || empty($parsed['headline'])) {
                // Try to extract JSON from content.
                preg_match('/\{.*\}/s', $result['content'], $matches);
                $parsed = json_decode($matches[0] ?? '{}', true) ?: [];
            }

            set_transient('ams_ai_email_body_' . $request_id, [
                'status'  => 'complete',
                'content' => $parsed,
            ], HOUR_IN_SECONDS);

            OpenAiClient::log(
                'email_body',
                mb_substr($user, 0, 500),
                mb_substr($result['content'], 0, 1000),
                $result['tokens_used'],
                $result['cost']
            );
        } else {
            set_transient('ams_ai_email_body_' . $request_id, [
                'status' => 'error',
                'error'  => $result['error'],
            ], HOUR_IN_SECONDS);
        }
    }
}
