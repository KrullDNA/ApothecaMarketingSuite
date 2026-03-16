<?php
/**
 * Send email flow step processor.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

defined('ABSPATH') || exit;

final class SendEmail implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        if (empty($subscriber->email) || $subscriber->status === 'unsubscribed') {
            return false;
        }

        $subject = $this->replace_tokens($step->subject, $subscriber);
        $body_html = $this->replace_tokens($step->body_html ?? '', $subscriber);
        $body_text = $this->replace_tokens($step->body_text ?? '', $subscriber);

        // Add unsubscribe link.
        $unsubscribe_url = add_query_arg(
            ['token' => $subscriber->unsubscribe_token],
            home_url('/ams-unsubscribe/')
        );

        $footer = sprintf(
            "\n\n---\n%s",
            sprintf(
                /* translators: %s: unsubscribe URL */
                __('To unsubscribe, visit: %s', 'apotheca-marketing-suite'),
                esc_url($unsubscribe_url)
            )
        );

        // Prepare headers.
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (!empty($step->preview_text)) {
            $preview = $this->replace_tokens($step->preview_text, $subscriber);
            $body_html = '<div style="display:none;max-height:0;overflow:hidden;">' . esc_html($preview) . '</div>' . $body_html;
        }

        $body_html .= wp_kses_post(nl2br($footer));

        $sent = wp_mail($subscriber->email, $subject, $body_html, $headers);

        // Record the send.
        $this->record_send($subscriber, $step, $sent);

        return $sent;
    }

    /**
     * Replace personalisation tokens in content.
     */
    private function replace_tokens(string $content, object $subscriber): string
    {
        $tokens = [
            '{{first_name}}'  => $subscriber->first_name ?: 'there',
            '{{last_name}}'   => $subscriber->last_name ?: '',
            '{{email}}'       => $subscriber->email,
            '{{phone}}'       => $subscriber->phone ?: '',
            '{{full_name}}'   => trim(($subscriber->first_name ?: '') . ' ' . ($subscriber->last_name ?: '')),
            '{{total_orders}}' => (string) ($subscriber->total_orders ?? 0),
            '{{total_spent}}'  => number_format((float) ($subscriber->total_spent ?? 0), 2),
            '{{rfm_segment}}' => $subscriber->rfm_segment ?: '',
            '{{site_name}}'   => get_bloginfo('name'),
            '{{site_url}}'    => home_url('/'),
            '{{unsubscribe_url}}' => add_query_arg(
                ['token' => $subscriber->unsubscribe_token],
                home_url('/ams-unsubscribe/')
            ),
        ];

        return str_replace(array_keys($tokens), array_values($tokens), $content);
    }

    /**
     * Record a send entry in ams_sends.
     */
    private function record_send(object $subscriber, object $step, bool $success): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'ams_sends', [
            'flow_step_id'  => (int) $step->id,
            'subscriber_id' => (int) $subscriber->id,
            'channel'       => 'email',
            'status'        => $success ? 'sent' : 'failed',
            'sent_at'       => $success ? current_time('mysql', true) : null,
            'created_at'    => current_time('mysql', true),
        ]);
    }
}
