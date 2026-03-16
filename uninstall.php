<?php
/**
 * Uninstall routine for Apotheca® Marketing Suite.
 *
 * Drops all custom tables and removes options when the user chooses
 * to delete the plugin data on uninstall.
 *
 * @package Apotheca\Marketing
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Check if the user opted to remove data on uninstall.
$settings = get_option('ams_settings', []);
$remove_data = $settings['remove_data_on_uninstall'] ?? false;

if (!$remove_data) {
    return;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'ams_subscribers',
    $wpdb->prefix . 'ams_events',
    $wpdb->prefix . 'ams_flows',
    $wpdb->prefix . 'ams_flow_steps',
    $wpdb->prefix . 'ams_campaigns',
    $wpdb->prefix . 'ams_segments',
    $wpdb->prefix . 'ams_sends',
    $wpdb->prefix . 'ams_forms',
];

foreach ($tables as $table) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

delete_option('ams_settings');
delete_option('ams_db_version');

// Clean up any Action Scheduler actions.
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('ams_abandoned_cart_check');
    as_unschedule_all_actions('ams_rfm_nightly');
    as_unschedule_all_actions('ams_segment_recalculate');
}
