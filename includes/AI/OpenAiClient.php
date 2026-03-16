<?php
/**
 * OpenAI API client.
 *
 * Handles all communication with the OpenAI Chat Completions API.
 * API key stored encrypted in ams_ai_credentials option using AES-256-CBC.
 * All calls are async via Action Scheduler — never on page load.
 *
 * @package Apotheca\Marketing\AI
 */

declare(strict_types=1);

namespace Apotheca\Marketing\AI;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class OpenAiClient
{
    private const API_URL    = 'https://api.openai.com/v1/chat/completions';
    private const MODEL      = 'gpt-4o';
    private const OPTION_KEY = 'ams_ai_credentials';
    private const CIPHER     = 'aes-256-cbc';

    // Cost per 1K tokens (gpt-4o pricing).
    private const INPUT_COST_PER_1K  = 0.0025;
    private const OUTPUT_COST_PER_1K = 0.01;

    /**
     * Send a chat completion request to OpenAI.
     *
     * @param string $system_prompt  System message.
     * @param string $user_prompt    User message.
     * @param float  $temperature    0.0–2.0.
     * @param int    $max_tokens     Maximum response tokens.
     * @return array{success: bool, content: string, tokens_used: int, cost: float, error: string}
     */
    public static function chat(
        string $system_prompt,
        string $user_prompt,
        float $temperature = 0.7,
        int $max_tokens = 1000
    ): array {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return ['success' => false, 'content' => '', 'tokens_used' => 0, 'cost' => 0.0, 'error' => 'OpenAI API key not configured'];
        }

        // Check budget before calling.
        if (self::is_budget_exceeded()) {
            return ['success' => false, 'content' => '', 'tokens_used' => 0, 'cost' => 0.0, 'error' => 'Monthly AI token budget exceeded'];
        }

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 60,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model'       => self::MODEL,
                'messages'    => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt],
                ],
                'temperature' => $temperature,
                'max_tokens'  => $max_tokens,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'content' => '', 'tokens_used' => 0, 'cost' => 0.0, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['choices'][0]['message']['content'])) {
            $error = $body['error']['message'] ?? 'Unknown OpenAI error (HTTP ' . $code . ')';
            return ['success' => false, 'content' => '', 'tokens_used' => 0, 'cost' => 0.0, 'error' => $error];
        }

        $content     = $body['choices'][0]['message']['content'];
        $usage       = $body['usage'] ?? [];
        $input_tok   = (int) ($usage['prompt_tokens'] ?? 0);
        $output_tok  = (int) ($usage['completion_tokens'] ?? 0);
        $total_tok   = $input_tok + $output_tok;
        $cost        = ($input_tok / 1000) * self::INPUT_COST_PER_1K + ($output_tok / 1000) * self::OUTPUT_COST_PER_1K;

        return [
            'success'     => true,
            'content'     => $content,
            'tokens_used' => $total_tok,
            'cost'        => round($cost, 4),
            'error'       => '',
        ];
    }

    /**
     * Log an AI call to ams_ai_log.
     */
    public static function log(string $feature, string $input_summary, string $output_summary, int $tokens_used, float $cost, ?int $subscriber_id = null): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ams_ai_log', [
            'feature'        => sanitize_text_field($feature),
            'input_summary'  => wp_kses_post(mb_substr($input_summary, 0, 2000)),
            'output_summary' => wp_kses_post(mb_substr($output_summary, 0, 2000)),
            'tokens_used'    => $tokens_used,
            'cost_usd'       => $cost,
            'subscriber_id'  => $subscriber_id,
            'created_at'     => current_time('mysql', true),
        ]);
    }

    /**
     * Get tokens used this month.
     */
    public static function get_monthly_usage(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_ai_log';
        $month_start = gmdate('Y-m-01 00:00:00');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(tokens_used) as tokens, SUM(cost_usd) as cost, COUNT(*) as calls
             FROM {$table} WHERE created_at >= %s",
            $month_start
        ));

        return [
            'tokens' => (int) ($row->tokens ?? 0),
            'cost'   => round((float) ($row->cost ?? 0), 4),
            'calls'  => (int) ($row->calls ?? 0),
        ];
    }

    /**
     * Check if monthly budget is exceeded.
     */
    public static function is_budget_exceeded(): bool
    {
        $budget = (int) Settings::get('ai_monthly_token_budget', 500000);
        if ($budget <= 0) {
            return false;
        }
        $usage = self::get_monthly_usage();
        return $usage['tokens'] >= $budget;
    }

    /**
     * Check if budget is at 80% warning level.
     */
    public static function is_budget_warning(): bool
    {
        $budget = (int) Settings::get('ai_monthly_token_budget', 500000);
        if ($budget <= 0) {
            return false;
        }
        $usage = self::get_monthly_usage();
        return $usage['tokens'] >= ($budget * 0.8);
    }

    // ── Encrypted API Key Storage ───────────────────────────────────────

    public static function store_api_key(string $api_key): void
    {
        update_option(self::OPTION_KEY, self::encrypt($api_key));
    }

    public static function get_api_key(): string
    {
        $stored = get_option(self::OPTION_KEY, '');
        return is_string($stored) ? self::decrypt($stored) : '';
    }

    public static function has_api_key(): bool
    {
        return !empty(self::get_api_key());
    }

    private static function get_key(): string
    {
        $source = defined('AUTH_KEY') ? AUTH_KEY : 'ams-default-key-change-me';
        return hash('sha256', $source, true);
    }

    private static function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }
        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $encrypted !== false ? base64_encode($iv . $encrypted) : '';
    }

    private static function decrypt(string $ciphertext): string
    {
        if (empty($ciphertext)) {
            return '';
        }
        $data = base64_decode($ciphertext, true);
        if ($data === false) {
            return '';
        }
        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($data) < $iv_length) {
            return '';
        }
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : '';
    }
}
