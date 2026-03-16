<?php
/**
 * Admin menu registration.
 *
 * @package Apotheca\Marketing\Admin
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Admin;

defined('ABSPATH') || exit;

final class Menu
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register']);
    }

    /**
     * Register admin menu and submenu pages.
     */
    public function register(): void
    {
        $capability = 'manage_woocommerce';

        // Top-level menu.
        add_menu_page(
            __('Apotheca® Marketing', 'apotheca-marketing-suite'),
            __('Apotheca® Marketing', 'apotheca-marketing-suite'),
            $capability,
            'ams-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-email-alt',
            56
        );

        // Submenu pages.
        $submenus = [
            [
                'slug'     => 'ams-dashboard',
                'title'    => __('Dashboard', 'apotheca-marketing-suite'),
                'callback' => 'render_dashboard',
            ],
            [
                'slug'     => 'ams-subscribers',
                'title'    => __('Subscribers', 'apotheca-marketing-suite'),
                'callback' => 'render_subscribers',
            ],
            [
                'slug'     => 'ams-flows',
                'title'    => __('Flows', 'apotheca-marketing-suite'),
                'callback' => 'render_flows',
            ],
            [
                'slug'     => 'ams-campaigns',
                'title'    => __('Campaigns', 'apotheca-marketing-suite'),
                'callback' => 'render_campaigns',
            ],
            [
                'slug'     => 'ams-segments',
                'title'    => __('Segments', 'apotheca-marketing-suite'),
                'callback' => 'render_segments',
            ],
            [
                'slug'     => 'ams-forms',
                'title'    => __('Forms', 'apotheca-marketing-suite'),
                'callback' => 'render_forms',
            ],
            [
                'slug'     => 'ams-sms',
                'title'    => __('SMS', 'apotheca-marketing-suite'),
                'callback' => 'render_sms',
            ],
            [
                'slug'     => 'ams-analytics',
                'title'    => __('Analytics', 'apotheca-marketing-suite'),
                'callback' => 'render_analytics',
            ],
            [
                'slug'     => 'ams-ai-settings',
                'title'    => __('AI Settings', 'apotheca-marketing-suite'),
                'callback' => 'render_ai_settings',
            ],
            [
                'slug'     => 'ams-email-editor',
                'title'    => __('Email Editor', 'apotheca-marketing-suite'),
                'callback' => 'render_email_editor',
            ],
            [
                'slug'     => 'ams-reviews',
                'title'    => __('Reviews', 'apotheca-marketing-suite'),
                'callback' => 'render_reviews',
            ],
            [
                'slug'     => 'ams-sync',
                'title'    => __('Sync', 'apotheca-marketing-suite'),
                'callback' => 'render_sync',
            ],
            [
                'slug'     => 'ams-settings',
                'title'    => __('Settings', 'apotheca-marketing-suite'),
                'callback' => 'render_settings',
            ],
        ];

        foreach ($submenus as $submenu) {
            add_submenu_page(
                'ams-dashboard',
                $submenu['title'],
                $submenu['title'],
                $capability,
                $submenu['slug'],
                [$this, $submenu['callback']]
            );
        }
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard(): void
    {
        $this->render_page_wrapper('Dashboard', 'dashboard');
    }

    public function render_subscribers(): void
    {
        $this->render_page_wrapper('Subscribers', 'subscribers');
    }

    public function render_flows(): void
    {
        $this->render_page_wrapper('Flows', 'flows');
    }

    public function render_campaigns(): void
    {
        $this->render_page_wrapper('Campaigns', 'campaigns');
    }

    public function render_segments(): void
    {
        $this->render_page_wrapper('Segments', 'segments');
    }

    public function render_forms(): void
    {
        $this->render_page_wrapper('Forms', 'forms');
    }

    public function render_sms(): void
    {
        $this->render_page_wrapper('SMS', 'sms');
    }

    public function render_analytics(): void
    {
        $this->render_page_wrapper('Analytics', 'analytics');
    }

    public function render_ai_settings(): void
    {
        $this->render_page_wrapper('AI Settings', 'ai-settings');
    }

    public function render_reviews(): void
    {
        $this->render_page_wrapper('Reviews', 'reviews-settings');
    }

    public function render_email_editor(): void
    {
        $this->render_page_wrapper('Email Editor', 'email-editor');
    }

    public function render_sync(): void
    {
        $this->render_page_wrapper('Sync', 'sync-settings');
    }

    public function render_settings(): void
    {
        $this->render_page_wrapper('Settings', 'settings');
    }

    /**
     * Render a page with the standard wrapper.
     */
    private function render_page_wrapper(string $title, string $page_id): void
    {
        $template = AMS_PLUGIN_DIR . "includes/Admin/views/{$page_id}.php";

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Apotheca® Marketing', 'apotheca-marketing-suite') . ' &mdash; ' . esc_html($title) . '</h1>';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div id="ams-admin-' . esc_attr($page_id) . '"></div>';
            echo '<p>' . esc_html__('This page will be built in a future session.', 'apotheca-marketing-suite') . '</p>';
        }

        echo '</div>';
    }
}
