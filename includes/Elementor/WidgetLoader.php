<?php
/**
 * Elementor widget loader — registers AMS widgets with Elementor.
 *
 * @package Apotheca\Marketing\Elementor
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Elementor;

defined('ABSPATH') || exit;

final class WidgetLoader
{
    public function __construct()
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_frontend_styles']);
    }

    /**
     * Register a custom Elementor category for AMS widgets.
     */
    public function register_category(\Elementor\Elements_Manager $elements_manager): void
    {
        $elements_manager->add_category('apotheca-marketing', [
            'title' => esc_html__('Apotheca® Marketing', 'apotheca-marketing-suite'),
            'icon'  => 'eicon-mail',
        ]);
    }

    /**
     * Register all AMS Elementor widgets.
     */
    public function register_widgets(\Elementor\Widgets_Manager $widgets_manager): void
    {
        $widgets_manager->register(new Widgets\OptInForm());
        $widgets_manager->register(new Widgets\SubscriberCountBadge());
        $widgets_manager->register(new Widgets\CampaignArchive());
        $widgets_manager->register(new Widgets\PreferenceCentre());
    }

    /**
     * Enqueue front-end widget styles.
     */
    public function enqueue_frontend_styles(): void
    {
        wp_enqueue_style(
            'ams-elementor-widgets',
            AMS_PLUGIN_URL . 'assets/css/elementor-widgets.css',
            [],
            AMS_VERSION
        );
    }
}
