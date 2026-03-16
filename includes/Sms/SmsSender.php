<?php
/**
 * SMS send queue and delivery manager.
 *
 * Handles async SMS sending via Action Scheduler, token replacement,
 * delivery status updates, and retry logic.
 *
 * @package Apotheca\Marketing\Sms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sms;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class SmsSender
{
    private const SEND_HOOK  = 'ams_send_sms_async';
    private const RETRY_HOOK = 'ams_send_sms_retry';

    public function __construct()
    {
        add_action(self::SEND_HOOK, [$this, 'process_send']);
        add_action(self::RETRY_HOOK, [$this, 'process_retry']);
    }

    /**
     * Process an async SMS send from Action Scheduler.
     *
     * @param array $args {phone, body, subscriber_id, flow_step_id, campaign_id, media_url, send_id}
     */
    public function process_send(array $args): void
    {
        $phone         = $args['phone'] ?? '';
        $body          = $args['body'] ?? '';
        $subscriber_id = (int) ($args['subscriber_id'] ?? 0);
        $flow_step_id  = (int) ($args['flow_step_id'] ?? 0);
        $campaign_id   = (int) ($args['campaign_id'] ?? 0);
        $media_url     = $args['media_url'] ?? null;
        $send_id       = (int) ($args['send_id'] ?? 0);

        if (empty($phone) || empty($body)) {
            return;
        }

        // Check sms_opt_in before sending.
        if ($subscriber_id > 0) {
            $repo = new SubscriberRepository();
            $subscriber = $repo->find($subscriber_id);
            if ($subscriber && empty($subscriber->sms_opt_in)) {
                $this->update_send_status($send_id, 'skipped');
                return;
            }
        }

        $provider = new TwilioProvider();
        $result = $provider->send($phone, $body, $media_url);

        global $wpdb;
        $sends_table = $wpdb->prefix . 'ams_sends';

        if ($result['success']) {
            // Update send record with Twilio SID and sent status.
            if ($send_id > 0) {
                $wpdb->update($sends_table, [
                    'status'   => 'sent',
                    'sent_at'  => current_time('mysql', true),
                ], ['id' => $send_id]);
            }

            // Store the Twilio message SID for status callback correlation.
            if (!empty($result['sid']) && $send_id > 0) {
                update_post_meta($send_id, '_ams_twilio_sid', $result['sid']);
                // Use a transient for fast SID->send_id lookup.
                set_transient('ams_sms_sid_' . $result['sid'], $send_id, DAY_IN_SECONDS);
            }
        } else {
            // Schedule retry after 30 minutes (once only).
            if (empty($args['is_retry'])) {
                if (function_exists('as_schedule_single_action')) {
                    as_schedule_single_action(
                        time() + 1800,
                        self::RETRY_HOOK,
                        [array_merge($args, ['is_retry' => true, 'send_id' => $send_id])],
                        'ams'
                    );
                }
                $this->update_send_status($send_id, 'retry_queued');
            } else {
                $this->update_send_status($send_id, 'permanently_failed');
            }
        }
    }

    /**
     * Process a retry attempt.
     */
    public function process_retry(array $args): void
    {
        $this->process_send($args);
    }

    /**
     * Queue an SMS for sending (creates send record and schedules action).
     *
     * @return int The send record ID.
     */
    public function queue(
        string $phone,
        string $body,
        int $subscriber_id = 0,
        int $flow_step_id = 0,
        int $campaign_id = 0,
        ?string $media_url = null
    ): int {
        global $wpdb;

        // Create the send record.
        $wpdb->insert($wpdb->prefix . 'ams_sends', [
            'campaign_id'   => $campaign_id ?: null,
            'flow_step_id'  => $flow_step_id ?: null,
            'subscriber_id' => $subscriber_id,
            'channel'       => 'sms',
            'status'        => 'queued',
            'created_at'    => current_time('mysql', true),
        ]);

        $send_id = (int) $wpdb->insert_id;

        // Schedule async send.
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time(),
                self::SEND_HOOK,
                [[
                    'phone'         => $phone,
                    'body'          => $body,
                    'subscriber_id' => $subscriber_id,
                    'flow_step_id'  => $flow_step_id,
                    'campaign_id'   => $campaign_id,
                    'media_url'     => $media_url,
                    'send_id'       => $send_id,
                ]],
                'ams'
            );
        }

        return $send_id;
    }

    /**
     * Replace SMS personalisation tokens.
     */
    public static function replace_tokens(string $content, object $subscriber, array $extra = []): string
    {
        $unsubscribe_url = '';
        if (!empty($subscriber->unsubscribe_token)) {
            $unsubscribe_url = add_query_arg(
                ['token' => $subscriber->unsubscribe_token],
                home_url('/ams-unsubscribe/')
            );
        }

        $tokens = [
            '{{first_name}}'      => $subscriber->first_name ?: 'there',
            '{{last_name}}'       => $subscriber->last_name ?: '',
            '{{email}}'           => $subscriber->email ?? '',
            '{{phone}}'           => $subscriber->phone ?? '',
            '{{shop_name}}'       => get_bloginfo('name'),
            '{{shop_url}}'        => home_url(),
            '{{unsubscribe_url}}' => $unsubscribe_url,
        ];

        // WooCommerce tokens from extra context.
        $woo_tokens = [
            '{{order_number}}' => $extra['order_number'] ?? '',
            '{{order_total}}'  => $extra['order_total'] ?? '',
            '{{product_name}}' => $extra['product_name'] ?? '',
            '{{cart_url}}'     => $extra['cart_url'] ?? wc_get_cart_url(),
            '{{coupon_code}}'  => $extra['coupon_code'] ?? '',
        ];

        $all_tokens = array_merge($tokens, $woo_tokens);

        return str_replace(array_keys($all_tokens), array_values($all_tokens), $content);
    }

    /**
     * Update a send record's status.
     */
    private function update_send_status(int $send_id, string $status): void
    {
        if ($send_id <= 0) {
            return;
        }

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'ams_sends', [
            'status' => $status,
        ], ['id' => $send_id]);
    }
}
