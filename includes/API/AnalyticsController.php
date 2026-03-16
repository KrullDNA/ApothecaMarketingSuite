<?php
/**
 * REST API controller for the analytics dashboard.
 *
 * All date-range queries read from ams_analytics_daily (pre-aggregated).
 * Live queries only used for real-time data (campaign tables, flow funnels).
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

defined('ABSPATH') || exit;

final class AnalyticsController
{
    private string $daily_table;
    private string $sends_table;
    private string $attr_table;
    private string $subs_table;
    private string $campaigns_table;
    private string $flows_table;
    private string $steps_table;
    private string $enrolments_table;

    public function __construct()
    {
        global $wpdb;
        $this->daily_table      = $wpdb->prefix . 'ams_analytics_daily';
        $this->sends_table      = $wpdb->prefix . 'ams_sends';
        $this->attr_table       = $wpdb->prefix . 'ams_attributions';
        $this->subs_table       = $wpdb->prefix . 'ams_subscribers';
        $this->campaigns_table  = $wpdb->prefix . 'ams_campaigns';
        $this->flows_table      = $wpdb->prefix . 'ams_flows';
        $this->steps_table      = $wpdb->prefix . 'ams_flow_steps';
        $this->enrolments_table = $wpdb->prefix . 'ams_flow_enrolments';

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        $ns = 'ams/v1';
        $admin = ['callback' => '__return_true'];
        $perm  = function () {
            return current_user_can('manage_woocommerce');
        };

        // Overview.
        register_rest_route($ns, '/analytics/overview', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_overview'],
            'permission_callback' => $perm,
        ]);

        // Subscriber growth chart.
        register_rest_route($ns, '/analytics/subscriber-growth', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_subscriber_growth'],
            'permission_callback' => $perm,
        ]);

        // Revenue by channel chart.
        register_rest_route($ns, '/analytics/revenue-by-channel', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_revenue_by_channel'],
            'permission_callback' => $perm,
        ]);

        // Email performance table.
        register_rest_route($ns, '/analytics/email-performance', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_email_performance'],
            'permission_callback' => $perm,
        ]);

        // Bounce log.
        register_rest_route($ns, '/analytics/bounce-log', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_bounce_log'],
            'permission_callback' => $perm,
        ]);

        // SMS performance.
        register_rest_route($ns, '/analytics/sms-performance', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_sms_performance'],
            'permission_callback' => $perm,
        ]);

        // SMS delivery trend.
        register_rest_route($ns, '/analytics/sms-delivery-trend', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_sms_delivery_trend'],
            'permission_callback' => $perm,
        ]);

        // Subscriber insights — RFM heatmap.
        register_rest_route($ns, '/analytics/rfm-heatmap', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_rfm_heatmap'],
            'permission_callback' => $perm,
        ]);

        // Subscriber insights — segment breakdown.
        register_rest_route($ns, '/analytics/segment-breakdown', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_segment_breakdown'],
            'permission_callback' => $perm,
        ]);

        // Subscriber insights — churn distribution.
        register_rest_route($ns, '/analytics/churn-distribution', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_churn_distribution'],
            'permission_callback' => $perm,
        ]);

        // Subscriber insights — CLV distribution.
        register_rest_route($ns, '/analytics/clv-distribution', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_clv_distribution'],
            'permission_callback' => $perm,
        ]);

        // Flow analytics — funnel data.
        register_rest_route($ns, '/analytics/flow-funnels', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_flow_funnels'],
            'permission_callback' => $perm,
        ]);

        // CSV export.
        register_rest_route($ns, '/analytics/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'export_csv'],
            'permission_callback' => $perm,
        ]);
    }

    /**
     * GET /analytics/overview — key metric cards.
     */
    public function get_overview(\WP_REST_Request $request): \WP_REST_Response
    {
        $range = $this->parse_range($request);

        $metrics = $this->sum_metrics(
            ['total_revenue', 'email_revenue', 'sms_revenue'],
            $range['start'],
            $range['end']
        );

        global $wpdb;

        // Active subscribers (latest snapshot).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->subs_table} WHERE status = 'subscribed'"
        );

        // AOV from attributed orders in range.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $aov_data = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(order_total) as aov, COUNT(*) as orders
             FROM {$this->attr_table}
             WHERE attributed_at BETWEEN %s AND %s",
            $range['start'] . ' 00:00:00',
            $range['end'] . ' 23:59:59'
        ));

        return new \WP_REST_Response([
            'total_revenue'      => round((float) ($metrics['total_revenue'] ?? 0), 2),
            'email_revenue'      => round((float) ($metrics['email_revenue'] ?? 0), 2),
            'sms_revenue'        => round((float) ($metrics['sms_revenue'] ?? 0), 2),
            'active_subscribers' => $active,
            'aov'                => round((float) ($aov_data->aov ?? 0), 2),
            'attributed_orders'  => (int) ($aov_data->orders ?? 0),
            'period'             => $range,
        ]);
    }

    /**
     * GET /analytics/subscriber-growth — daily line chart data.
     */
    public function get_subscriber_growth(\WP_REST_Request $request): \WP_REST_Response
    {
        $range = $this->parse_range($request);

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT date, metric_value FROM {$this->daily_table}
             WHERE metric_key = 'new_subscribers' AND date BETWEEN %s AND %s
             ORDER BY date ASC",
            $range['start'],
            $range['end']
        ));

        $data = [];
        foreach ($rows ?: [] as $row) {
            $data[] = ['date' => $row->date, 'count' => (int) $row->metric_value];
        }

        return new \WP_REST_Response($data);
    }

    /**
     * GET /analytics/revenue-by-channel — bar chart data.
     */
    public function get_revenue_by_channel(\WP_REST_Request $request): \WP_REST_Response
    {
        $range = $this->parse_range($request);

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT date, metric_key, metric_value FROM {$this->daily_table}
             WHERE metric_key IN ('email_revenue','sms_revenue') AND date BETWEEN %s AND %s
             ORDER BY date ASC",
            $range['start'],
            $range['end']
        ));

        $by_date = [];
        foreach ($rows ?: [] as $row) {
            $by_date[$row->date][$row->metric_key] = round((float) $row->metric_value, 2);
        }

        $data = [];
        foreach ($by_date as $date => $vals) {
            $data[] = [
                'date'          => $date,
                'email_revenue' => $vals['email_revenue'] ?? 0,
                'sms_revenue'   => $vals['sms_revenue'] ?? 0,
            ];
        }

        return new \WP_REST_Response($data);
    }

    /**
     * GET /analytics/email-performance — per campaign + per flow metrics.
     */
    public function get_email_performance(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $page     = max(1, (int) $request->get_param('page') ?: 1);
        $per_page = max(1, min(100, (int) $request->get_param('per_page') ?: 20));
        $orderby  = sanitize_text_field($request->get_param('orderby') ?: 'sent');
        $order    = strtoupper($request->get_param('order') ?: 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $offset   = ($page - 1) * $per_page;

        $allowed_order = ['name', 'sent', 'delivered', 'opened', 'clicked', 'unsubscribed', 'revenue'];
        if (!in_array($orderby, $allowed_order, true)) {
            $orderby = 'sent';
        }

        // Campaign metrics.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT
                c.id, c.name, 'campaign' AS type,
                COUNT(s.id) as sent,
                SUM(CASE WHEN s.status = 'delivered' OR s.opened_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN s.unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed,
                SUM(s.revenue_attributed) as revenue
             FROM {$this->campaigns_table} c
             LEFT JOIN {$this->sends_table} s ON s.campaign_id = c.id AND s.channel = 'email'
             WHERE c.type = 'email'
             GROUP BY c.id
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        // Flow step metrics (grouped by flow).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $flows = $wpdb->get_results(
            "SELECT
                f.id, f.name, 'flow' AS type,
                COUNT(s.id) as sent,
                SUM(CASE WHEN s.status = 'delivered' OR s.opened_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN s.unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed,
                SUM(s.revenue_attributed) as revenue
             FROM {$this->flows_table} f
             INNER JOIN {$this->steps_table} fs ON fs.flow_id = f.id AND fs.step_type = 'send_email'
             LEFT JOIN {$this->sends_table} s ON s.flow_step_id = fs.id AND s.channel = 'email'
             GROUP BY f.id"
        );

        $items = [];
        foreach (array_merge($campaigns ?: [], $flows ?: []) as $row) {
            $sent = max(1, (int) $row->sent);
            $items[] = [
                'id'               => (int) $row->id,
                'name'             => $row->name,
                'type'             => $row->type,
                'sent'             => (int) $row->sent,
                'delivered'        => (int) $row->delivered,
                'opened'           => (int) $row->opened,
                'clicked'          => (int) $row->clicked,
                'unsubscribed'     => (int) $row->unsubscribed,
                'open_rate'        => round(((int) $row->opened / $sent) * 100, 1),
                'click_rate'       => round(((int) $row->clicked / $sent) * 100, 1),
                'unsub_rate'       => round(((int) $row->unsubscribed / $sent) * 100, 1),
                'deliverability'   => round(((int) $row->delivered / $sent) * 100, 1),
                'revenue'          => round((float) $row->revenue, 2),
                'revenue_per_send' => round((float) $row->revenue / $sent, 2),
            ];
        }

        return new \WP_REST_Response($items);
    }

    /**
     * GET /analytics/bounce-log — hard + soft bounces.
     */
    public function get_bounce_log(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $page     = max(1, (int) $request->get_param('page') ?: 1);
        $per_page = max(1, min(100, (int) $request->get_param('per_page') ?: 50));
        $offset   = ($page - 1) * $per_page;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $bounces = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.subscriber_id, s.campaign_id, s.flow_step_id, s.bounced_at, s.channel,
                    sub.email, sub.first_name, sub.last_name
             FROM {$this->sends_table} s
             LEFT JOIN {$this->subs_table} sub ON sub.id = s.subscriber_id
             WHERE s.bounced_at IS NOT NULL
             ORDER BY s.bounced_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->sends_table} WHERE bounced_at IS NOT NULL"
        );

        $items = [];
        foreach ($bounces ?: [] as $b) {
            $items[] = [
                'send_id'       => (int) $b->id,
                'email'         => $b->email ?? '',
                'name'          => trim(($b->first_name ?? '') . ' ' . ($b->last_name ?? '')),
                'campaign_id'   => $b->campaign_id ? (int) $b->campaign_id : null,
                'flow_step_id'  => $b->flow_step_id ? (int) $b->flow_step_id : null,
                'channel'       => $b->channel,
                'bounced_at'    => $b->bounced_at,
            ];
        }

        return new \WP_REST_Response(['items' => $items, 'total' => $total]);
    }

    /**
     * GET /analytics/sms-performance — per SMS campaign metrics.
     */
    public function get_sms_performance(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $campaigns = $wpdb->get_results(
            "SELECT
                c.id, c.name,
                COUNT(s.id) as sent,
                SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN s.unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as opt_outs,
                SUM(s.revenue_attributed) as revenue
             FROM {$this->campaigns_table} c
             LEFT JOIN {$this->sends_table} s ON s.campaign_id = c.id AND s.channel = 'sms'
             WHERE c.type = 'sms'
             GROUP BY c.id
             ORDER BY c.created_at DESC"
        );

        $items = [];
        foreach ($campaigns ?: [] as $row) {
            $sent = max(1, (int) $row->sent);
            $items[] = [
                'id'            => (int) $row->id,
                'name'          => $row->name,
                'sent'          => (int) $row->sent,
                'delivered'     => (int) $row->delivered,
                'delivery_rate' => round(((int) $row->delivered / $sent) * 100, 1),
                'opt_outs'      => (int) $row->opt_outs,
                'opt_out_rate'  => round(((int) $row->opt_outs / $sent) * 100, 1),
                'revenue'       => round((float) $row->revenue, 2),
            ];
        }

        return new \WP_REST_Response($items);
    }

    /**
     * GET /analytics/sms-delivery-trend — 30-day line chart.
     */
    public function get_sms_delivery_trend(\WP_REST_Request $request): \WP_REST_Response
    {
        $range = $this->parse_range($request);

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sent_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT date, metric_value FROM {$this->daily_table}
             WHERE metric_key = 'sms_sent' AND date BETWEEN %s AND %s ORDER BY date ASC",
            $range['start'],
            $range['end']
        ));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $del_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT date, metric_value FROM {$this->daily_table}
             WHERE metric_key = 'sms_delivered' AND date BETWEEN %s AND %s ORDER BY date ASC",
            $range['start'],
            $range['end']
        ));

        $sent_map = [];
        foreach ($sent_rows ?: [] as $r) {
            $sent_map[$r->date] = (float) $r->metric_value;
        }
        $del_map = [];
        foreach ($del_rows ?: [] as $r) {
            $del_map[$r->date] = (float) $r->metric_value;
        }

        $data = [];
        $all_dates = array_unique(array_merge(array_keys($sent_map), array_keys($del_map)));
        sort($all_dates);
        foreach ($all_dates as $date) {
            $s = $sent_map[$date] ?? 0;
            $d = $del_map[$date] ?? 0;
            $data[] = [
                'date'          => $date,
                'sent'          => (int) $s,
                'delivered'     => (int) $d,
                'delivery_rate' => $s > 0 ? round(($d / $s) * 100, 1) : 0,
            ];
        }

        return new \WP_REST_Response($data);
    }

    /**
     * GET /analytics/rfm-heatmap — 5x5 grid (R x F).
     */
    public function get_rfm_heatmap(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT
                SUBSTRING(rfm_score, 1, 1) as r,
                SUBSTRING(rfm_score, 2, 1) as f,
                COUNT(*) as count
             FROM {$this->subs_table}
             WHERE rfm_score != '' AND LENGTH(rfm_score) >= 2
             GROUP BY r, f"
        );

        $grid = [];
        for ($r = 1; $r <= 5; $r++) {
            for ($f = 1; $f <= 5; $f++) {
                $grid["{$r}-{$f}"] = 0;
            }
        }

        foreach ($rows ?: [] as $row) {
            $key = "{$row->r}-{$row->f}";
            if (isset($grid[$key])) {
                $grid[$key] = (int) $row->count;
            }
        }

        return new \WP_REST_Response($grid);
    }

    /**
     * GET /analytics/segment-breakdown — doughnut chart of RFM segments.
     */
    public function get_segment_breakdown(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT rfm_segment, COUNT(*) as count
             FROM {$this->subs_table}
             WHERE rfm_segment != ''
             GROUP BY rfm_segment
             ORDER BY count DESC"
        );

        $data = [];
        foreach ($rows ?: [] as $row) {
            $data[] = ['segment' => $row->rfm_segment, 'count' => (int) $row->count];
        }

        return new \WP_REST_Response($data);
    }

    /**
     * GET /analytics/churn-distribution — low/medium/high risk counts.
     */
    public function get_churn_distribution(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $low = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->subs_table} WHERE churn_risk_score BETWEEN 0 AND 33 AND status = 'subscribed'"
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $medium = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->subs_table} WHERE churn_risk_score BETWEEN 34 AND 66 AND status = 'subscribed'"
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $high = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->subs_table} WHERE churn_risk_score BETWEEN 67 AND 100 AND status = 'subscribed'"
        );

        return new \WP_REST_Response([
            ['label' => 'Low (0-33)', 'count' => $low],
            ['label' => 'Medium (34-66)', 'count' => $medium],
            ['label' => 'High (67-100)', 'count' => $high],
        ]);
    }

    /**
     * GET /analytics/clv-distribution — histogram buckets.
     */
    public function get_clv_distribution(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN predicted_clv = 0 THEN '$0'
                    WHEN predicted_clv <= 50 THEN '$1-50'
                    WHEN predicted_clv <= 100 THEN '$51-100'
                    WHEN predicted_clv <= 250 THEN '$101-250'
                    WHEN predicted_clv <= 500 THEN '$251-500'
                    WHEN predicted_clv <= 1000 THEN '$501-1000'
                    ELSE '$1000+'
                END as bucket,
                COUNT(*) as count
             FROM {$this->subs_table}
             WHERE status = 'subscribed'
             GROUP BY bucket
             ORDER BY MIN(predicted_clv) ASC"
        );

        $data = [];
        foreach ($rows ?: [] as $row) {
            $data[] = ['bucket' => $row->bucket, 'count' => (int) $row->count];
        }

        return new \WP_REST_Response($data);
    }

    /**
     * GET /analytics/flow-funnels — per-flow step funnel data.
     */
    public function get_flow_funnels(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // Get all active/paused flows.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $flows = $wpdb->get_results(
            "SELECT id, name FROM {$this->flows_table} ORDER BY name ASC"
        );

        $result = [];
        foreach ($flows ?: [] as $flow) {
            $flow_id = (int) $flow->id;

            // Total enrolled.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $enrolled = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->enrolments_table} WHERE flow_id = %d",
                $flow_id
            ));

            // Completed.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $completed = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->enrolments_table} WHERE flow_id = %d AND status = 'completed'",
                $flow_id
            ));

            // Exited.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $exited = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->enrolments_table} WHERE flow_id = %d AND status = 'exited'",
                $flow_id
            ));

            // Steps with send counts.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $steps = $wpdb->get_results($wpdb->prepare(
                "SELECT fs.id, fs.step_type, fs.step_order, fs.subject, fs.sms_body,
                        COUNT(s.id) as sends
                 FROM {$this->steps_table} fs
                 LEFT JOIN {$this->sends_table} s ON s.flow_step_id = fs.id
                 WHERE fs.flow_id = %d
                 GROUP BY fs.id
                 ORDER BY fs.step_order ASC",
                $flow_id
            ));

            $step_data = [];
            $prev_count = $enrolled;
            foreach ($steps ?: [] as $step) {
                $count = (int) $step->sends ?: $prev_count;
                $drop_off = $prev_count > 0 ? round((1 - ($count / $prev_count)) * 100, 1) : 0;
                $step_data[] = [
                    'id'        => (int) $step->id,
                    'type'      => $step->step_type,
                    'order'     => (int) $step->step_order,
                    'label'     => $step->subject ?: $step->sms_body ?: $step->step_type,
                    'count'     => $count,
                    'drop_off'  => $drop_off,
                    'highlight' => $drop_off > 20,
                ];
                $prev_count = $count;
            }

            // Revenue attributed to this flow.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $revenue = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(order_total) FROM {$this->attr_table} WHERE flow_id = %d",
                $flow_id
            ));

            $result[] = [
                'id'        => $flow_id,
                'name'      => $flow->name,
                'enrolled'  => $enrolled,
                'completed' => $completed,
                'exited'    => $exited,
                'revenue'   => round($revenue, 2),
                'steps'     => $step_data,
            ];
        }

        return new \WP_REST_Response($result);
    }

    /**
     * GET /analytics/export — CSV export.
     */
    public function export_csv(\WP_REST_Request $request): \WP_REST_Response
    {
        $type = sanitize_text_field($request->get_param('type') ?: 'subscribers');

        global $wpdb;

        $rows = [];
        $headers = [];

        switch ($type) {
            case 'subscribers':
                $segment_id = (int) $request->get_param('segment_id');
                if ($segment_id > 0) {
                    $calc = new \Apotheca\Marketing\Segments\SegmentCalculator();
                    $ids = $calc->get_matching_subscriber_ids($segment_id);
                    if (empty($ids)) {
                        return new \WP_REST_Response(['csv' => '', 'filename' => 'subscribers.csv']);
                    }
                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$this->subs_table} WHERE id IN ({$placeholders})",
                            ...$ids
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $rows = $wpdb->get_results("SELECT * FROM {$this->subs_table}", ARRAY_A);
                }
                break;

            case 'email-performance':
                $response = $this->get_email_performance($request);
                $rows = $response->get_data();
                break;

            case 'sms-performance':
                $response = $this->get_sms_performance($request);
                $rows = $response->get_data();
                break;

            case 'bounce-log':
                $response = $this->get_bounce_log($request);
                $data = $response->get_data();
                $rows = $data['items'] ?? [];
                break;

            default:
                return new \WP_REST_Response(['error' => 'Invalid export type'], 400);
        }

        if (empty($rows)) {
            return new \WP_REST_Response(['csv' => '', 'filename' => $type . '.csv']);
        }

        $first = is_object($rows[0]) ? (array) $rows[0] : $rows[0];
        $headers = array_keys($first);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            $row_array = is_object($row) ? (array) $row : $row;
            fputcsv($output, array_values($row_array));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return new \WP_REST_Response([
            'csv'      => $csv,
            'filename' => $type . '-' . gmdate('Y-m-d') . '.csv',
        ]);
    }

    /**
     * Parse date range from request params.
     *
     * @return array{start: string, end: string}
     */
    private function parse_range(\WP_REST_Request $request): array
    {
        $period = sanitize_text_field($request->get_param('period') ?: '30d');
        $end    = gmdate('Y-m-d');

        switch ($period) {
            case '7d':
                $start = gmdate('Y-m-d', strtotime('-7 days'));
                break;
            case '30d':
                $start = gmdate('Y-m-d', strtotime('-30 days'));
                break;
            case 'all':
                $start = '2020-01-01';
                break;
            case 'custom':
                $start = sanitize_text_field($request->get_param('start') ?: gmdate('Y-m-d', strtotime('-30 days')));
                $end   = sanitize_text_field($request->get_param('end') ?: $end);
                break;
            default:
                $start = gmdate('Y-m-d', strtotime('-30 days'));
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Sum metrics from ams_analytics_daily for a date range.
     *
     * @param string[] $keys
     * @return array<string,float>
     */
    private function sum_metrics(array $keys, string $start, string $end): array
    {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $params = array_merge($keys, [$start, $end]);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT metric_key, SUM(metric_value) as total
                 FROM {$this->daily_table}
                 WHERE metric_key IN ({$placeholders}) AND date BETWEEN %s AND %s
                 GROUP BY metric_key",
                ...$params
            )
        );

        $result = [];
        foreach ($rows ?: [] as $row) {
            $result[$row->metric_key] = (float) $row->total;
        }

        return $result;
    }
}
