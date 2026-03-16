<?php
/**
 * Plugin Name: Apotheca® Marketing Suite
 * Plugin URI:  https://apothecamarketing.com
 * Description: Premium WooCommerce email and SMS marketing automation suite with flows, segmentation, RFM scoring, pop-up forms, and analytics.
 * Version:     1.0.0
 * Author:      Apotheca®
 * Author URI:  https://apothecamarketing.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: apotheca-marketing-suite
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package Apotheca\Marketing
 */

declare(strict_types=1);

namespace Apotheca\Marketing;

defined('ABSPATH') || exit;

/**
 * Plugin constants.
 */
define('AMS_VERSION', '1.0.0');
define('AMS_PLUGIN_FILE', __FILE__);
define('AMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AMS_DB_VERSION', '1.1.0');
define('AMS_SETTINGS_KEY', 'ams_settings');

/**
 * PSR-4 Autoloader for Apotheca\Marketing namespace.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Apotheca\\Marketing\\';
    $base_dir = AMS_PLUGIN_DIR . 'includes/';

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
 * Activation hook — create DB tables and set default options.
 */
register_activation_hook(__FILE__, function (): void {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(AMS_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Apotheca® Marketing Suite requires WooCommerce to be installed and active.', 'apotheca-marketing-suite'),
            'Plugin dependency check',
            ['back_link' => true]
        );
    }

    $installer = new Database\Installer();
    $installer->install();

    // Set default settings if not already present.
    if (false === get_option(AMS_SETTINGS_KEY)) {
        update_option(AMS_SETTINGS_KEY, Settings::defaults());
    }

    update_option('ams_db_version', AMS_DB_VERSION);
    flush_rewrite_rules();
});

/**
 * Deactivation hook — clean up scheduled actions.
 */
register_deactivation_hook(__FILE__, function (): void {
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('ams_abandoned_cart_check');
        as_unschedule_all_actions('ams_rfm_nightly');
        as_unschedule_all_actions('ams_segment_recalculate');
        as_unschedule_all_actions('ams_flow_process_step');
        as_unschedule_all_actions('ams_flow_win_back_check');
        as_unschedule_all_actions('ams_flow_browse_abandon_check');
        as_unschedule_all_actions('ams_flow_birthday_check');
        as_unschedule_all_actions('ams_predictive_nightly');
    }

    flush_rewrite_rules();
});

/**
 * Uninstall hook is handled by uninstall.php.
 */

/**
 * Main plugin class — singleton.
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
        $this->check_dependencies();
        $this->init_hooks();
    }

    /**
     * Verify WooCommerce is active before initialising.
     */
    private function check_dependencies(): void
    {
        add_action('admin_init', function (): void {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', function (): void {
                    echo '<div class="notice notice-error"><p>';
                    echo esc_html__('Apotheca® Marketing Suite requires WooCommerce to be installed and active.', 'apotheca-marketing-suite');
                    echo '</p></div>';
                });
            }
        });
    }

    /**
     * Register all WordPress hooks.
     */
    private function init_hooks(): void
    {
        // Check for DB upgrades.
        add_action('plugins_loaded', [$this, 'maybe_upgrade_db']);

        // Initialise components after plugins are loaded.
        add_action('plugins_loaded', [$this, 'init_components'], 20);

        // Register rewrite rules.
        add_action('init', [$this, 'register_rewrite_rules']);

        // Handle unsubscribe endpoint.
        add_action('template_redirect', [$this, 'handle_unsubscribe_endpoint']);
    }

    /**
     * Run DB upgrade if version mismatch.
     */
    public function maybe_upgrade_db(): void
    {
        $installed_version = get_option('ams_db_version', '0');
        if (version_compare($installed_version, AMS_DB_VERSION, '<')) {
            $installer = new Database\Installer();
            $installer->install();
            update_option('ams_db_version', AMS_DB_VERSION);
        }
    }

    /**
     * Initialise all plugin components.
     */
    public function init_components(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Admin menu and assets.
        if (is_admin()) {
            new Admin\Menu();
            new Admin\Assets();
        }

        // Subscriber capture hooks.
        new Subscriber\CaptureHandler();

        // WooCommerce event tracking.
        new Events\EventTracker();

        // Abandoned cart detection.
        new Events\AbandonedCartDetector();

        // GDPR and unsubscribe handling.
        new GDPR\Handler();

        // Flow engine — triggers, execution, and scheduling.
        new Flows\FlowManager();

        // REST API controllers.
        new API\FlowsController();
        new API\SegmentsController();

        // Segmentation engine — background recalculation.
        new Segments\SegmentCalculator();

        // Analytics engines — nightly RFM and predictive scoring.
        new Analytics\RfmEngine();
        new Analytics\PredictiveEngine();
    }

    /**
     * Register rewrite rules for public endpoints.
     */
    public function register_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^ams-unsubscribe/?$',
            'index.php?ams_unsubscribe=1',
            'top'
        );
        add_filter('query_vars', function (array $vars): array {
            $vars[] = 'ams_unsubscribe';
            return $vars;
        });
    }

    /**
     * Handle the public unsubscribe endpoint.
     */
    public function handle_unsubscribe_endpoint(): void
    {
        if (!get_query_var('ams_unsubscribe')) {
            return;
        }

        $gdpr_handler = new GDPR\Handler();
        $gdpr_handler->process_unsubscribe();
        exit;
    }
}

/**
 * Boot the plugin.
 */
add_action('plugins_loaded', function (): void {
    Plugin::instance();
}, 10);
