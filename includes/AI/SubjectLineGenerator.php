<?php
/**
 * AI subject line generator.
 *
 * Generates 5 subject line options with emoji variants via OpenAI.
 * Runs async via Action Scheduler — results stored in transient for pickup.
 *
 * @package Apotheca\Marketing\AI
 */

declare(strict_types=1);

namespace Apotheca\Marketing\AI;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class SubjectLineGenerator
{
    private const HOOK = 'ams_ai_generate_subjects';

    public function __construct()
    {
        add_action(self::HOOK, [$this, 'process']);
    }

    /**
     * Queue a subject line generation request.
     *
     * @return string Request ID for polling the result.
     */
    public static function queue(array $context): string
    {
        if (!Settings::get('ai_subject_lines_enabled', true)) {
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

        // Set a pending transient so the UI knows it's in progress.
        set_transient('ams_ai_subjects_' . $request_id, ['status' => 'pending'], HOUR_IN_SECONDS);

        return $request_id;
    }

    /**
     * Process the generation request (called by Action Scheduler).
     */
    public function process(array $args): void
    {
        $request_id   = $args['request_id'] ?? '';
        $brand_name   = sanitize_text_field($args['brand_name'] ?? get_bloginfo('name'));
        $product_ctx  = sanitize_text_field($args['product_context'] ?? '');
        $body_summary = sanitize_text_field($args['body_summary'] ?? '');
        $segment_name = sanitize_text_field($args['segment_name'] ?? '');

        $system = 'You are an expert email marketing copywriter. Generate compelling email subject lines that drive high open rates. Return ONLY a valid JSON array of exactly 5 objects, each with "subject" (plain text) and "emoji_variant" (same subject with one relevant emoji). No markdown, no explanation.';

        $user = "Brand: {$brand_name}\n";
        if ($product_ctx) {
            $user .= "Product context: {$product_ctx}\n";
        }
        if ($body_summary) {
            $user .= "Email body summary: {$body_summary}\n";
        }
        if ($segment_name) {
            $user .= "Target segment: {$segment_name}\n";
        }
        $user .= "\nGenerate 5 email subject line options.";

        $result = OpenAiClient::chat($system, $user, 0.8, 500);

        if ($result['success']) {
            $options = json_decode($result['content'], true);
            if (!is_array($options)) {
                // Try to extract JSON from markdown code block.
                preg_match('/\[.*\]/s', $result['content'], $matches);
                $options = json_decode($matches[0] ?? '[]', true) ?: [];
            }

            set_transient('ams_ai_subjects_' . $request_id, [
                'status'  => 'complete',
                'options' => $options,
            ], HOUR_IN_SECONDS);

            OpenAiClient::log(
                'subject_lines',
                mb_substr($user, 0, 500),
                mb_substr($result['content'], 0, 1000),
                $result['tokens_used'],
                $result['cost']
            );
        } else {
            set_transient('ams_ai_subjects_' . $request_id, [
                'status' => 'error',
                'error'  => $result['error'],
            ], HOUR_IN_SECONDS);
        }
    }
}
