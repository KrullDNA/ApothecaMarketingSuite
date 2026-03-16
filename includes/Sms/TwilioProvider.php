<?php
/**
 * Twilio SMS provider.
 *
 * Sends SMS/MMS via Twilio REST API using wp_remote_post (no SDK).
 * Validates inbound webhooks using Twilio signature verification.
 *
 * @package Apotheca\Marketing\Sms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sms;

defined('ABSPATH') || exit;

final class TwilioProvider implements SmsProviderInterface
{
    private string $account_sid;
    private string $auth_token;
    private string $from_number;

    public function __construct()
    {
        $creds = CredentialEncryptor::retrieve();
        $this->account_sid = $creds['account_sid'];
        $this->auth_token  = $creds['auth_token'];
        $this->from_number = $creds['from_number'];
    }

    public function is_configured(): bool
    {
        return !empty($this->account_sid) && !empty($this->auth_token) && !empty($this->from_number);
    }

    public function send(string $to, string $body, ?string $media_url = null): array
    {
        if (!$this->is_configured()) {
            return ['success' => false, 'sid' => '', 'error' => 'SMS provider not configured.'];
        }

        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            $this->account_sid
        );

        $params = [
            'To'   => $to,
            'Body' => $body,
        ];

        // Support Messaging Service SID or From number.
        if (str_starts_with($this->from_number, 'MG')) {
            $params['MessagingServiceSid'] = $this->from_number;
        } else {
            $params['From'] = $this->from_number;
        }

        // MMS: attach media URL.
        if (!empty($media_url)) {
            $params['MediaUrl'] = esc_url_raw($media_url);
        }

        // Status callback for delivery tracking.
        $params['StatusCallback'] = rest_url('ams/v1/sms/status');

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->account_sid . ':' . $this->auth_token),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => $params,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'sid'     => '',
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300 && !empty($data['sid'])) {
            return [
                'success' => true,
                'sid'     => $data['sid'],
                'error'   => '',
            ];
        }

        return [
            'success' => false,
            'sid'     => $data['sid'] ?? '',
            'error'   => $data['message'] ?? ('HTTP ' . $code),
        ];
    }

    public function reply(string $to, string $body): array
    {
        return $this->send($to, $body);
    }

    /**
     * Validate Twilio webhook signature (X-Twilio-Signature).
     *
     * @see https://www.twilio.com/docs/usage/security#validating-requests
     */
    public function validate_webhook(string $url, array $params, string $signature): bool
    {
        if (empty($this->auth_token) || empty($signature)) {
            return false;
        }

        // Sort params by key and append key=value to URL.
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $this->auth_token, true));

        return hash_equals($expected, $signature);
    }
}
