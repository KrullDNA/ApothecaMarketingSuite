<?php
/**
 * SMS webhook handler.
 *
 * Processes inbound Twilio webhooks for:
 * - STOP / UNSTOP / HELP keyword replies
 * - Delivery status callbacks
 *
 * All inbound webhooks are validated using Twilio signature verification.
 *
 * @package Apotheca\Marketing\Sms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sms;

defined('ABSPATH') || exit;

final class WebhookHandler
{
    /**
     * Handle an inbound SMS webhook (STOP/UNSTOP/HELP).
     *
     * @param array  $params    POST parameters from Twilio.
     * @param string $signature X-Twilio-Signature header.
     * @param string $url       Full webhook URL.
     * @return array{success: bool, message: string, reply?: string}
     */
    public function handle_inbound(array $params, string $signature, string $url): array
    {
        // Validate Twilio signature.
        $provider = new TwilioProvider();
        if (!$provider->validate_webhook($url, $params, $signature)) {
            return ['success' => false, 'message' => 'Invalid signature.'];
        }

        $body = strtoupper(trim($params['Body'] ?? ''));
        $from = $params['From'] ?? '';

        if (empty($from)) {
            return ['success' => false, 'message' => 'No sender number.'];
        }

        $consent = new SmsConsentManager();

        switch ($body) {
            case 'STOP':
            case 'STOPALL':
            case 'UNSUBSCRIBE':
            case 'CANCEL':
            case 'END':
            case 'QUIT':
                $consent->handle_stop($from);
                return [
                    'success' => true,
                    'message' => 'Subscriber opted out.',
                    'reply'   => 'You have been unsubscribed from SMS messages. Reply START to resubscribe.',
                ];

            case 'UNSTOP':
            case 'START':
            case 'YES':
                $consent->handle_unstop($from);
                return [
                    'success' => true,
                    'message' => 'Subscriber opted back in.',
                    'reply'   => 'You have been resubscribed to SMS messages. Reply STOP to opt out.',
                ];

            case 'HELP':
            case 'INFO':
                $creds = CredentialEncryptor::retrieve();
                $help_text = $creds['help_text'] ?: 'Reply STOP to opt out. Reply HELP for help. Msg&data rates may apply.';
                return [
                    'success' => true,
                    'message' => 'Help text sent.',
                    'reply'   => $help_text,
                ];

            default:
                return [
                    'success' => true,
                    'message' => 'Unrecognised keyword, no action taken.',
                ];
        }
    }

    /**
     * Handle a delivery status callback from Twilio.
     *
     * @param array  $params    POST parameters from Twilio.
     * @param string $signature X-Twilio-Signature header.
     * @param string $url       Full webhook URL.
     * @return array{success: bool, message: string}
     */
    public function handle_status(array $params, string $signature, string $url): array
    {
        // Validate signature.
        $provider = new TwilioProvider();
        if (!$provider->validate_webhook($url, $params, $signature)) {
            return ['success' => false, 'message' => 'Invalid signature.'];
        }

        $message_sid = $params['MessageSid'] ?? '';
        $status      = strtolower($params['MessageStatus'] ?? '');

        if (empty($message_sid) || empty($status)) {
            return ['success' => false, 'message' => 'Missing SID or status.'];
        }

        // Look up the send record by Twilio SID.
        $send_id = (int) get_transient('ams_sms_sid_' . $message_sid);

        if ($send_id <= 0) {
            return ['success' => false, 'message' => 'Send record not found.'];
        }

        // Map Twilio status to internal status.
        $status_map = [
            'queued'      => 'queued',
            'sent'        => 'sent',
            'delivered'   => 'delivered',
            'undelivered' => 'undelivered',
            'failed'      => 'failed',
            'accepted'    => 'sent',
            'sending'     => 'sent',
            'receiving'   => 'sent',
            'received'    => 'delivered',
        ];

        $internal_status = $status_map[$status] ?? $status;

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'ams_sends', [
            'status' => $internal_status,
        ], ['id' => $send_id]);

        // If the message bounced, mark as bounced_at.
        if (in_array($internal_status, ['undelivered', 'failed'], true)) {
            $wpdb->update($wpdb->prefix . 'ams_sends', [
                'bounced_at' => current_time('mysql', true),
            ], ['id' => $send_id]);
        }

        return ['success' => true, 'message' => 'Status updated to: ' . $internal_status];
    }
}
