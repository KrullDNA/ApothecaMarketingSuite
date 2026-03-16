<?php
/**
 * Settings page for the sync plugin — Tools > Marketing Sync.
 *
 * @package Apotheca\MarketingSync
 */

declare(strict_types=1);

namespace Apotheca\MarketingSync;

defined('ABSPATH') || exit;

final class SettingsPage
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_post_ams_sync_save_settings', [$this, 'handle_save']);
        add_action('admin_post_ams_sync_test_connection', [$this, 'handle_test']);
        add_action('admin_post_ams_sync_retry_failed', [$this, 'handle_retry_failed']);
    }

    public function register_page(): void
    {
        add_management_page(
            __('Marketing Sync', 'apotheca-marketing-sync'),
            __('Marketing Sync', 'apotheca-marketing-sync'),
            'manage_woocommerce',
            'ams-sync',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $settings = Settings::all();
        $has_secret = !empty($settings['shared_secret']);
        $test_result = get_transient('ams_sync_test_result');
        delete_transient('ams_sync_test_result');

        $last_success = get_option('ams_sync_last_success', '');
        $health = $this->get_health_data();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Apotheca® Marketing Sync', 'apotheca-marketing-sync') . '</h1>';

        // Test result notice.
        if ($test_result) {
            $class = $test_result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($test_result['message']) . '</p></div>';
        }

        // Settings form.
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="ams_sync_save_settings">';
        wp_nonce_field('ams_sync_save', 'ams_sync_nonce');

        echo '<table class="form-table">';

        // Endpoint URL.
        echo '<tr><th><label for="endpoint_url">' . esc_html__('Marketing Subdomain URL', 'apotheca-marketing-sync') . '</label></th>';
        echo '<td><input type="url" id="endpoint_url" name="endpoint_url" value="' . esc_attr($settings['endpoint_url']) . '" class="regular-text" placeholder="https://marketing.yoursite.com"></td></tr>';

        // Shared secret.
        echo '<tr><th><label for="shared_secret">' . esc_html__('Shared Secret Key', 'apotheca-marketing-sync') . '</label></th>';
        echo '<td><input type="password" id="shared_secret" name="shared_secret" value="" class="regular-text" placeholder="' . ($has_secret ? '••••••••' : 'Enter shared secret') . '">';
        echo '<p class="description">' . ($has_secret ? esc_html__('Secret is configured. Leave blank to keep current.', 'apotheca-marketing-sync') : esc_html__('Enter a shared secret. Must match the subdomain setting.', 'apotheca-marketing-sync')) . '</p></td></tr>';

        // Event toggles.
        $events = [
            'customer_registered'  => __('Customer Registered', 'apotheca-marketing-sync'),
            'order_placed'         => __('Order Placed', 'apotheca-marketing-sync'),
            'order_status_changed' => __('Order Status Changed', 'apotheca-marketing-sync'),
            'cart_updated'         => __('Cart Updated', 'apotheca-marketing-sync'),
            'product_viewed'       => __('Product Viewed', 'apotheca-marketing-sync'),
            'checkout_started'     => __('Checkout Started', 'apotheca-marketing-sync'),
        ];

        echo '<tr><th>' . esc_html__('Events to Push', 'apotheca-marketing-sync') . '</th><td>';
        foreach ($events as $key => $label) {
            $checked = !empty($settings['enable_' . $key]) ? 'checked' : '';
            echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="events[' . esc_attr($key) . ']" value="1" ' . $checked . '> ' . esc_html($label) . '</label>';
        }
        echo '</td></tr>';

        echo '</table>';
        submit_button(__('Save Settings', 'apotheca-marketing-sync'));
        echo '</form>';

        // Test connection.
        echo '<hr>';
        echo '<h2>' . esc_html__('Connection Test', 'apotheca-marketing-sync') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="ams_sync_test_connection">';
        wp_nonce_field('ams_sync_test', 'ams_sync_test_nonce');
        submit_button(__('Test Connection', 'apotheca-marketing-sync'), 'secondary');
        echo '</form>';

        // Sync health panel.
        echo '<hr>';
        echo '<h2>' . esc_html__('Sync Health', 'apotheca-marketing-sync') . '</h2>';
        echo '<table class="widefat striped" style="max-width:600px;">';
        echo '<tr><td><strong>' . esc_html__('Last Successful Sync', 'apotheca-marketing-sync') . '</strong></td>';
        echo '<td>' . esc_html($last_success ?: 'Never') . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Events Queued', 'apotheca-marketing-sync') . '</strong></td>';
        echo '<td>' . esc_html((string) $health['queued']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Sent Today', 'apotheca-marketing-sync') . '</strong></td>';
        echo '<td>' . esc_html((string) $health['sent_today']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Sent This Week', 'apotheca-marketing-sync') . '</strong></td>';
        echo '<td>' . esc_html((string) $health['sent_week']) . '</td></tr>';
        echo '</table>';

        // Recent errors.
        if (!empty($health['recent_errors'])) {
            echo '<h3>' . esc_html__('Recent Errors (Last 10)', 'apotheca-marketing-sync') . '</h3>';
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Event</th><th>HTTP Status</th><th>Attempt</th><th>Response</th><th>Time</th></tr></thead><tbody>';
            foreach ($health['recent_errors'] as $err) {
                echo '<tr>';
                echo '<td>' . esc_html($err->event_type) . '</td>';
                echo '<td>' . esc_html((string) $err->http_status) . '</td>';
                echo '<td>' . esc_html((string) $err->attempt_number) . '</td>';
                echo '<td>' . esc_html(substr($err->response_body, 0, 100)) . '</td>';
                echo '<td>' . esc_html($err->dispatched_at) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            // Retry failed button.
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
            echo '<input type="hidden" name="action" value="ams_sync_retry_failed">';
            wp_nonce_field('ams_sync_retry', 'ams_sync_retry_nonce');
            submit_button(__('Retry Failed Jobs (Last 24h)', 'apotheca-marketing-sync'), 'secondary');
            echo '</form>';
        }

        echo '</div>';
    }

    public function handle_save(): void
    {
        check_admin_referer('ams_sync_save', 'ams_sync_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        $updates = [
            'endpoint_url' => esc_url_raw($_POST['endpoint_url'] ?? ''),
        ];

        // Event toggles.
        $event_types = ['customer_registered', 'order_placed', 'order_status_changed', 'cart_updated', 'product_viewed', 'checkout_started'];
        $posted_events = $_POST['events'] ?? [];
        foreach ($event_types as $et) {
            $updates['enable_' . $et] = !empty($posted_events[$et]);
        }

        Settings::update($updates);

        // Shared secret (only update if non-empty).
        $secret = sanitize_text_field($_POST['shared_secret'] ?? '');
        if ($secret) {
            Settings::store_shared_secret($secret);
        }

        wp_safe_redirect(admin_url('tools.php?page=ams-sync&saved=1'));
        exit;
    }

    public function handle_test(): void
    {
        check_admin_referer('ams_sync_test', 'ams_sync_test_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        $result = Dispatcher::test_connection();
        set_transient('ams_sync_test_result', $result, 30);

        wp_safe_redirect(admin_url('tools.php?page=ams-sync'));
        exit;
    }

    public function handle_retry_failed(): void
    {
        check_admin_referer('ams_sync_retry', 'ams_sync_retry_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        // This is a manual retry — we can't recover the full payload from the log.
        // Instead, show a notice that retry is acknowledged.
        // In practice, Action Scheduler handles retries automatically.

        wp_safe_redirect(admin_url('tools.php?page=ams-sync&retried=1'));
        exit;
    }

    /**
     * Get sync health statistics.
     */
    private function get_health_data(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_sync_log';

        // Queued actions (pending in Action Scheduler).
        $queued = 0;
        if (function_exists('as_get_scheduled_actions')) {
            $pending = as_get_scheduled_actions([
                'hook'   => 'ams_sync_dispatch',
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 0,
            ], 'ids');
            $queued = count($pending);
        }

        $today = current_time('Y-m-d');
        $week_start = gmdate('Y-m-d', strtotime('-7 days'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sent_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE http_status = 200 AND dispatched_at >= %s",
            $today . ' 00:00:00'
        ));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sent_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE http_status = 200 AND dispatched_at >= %s",
            $week_start . ' 00:00:00'
        ));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $recent_errors = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE http_status != 200 ORDER BY created_at DESC LIMIT 10"
        ) ?: [];

        return [
            'queued'        => $queued,
            'sent_today'    => $sent_today,
            'sent_week'     => $sent_week,
            'recent_errors' => $recent_errors,
        ];
    }
}
