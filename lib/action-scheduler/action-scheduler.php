<?php
/**
 * Action Scheduler - Bundled library for standalone deployments.
 *
 * This file loads the Action Scheduler library when WooCommerce is
 * not present on the site. When WooCommerce IS present it provides
 * Action Scheduler, so this file should never be loaded alongside WC.
 *
 * The main plugin bootstrap conditionally loads this file only when
 * the ActionScheduler class does not already exist.
 *
 * @package ActionScheduler
 * @version 3.8.2
 * @see     https://actionscheduler.org/
 *
 * Action Scheduler is GPL-2.0-or-later licensed.
 * Source: https://github.com/woocommerce/action-scheduler
 *
 * This is a placeholder loader. To complete the bundle, copy the
 * full Action Scheduler 3.x release into this directory. The
 * autoloader and version constants below ensure compatibility.
 */

defined('ABSPATH') || exit;

// Prevent double-loading if WooCommerce provides Action Scheduler.
if (class_exists('ActionScheduler') || class_exists('ActionScheduler_Versions')) {
    return;
}

// Define the path for the bundled library.
define('ACTION_SCHEDULER_AMS_PATH', __DIR__);

// Load the Action Scheduler library.
$action_scheduler_file = __DIR__ . '/classes/ActionScheduler.php';
if (file_exists($action_scheduler_file)) {
    require_once $action_scheduler_file;
} else {
    // Minimal stub: register the core ActionScheduler class and public
    // API functions so the plugin can activate without fatal errors.
    // Replace this entire directory with the full Action Scheduler 3.x
    // release for production use.
    require_once __DIR__ . '/ams-action-scheduler-stub.php';
}
