<?php
/**
 * SMS provider interface.
 *
 * Allows swapping Twilio for another provider in the future.
 *
 * @package Apotheca\Marketing\Sms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sms;

defined('ABSPATH') || exit;

interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     *
     * @param string      $to   Recipient phone number (E.164 format).
     * @param string      $body Message body.
     * @param string|null $media_url Optional MMS image URL.
     * @return array{success: bool, sid: string, error: string}
     */
    public function send(string $to, string $body, ?string $media_url = null): array;

    /**
     * Send an SMS reply (for webhook responses).
     *
     * @param string $to   Recipient phone number.
     * @param string $body Reply message body.
     * @return array{success: bool, sid: string, error: string}
     */
    public function reply(string $to, string $body): array;

    /**
     * Validate an inbound webhook signature.
     *
     * @param string $url       The full webhook URL.
     * @param array  $params    The POST parameters.
     * @param string $signature The signature header value.
     * @return bool
     */
    public function validate_webhook(string $url, array $params, string $signature): bool;

    /**
     * Check if the provider is configured and ready to send.
     */
    public function is_configured(): bool;
}
