<?php
/**
 * Plugin Name: Apotheca Marketing Sync
 * Plugin URI:  https://apothecamarketing.com
 * Description: Pushes WooCommerce events to the Apotheca Marketing Suite on the marketing subdomain. Requires Apotheca Marketing Suite to be installed on the subdomain.
 * Version:     1.0.0
 * Author:      Apotheca®
 * Author URI:  https://apothecamarketing.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: apotheca-marketing-sync
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 *
 * @package Apotheca\MarketingSync
 */

declare(strict_types=1);

namespace Apotheca\MarketingSync;

defined('ABSPATH') || exit;

define('AMS_SYNC_VERSION', '1.0.0');
define('AMS_SYNC_FILE', __FILE__);
define('AMS_SYNC_DIR', plugin_dir_path(__FILE__));
define('AMS_SYNC_URL', plugin_dir_url(__FILE__));
define('AMS_SYNC_BASENAME', plugin_basename(__FILE__));
define('AMS_SYNC_SETTINGS_KEY', 'ams_sync_settings');

/**
 * PSR-4 Autoloader for Apotheca\MarketingSync namespace.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Apotheca\\MarketingSync\\';
    $base_dir = AMS_SYNC_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Activation hook — create sync log table.
 */
register_activation_hook(__FILE__, function (): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    $sql = "CREATE TABLE {$prefix}ams_sync_log (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_type varchar(50) NOT NULL,
        payload_hash varchar(16) DEFAULT '' NOT NULL,
        http_status smallint(3) unsigned DEFAULT 0 NOT NULL,
        attempt_number tinyint(2) unsigned DEFAULT 1 NOT NULL,
        response_body varchar(500) DEFAULT '' NOT NULL,
        dispatched_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY event_type (event_type),
        KEY dispatched_at (dispatched_at),
        KEY http_status (http_status)
    ) $charset_collate;";

    dbDelta($sql);

    // Set defaults.
    if (false === get_option(AMS_SYNC_SETTINGS_KEY)) {
        update_option(AMS_SYNC_SETTINGS_KEY, Settings::defaults());
    }
});

/**
 * Deactivation hook.
 */
register_deactivation_hook(__FILE__, function (): void {
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('ams_sync_dispatch');
    }
});

/**
 * Uninstall is handled inline since this is a small plugin.
 */
register_uninstall_hook(__FILE__, [__NAMESPACE__ . '\\Plugin', 'uninstall']);

/**
 * Main plugin class.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Settings page.
        new SettingsPage();

        // Event collectors.
        new EventCollector();

        // Product view beacon.
        new ProductViewBeacon();

        // Dispatcher (Action Scheduler handler).
        new Dispatcher();

        // SSO admin toolbar link.
        new SSOGenerator();
    }

    /**
     * Uninstall cleanup.
     */
    public static function uninstall(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ams_sync_log");
        delete_option(AMS_SYNC_SETTINGS_KEY);
    }
}

/**
 * Boot the plugin.
 */
add_action('plugins_loaded', function (): void {
    Plugin::instance();
}, 10);
