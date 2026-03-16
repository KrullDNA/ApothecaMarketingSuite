<?php
/**
 * Welcome series trigger — fires on subscriber opt-in.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

final class WelcomeSeries extends AbstractTrigger
{
    protected function get_trigger_type(): string
    {
        return 'welcome_series';
    }

    public function register(): void
    {
        // Fires when double opt-in is confirmed.
        add_action('ams_subscriber_confirmed', [$this, 'on_subscriber_confirmed']);

        if (function_exists('WC')) {
            // Fires on direct subscription (no double opt-in).
            add_action('woocommerce_checkout_order_processed', [$this, 'on_checkout_subscription'], 30, 3);
        }
        add_action('user_register', [$this, 'on_registration'], 30, 1);

        // Also listen for the sync trigger from the ingest endpoint.
        add_action('ams_trigger_welcome_series', [$this, 'on_subscriber_confirmed']);
    }

    public function on_subscriber_confirmed(int $subscriber_id): void
    {
        $this->enrol_subscriber($subscriber_id);
    }

    public function on_checkout_subscription(int $order_id, array $posted_data, object $order): void
    {
        if (!class_exists('WC_Order') || !($order instanceof \WC_Order)) {
            return;
        }
        if (\Apotheca\Marketing\Settings::get('gdpr_double_optin')) {
            return; // Will be handled by on_subscriber_confirmed.
        }

        $email = $order->get_billing_email();
        if (empty($email)) {
            return;
        }

        $repo = new \Apotheca\Marketing\Subscriber\Repository();
        $subscriber = $repo->find_by_email($email);
        if ($subscriber && $subscriber->status === 'subscribed') {
            $this->enrol_subscriber((int) $subscriber->id);
        }
    }

    public function on_registration(int $user_id): void
    {
        if (\Apotheca\Marketing\Settings::get('gdpr_double_optin')) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $repo = new \Apotheca\Marketing\Subscriber\Repository();
        $subscriber = $repo->find_by_email($user->user_email);
        if ($subscriber && $subscriber->status === 'subscribed') {
            $this->enrol_subscriber((int) $subscriber->id);
        }
    }
}
