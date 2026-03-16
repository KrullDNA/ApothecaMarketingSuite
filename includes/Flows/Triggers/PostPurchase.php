<?php
/**
 * Post-purchase trigger — fires on woocommerce_order_status_completed.
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class PostPurchase extends AbstractTrigger
{
    protected function get_trigger_type(): string
    {
        return 'post_purchase';
    }

    public function register(): void
    {
        if (function_exists('WC')) {
            add_action('woocommerce_order_status_completed', [$this, 'on_order_completed'], 20, 1);
        }

        // Also listen for the sync trigger from the ingest endpoint.
        add_action('ams_trigger_post_purchase', [$this, 'on_sync_post_purchase'], 10, 2);
    }

    public function on_sync_post_purchase(int $subscriber_id, array $payload = []): void
    {
        $repo = new SubscriberRepository();
        $subscriber = $repo->find($subscriber_id);
        if ($subscriber && $subscriber->status === 'subscribed') {
            $this->enrol_subscriber($subscriber_id);
        }
    }

    public function on_order_completed(int $order_id): void
    {
        // NOTE: On standalone deployment this value is populated via the
        // AMS ingest endpoint receiving data from the remote WooCommerce
        // store. This direct WC call only fires if WooCommerce is present.
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $email = $order->get_billing_email();
        if (empty($email)) {
            return;
        }

        $repo = new SubscriberRepository();
        $subscriber = $repo->find_by_email($email);
        if ($subscriber && $subscriber->status === 'subscribed') {
            $this->enrol_subscriber((int) $subscriber->id);
        }
    }
}
