<?php
/**
 * GDPR compliance handler.
 *
 * Manages double opt-in emails, unsubscribe endpoint, and consent tracking.
 *
 * @package Apotheca\Marketing\GDPR
 */

declare(strict_types=1);

namespace Apotheca\Marketing\GDPR;

use Apotheca\Marketing\Settings;
use Apotheca\Marketing\Subscriber\Repository;

defined('ABSPATH') || exit;

final class Handler
{
    private Repository $subscribers;

    public function __construct()
    {
        $this->subscribers = new Repository();
        $this->register_hooks();
    }

    private function register_hooks(): void
    {
        add_action('ams_send_double_optin_email', [$this, 'send_double_optin_email']);

        // AJAX handler for double opt-in confirmation.
        add_action('init', [$this, 'handle_optin_confirmation']);
    }

    /**
     * Send double opt-in confirmation email.
     */
    public function send_double_optin_email(object $subscriber): void
    {
        if (empty($subscriber->email) || empty($subscriber->unsubscribe_token)) {
            return;
        }

        $confirm_url = add_query_arg([
            'ams_confirm' => '1',
            'token'       => $subscriber->unsubscribe_token,
        ], home_url('/'));

        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: site name */
            __('Confirm your subscription to %s', 'apotheca-marketing-suite'),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: subscriber first name, 2: site name, 3: confirmation URL */
            __(
                "Hi %1\$s,\n\nThank you for subscribing to %2\$s!\n\nPlease confirm your subscription by clicking the link below:\n\n%3\$s\n\nIf you did not subscribe, you can safely ignore this email.\n\nBest regards,\n%2\$s",
                'apotheca-marketing-suite'
            ),
            $subscriber->first_name ?: __('there', 'apotheca-marketing-suite'),
            $site_name,
            esc_url($confirm_url)
        );

        wp_mail($subscriber->email, $subject, $message);
    }

    /**
     * Handle double opt-in confirmation link clicks.
     */
    public function handle_optin_confirmation(): void
    {
        if (empty($_GET['ams_confirm']) || empty($_GET['token'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['token']));
        $subscriber = $this->subscribers->find_by_token($token);

        if (!$subscriber) {
            wp_die(
                esc_html__('Invalid confirmation link.', 'apotheca-marketing-suite'),
                esc_html__('Subscription Confirmation', 'apotheca-marketing-suite'),
                ['response' => 404]
            );
        }

        if ($subscriber->status === 'pending') {
            $now = current_time('mysql', true);
            $this->subscribers->update((int) $subscriber->id, [
                'status'         => 'subscribed',
                'subscribed_at'  => $now,
                'gdpr_consent'   => 1,
                'gdpr_timestamp' => $now,
            ]);

            do_action('ams_subscriber_confirmed', (int) $subscriber->id);
        }

        wp_die(
            esc_html__('Your subscription has been confirmed. Thank you!', 'apotheca-marketing-suite'),
            esc_html__('Subscription Confirmed', 'apotheca-marketing-suite'),
            ['response' => 200]
        );
    }

    /**
     * Process an unsubscribe request from the public endpoint.
     */
    public function process_unsubscribe(): void
    {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        if (empty($token)) {
            wp_die(
                esc_html__('Invalid unsubscribe link.', 'apotheca-marketing-suite'),
                esc_html__('Unsubscribe', 'apotheca-marketing-suite'),
                ['response' => 400]
            );
        }

        $subscriber = $this->subscribers->find_by_token($token);

        if (!$subscriber) {
            wp_die(
                esc_html__('Invalid unsubscribe link.', 'apotheca-marketing-suite'),
                esc_html__('Unsubscribe', 'apotheca-marketing-suite'),
                ['response' => 404]
            );
        }

        if ($subscriber->status !== 'unsubscribed') {
            $this->subscribers->unsubscribe((int) $subscriber->id);

            do_action('ams_subscriber_unsubscribed', (int) $subscriber->id);
        }

        $title = Settings::get('unsubscribe_page_title', 'Unsubscribe');
        $message = Settings::get('unsubscribe_page_message', 'You have been successfully unsubscribed.');

        wp_die(
            esc_html($message),
            esc_html($title),
            ['response' => 200]
        );
    }
}
