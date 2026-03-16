<?php
/**
 * Revenue attribution engine.
 *
 * Last-click attribution: when an order is placed, look back X days
 * in ams_sends for the most recent opened or clicked send for that
 * subscriber and attribute the order revenue.
 *
 * @package Apotheca\Marketing\Analytics
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Analytics;

use Apotheca\Marketing\Settings;
use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class RevenueAttributor
{
    private string $sends_table;
    private string $attr_table;
    private string $steps_table;

    public function __construct()
    {
        global $wpdb;
        $this->sends_table = $wpdb->prefix . 'ams_sends';
        $this->attr_table  = $wpdb->prefix . 'ams_attributions';
        $this->steps_table = $wpdb->prefix . 'ams_flow_steps';

        add_action('woocommerce_checkout_order_processed', [$this, 'attribute_order'], 30, 3);
    }

    /**
     * Attribute revenue when an order is placed.
     */
    public function attribute_order(int $order_id, array $posted_data, \WC_Order $order): void
    {
        $email = $order->get_billing_email();
        if (empty($email)) {
            return;
        }

        $repo = new SubscriberRepository();
        $subscriber = $repo->find_by_email($email);
        if (!$subscriber) {
            return;
        }

        $window_days = (int) Settings::get('attribution_window_days', 5);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($window_days * DAY_IN_SECONDS));

        global $wpdb;

        // Find the most recent send that was opened or clicked within the window.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $send = $wpdb->get_row($wpdb->prepare(
            "SELECT id, campaign_id, flow_step_id, channel
             FROM {$this->sends_table}
             WHERE subscriber_id = %d
               AND (opened_at IS NOT NULL OR clicked_at IS NOT NULL)
               AND sent_at >= %s
             ORDER BY COALESCE(clicked_at, opened_at) DESC
             LIMIT 1",
            (int) $subscriber->id,
            $cutoff
        ));

        if (!$send) {
            return;
        }

        $order_total = (float) $order->get_total();

        // Update revenue on the send record.
        $wpdb->update(
            $this->sends_table,
            ['revenue_attributed' => $order_total],
            ['id' => (int) $send->id]
        );

        // Resolve flow_id from flow_step_id.
        $flow_id = null;
        if ($send->flow_step_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $step = $wpdb->get_row($wpdb->prepare(
                "SELECT flow_id FROM {$this->steps_table} WHERE id = %d",
                (int) $send->flow_step_id
            ));
            $flow_id = $step ? (int) $step->flow_id : null;
        }

        // Create attribution record.
        $wpdb->insert($this->attr_table, [
            'send_id'       => (int) $send->id,
            'campaign_id'   => $send->campaign_id ? (int) $send->campaign_id : null,
            'flow_id'       => $flow_id,
            'flow_step_id'  => $send->flow_step_id ? (int) $send->flow_step_id : null,
            'subscriber_id' => (int) $subscriber->id,
            'order_id'      => $order_id,
            'order_total'   => $order_total,
            'attributed_at' => current_time('mysql', true),
        ]);
    }
}
