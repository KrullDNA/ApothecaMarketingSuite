<?php
/**
 * Subscriber capture hooks for WooCommerce checkout and registration.
 *
 * @package Apotheca\Marketing\Subscriber
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Subscriber;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class CaptureHandler
{
    private Repository $repository;

    public function __construct()
    {
        $this->repository = new Repository();
        $this->register_hooks();
    }

    private function register_hooks(): void
    {
        // Checkout opt-in checkbox.
        add_action('woocommerce_review_order_before_submit', [$this, 'render_checkout_optin']);
        add_action('woocommerce_checkout_order_processed', [$this, 'capture_checkout_subscriber'], 10, 3);

        // Registration capture.
        add_action('user_register', [$this, 'capture_registration_subscriber'], 20, 1);
    }

    /**
     * Render the opt-in checkbox on the checkout page.
     */
    public function render_checkout_optin(): void
    {
        if (!Settings::get('checkout_optin_enabled', true)) {
            return;
        }

        $label = Settings::get('checkout_optin_label', 'Keep me updated with news and offers via email.');

        woocommerce_form_field('ams_marketing_consent', [
            'type'  => 'checkbox',
            'class' => ['ams-marketing-consent form-row-wide'],
            'label' => esc_html($label),
        ], 1);

        wp_nonce_field('ams_checkout_capture', 'ams_checkout_nonce');
    }

    /**
     * Capture subscriber data from checkout.
     *
     * @param int      $order_id
     * @param array    $posted_data
     * @param \WC_Order $order
     */
    public function capture_checkout_subscriber(int $order_id, array $posted_data, \WC_Order $order): void
    {
        // Verify nonce.
        if (!isset($_POST['ams_checkout_nonce']) || !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['ams_checkout_nonce'])),
            'ams_checkout_capture'
        )) {
            return;
        }

        $consent = !empty($_POST['ams_marketing_consent']);

        $email = $order->get_billing_email();
        if (empty($email)) {
            return;
        }

        $now = current_time('mysql', true);

        $subscriber_data = [
            'email'          => $email,
            'first_name'     => $order->get_billing_first_name(),
            'last_name'      => $order->get_billing_last_name(),
            'phone'          => $order->get_billing_phone(),
            'source'         => 'checkout',
            'gdpr_consent'   => $consent ? 1 : 0,
            'gdpr_timestamp' => $consent ? $now : null,
        ];

        if ($consent) {
            $subscriber_data['status'] = Settings::get('gdpr_double_optin') ? 'pending' : 'subscribed';
        } else {
            // Still track the subscriber but mark as not subscribed.
            $subscriber_data['status'] = 'never_subscribed';
        }

        $subscriber_id = $this->repository->upsert($subscriber_data);

        if ($subscriber_id && $consent && Settings::get('gdpr_double_optin')) {
            $subscriber = $this->repository->find($subscriber_id);
            if ($subscriber) {
                do_action('ams_send_double_optin_email', $subscriber);
            }
        }
    }

    /**
     * Capture subscriber on WooCommerce user registration.
     */
    public function capture_registration_subscriber(int $user_id): void
    {
        if (!Settings::get('registration_capture', true)) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return;
        }

        $subscriber_data = [
            'email'      => $user->user_email,
            'first_name' => $user->first_name ?: '',
            'last_name'  => $user->last_name ?: '',
            'source'     => 'registration',
            'status'     => Settings::get('gdpr_double_optin') ? 'pending' : 'subscribed',
        ];

        $subscriber_id = $this->repository->upsert($subscriber_data);

        if ($subscriber_id && Settings::get('gdpr_double_optin')) {
            $subscriber = $this->repository->find($subscriber_id);
            if ($subscriber) {
                do_action('ams_send_double_optin_email', $subscriber);
            }
        }
    }
}
