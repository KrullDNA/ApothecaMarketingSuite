<?php
/**
 * Send SMS flow step processor.
 *
 * Queues SMS via Twilio REST API (async via Action Scheduler).
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class SendSms implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        if (empty($subscriber->phone) || $subscriber->status === 'unsubscribed') {
            return false;
        }

        $body = $this->replace_tokens($step->sms_body ?? '', $subscriber);

        // Append STOP instructions for TCPA compliance.
        $body .= "\n\nReply STOP to unsubscribe.";

        // Schedule async send via Action Scheduler.
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time(),
                'ams_send_sms_async',
                [
                    'phone'         => $subscriber->phone,
                    'body'          => $body,
                    'subscriber_id' => (int) $subscriber->id,
                    'flow_step_id'  => (int) $step->id,
                ],
                'ams'
            );
        }

        // Record the send as queued.
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ams_sends', [
            'flow_step_id'  => (int) $step->id,
            'subscriber_id' => (int) $subscriber->id,
            'channel'       => 'sms',
            'status'        => 'queued',
            'created_at'    => current_time('mysql', true),
        ]);

        return true;
    }

    private function replace_tokens(string $content, object $subscriber): string
    {
        $tokens = [
            '{{first_name}}'  => $subscriber->first_name ?: 'there',
            '{{last_name}}'   => $subscriber->last_name ?: '',
            '{{email}}'       => $subscriber->email,
            '{{site_name}}'   => get_bloginfo('name'),
        ];

        return str_replace(array_keys($tokens), array_values($tokens), $content);
    }
}
