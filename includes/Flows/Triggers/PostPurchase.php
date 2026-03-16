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
        add_action('woocommerce_order_status_completed', [$this, 'on_order_completed'], 20, 1);
    }

    public function on_order_completed(int $order_id): void
    {
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
