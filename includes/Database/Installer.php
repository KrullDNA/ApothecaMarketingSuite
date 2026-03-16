<?php
/**
 * Database table installer.
 *
 * Creates all custom ams_* tables on plugin activation and upgrades.
 *
 * @package Apotheca\Marketing\Database
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Database;

defined('ABSPATH') || exit;

final class Installer
{
    /**
     * Run dbDelta for all tables.
     */
    public function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = $this->get_subscribers_schema($prefix, $charset_collate);
        $sql .= $this->get_events_schema($prefix, $charset_collate);
        $sql .= $this->get_flows_schema($prefix, $charset_collate);
        $sql .= $this->get_flow_steps_schema($prefix, $charset_collate);
        $sql .= $this->get_campaigns_schema($prefix, $charset_collate);
        $sql .= $this->get_segments_schema($prefix, $charset_collate);
        $sql .= $this->get_sends_schema($prefix, $charset_collate);
        $sql .= $this->get_forms_schema($prefix, $charset_collate);
        $sql .= $this->get_flow_enrolments_schema($prefix, $charset_collate);
        $sql .= $this->get_attributions_schema($prefix, $charset_collate);
        $sql .= $this->get_analytics_daily_schema($prefix, $charset_collate);

        dbDelta($sql);
    }

    private function get_subscribers_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_subscribers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            phone varchar(30) DEFAULT '' NOT NULL,
            first_name varchar(100) DEFAULT '' NOT NULL,
            last_name varchar(100) DEFAULT '' NOT NULL,
            status varchar(20) DEFAULT 'subscribed' NOT NULL,
            source varchar(50) DEFAULT '' NOT NULL,
            subscribed_at datetime DEFAULT NULL,
            unsubscribed_at datetime DEFAULT NULL,
            gdpr_consent tinyint(1) DEFAULT 0 NOT NULL,
            gdpr_timestamp datetime DEFAULT NULL,
            tags longtext DEFAULT NULL,
            custom_fields longtext DEFAULT NULL,
            predicted_clv decimal(10,2) DEFAULT 0.00 NOT NULL,
            predicted_next_order datetime DEFAULT NULL,
            churn_risk_score tinyint(3) unsigned DEFAULT 0 NOT NULL,
            rfm_score varchar(5) DEFAULT '' NOT NULL,
            rfm_segment varchar(30) DEFAULT '' NOT NULL,
            total_orders int(10) unsigned DEFAULT 0 NOT NULL,
            total_spent decimal(12,2) DEFAULT 0.00 NOT NULL,
            last_order_date datetime DEFAULT NULL,
            sms_opt_in tinyint(1) DEFAULT 0 NOT NULL,
            unsubscribe_token varchar(64) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY rfm_segment (rfm_segment),
            KEY churn_risk_score (churn_risk_score),
            KEY source (source),
            KEY unsubscribe_token (unsubscribe_token),
            KEY last_order_date (last_order_date),
            KEY sms_opt_in (sms_opt_in)
        ) $charset_collate;\n\n";
    }

    private function get_events_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            woo_order_id bigint(20) unsigned DEFAULT NULL,
            product_ids longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY subscriber_id (subscriber_id),
            KEY event_type (event_type),
            KEY woo_order_id (woo_order_id),
            KEY created_at (created_at),
            KEY subscriber_event (subscriber_id, event_type)
        ) $charset_collate;\n\n";
    }

    private function get_flows_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_flows (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            trigger_type varchar(50) NOT NULL,
            trigger_config longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'draft' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY trigger_type (trigger_type),
            KEY status (status)
        ) $charset_collate;\n\n";
    }

    private function get_flow_steps_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_flow_steps (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            flow_id bigint(20) unsigned NOT NULL,
            step_type varchar(30) NOT NULL,
            step_order int(10) unsigned DEFAULT 0 NOT NULL,
            delay_value int(10) unsigned DEFAULT 0 NOT NULL,
            delay_unit varchar(10) DEFAULT 'minutes' NOT NULL,
            subject varchar(255) DEFAULT '' NOT NULL,
            preview_text varchar(255) DEFAULT '' NOT NULL,
            body_html longtext DEFAULT NULL,
            body_text longtext DEFAULT NULL,
            sms_body text DEFAULT NULL,
            conditions longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY flow_id (flow_id),
            KEY step_order (flow_id, step_order)
        ) $charset_collate;\n\n";
    }

    private function get_campaigns_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_campaigns (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(20) DEFAULT 'email' NOT NULL,
            status varchar(20) DEFAULT 'draft' NOT NULL,
            segment_id bigint(20) unsigned DEFAULT NULL,
            subject varchar(255) DEFAULT '' NOT NULL,
            preview_text varchar(255) DEFAULT '' NOT NULL,
            body_html longtext DEFAULT NULL,
            body_text longtext DEFAULT NULL,
            sms_body text DEFAULT NULL,
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY type (type),
            KEY segment_id (segment_id),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;\n\n";
    }

    private function get_segments_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_segments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            conditions longtext DEFAULT NULL,
            subscriber_count int(10) unsigned DEFAULT 0 NOT NULL,
            last_calculated datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;\n\n";
    }

    private function get_sends_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_sends (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned DEFAULT NULL,
            flow_step_id bigint(20) unsigned DEFAULT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            channel varchar(10) DEFAULT 'email' NOT NULL,
            status varchar(20) DEFAULT 'queued' NOT NULL,
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            bounced_at datetime DEFAULT NULL,
            unsubscribed_at datetime DEFAULT NULL,
            revenue_attributed decimal(10,2) DEFAULT 0.00 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id),
            KEY flow_step_id (flow_step_id),
            KEY subscriber_id (subscriber_id),
            KEY channel (channel),
            KEY status (status),
            KEY sent_at (sent_at),
            KEY subscriber_channel (subscriber_id, channel, sent_at)
        ) $charset_collate;\n\n";
    }

    private function get_forms_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_forms (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(30) DEFAULT 'modal' NOT NULL,
            trigger_config longtext DEFAULT NULL,
            targeting_config longtext DEFAULT NULL,
            fields longtext DEFAULT NULL,
            design_config longtext DEFAULT NULL,
            success_config longtext DEFAULT NULL,
            spin_config longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'draft' NOT NULL,
            views int(10) unsigned DEFAULT 0 NOT NULL,
            submissions int(10) unsigned DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY type (type)
        ) $charset_collate;\n\n";
    }

    private function get_attributions_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_attributions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            send_id bigint(20) unsigned NOT NULL,
            campaign_id bigint(20) unsigned DEFAULT NULL,
            flow_id bigint(20) unsigned DEFAULT NULL,
            flow_step_id bigint(20) unsigned DEFAULT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_total decimal(12,2) DEFAULT 0.00 NOT NULL,
            attributed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY send_id (send_id),
            KEY campaign_id (campaign_id),
            KEY flow_id (flow_id),
            KEY subscriber_id (subscriber_id),
            KEY order_id (order_id),
            KEY attributed_at (attributed_at)
        ) $charset_collate;\n\n";
    }

    private function get_analytics_daily_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_analytics_daily (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            metric_key varchar(100) NOT NULL,
            metric_value decimal(14,2) DEFAULT 0.00 NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY date_metric (date, metric_key),
            KEY metric_key (metric_key),
            KEY date (date)
        ) $charset_collate;\n\n";
    }

    private function get_flow_enrolments_schema(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}ams_flow_enrolments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            flow_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            current_step_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            enrolled_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            completed_at datetime DEFAULT NULL,
            exited_at datetime DEFAULT NULL,
            exit_reason varchar(100) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id),
            KEY flow_id (flow_id),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY flow_subscriber (flow_id, subscriber_id, status),
            KEY current_step_id (current_step_id)
        ) $charset_collate;\n\n";
    }
}
