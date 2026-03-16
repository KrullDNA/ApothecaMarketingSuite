<?php
/**
 * SMS consent manager.
 *
 * Handles sms_opt_in flag management and TCPA compliance.
 *
 * @package Apotheca\Marketing\Sms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sms;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class SmsConsentManager
{
    private SubscriberRepository $subscribers;

    public function __construct()
    {
        $this->subscribers = new SubscriberRepository();
    }

    /**
     * Opt a subscriber in to SMS.
     */
    public function opt_in(int $subscriber_id): bool
    {
        return $this->subscribers->update($subscriber_id, ['sms_opt_in' => 1]);
    }

    /**
     * Opt a subscriber out of SMS.
     */
    public function opt_out(int $subscriber_id): bool
    {
        return $this->subscribers->update($subscriber_id, ['sms_opt_in' => 0]);
    }

    /**
     * Find a subscriber by phone number.
     */
    public function find_by_phone(string $phone): ?object
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';

        // Normalise phone: strip all non-digit/plus characters.
        $normalised = preg_replace('/[^0-9+]/', '', $phone);
        if (empty($normalised)) {
            return null;
        }

        // Try exact match first.
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE phone = %s LIMIT 1",
            $normalised
        ));

        if ($subscriber) {
            return $subscriber;
        }

        // Try with leading country code variants.
        $without_plus = ltrim($normalised, '+');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE REPLACE(REPLACE(phone, '+', ''), '-', '') = %s LIMIT 1",
            $without_plus
        ));
    }

    /**
     * Check if a subscriber is opted in to SMS.
     */
    public function is_opted_in(int $subscriber_id): bool
    {
        $subscriber = $this->subscribers->find($subscriber_id);
        return $subscriber && !empty($subscriber->sms_opt_in);
    }

    /**
     * Process an inbound STOP keyword.
     */
    public function handle_stop(string $from_phone): bool
    {
        $subscriber = $this->find_by_phone($from_phone);
        if (!$subscriber) {
            return false;
        }
        return $this->opt_out((int) $subscriber->id);
    }

    /**
     * Process an inbound UNSTOP / START keyword.
     */
    public function handle_unstop(string $from_phone): bool
    {
        $subscriber = $this->find_by_phone($from_phone);
        if (!$subscriber) {
            return false;
        }
        return $this->opt_in((int) $subscriber->id);
    }
}
