<?php
/**
 * Admin asset loader — enqueues JS/CSS only on pages that need them.
 *
 * @package Apotheca\Marketing\Admin
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Admin;

defined('ABSPATH') || exit;

final class Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Conditionally enqueue admin assets based on the current page.
     */
    public function enqueue(string $hook): void
    {
        // Only load on AMS admin pages.
        $screen = get_current_screen();
        if (!$screen || !str_contains($screen->id, 'ams-')) {
            return;
        }

        // Flow builder — only on the Flows page.
        if (str_contains($screen->id, 'ams-flows')) {
            $this->enqueue_flow_builder();
        }

        // Segment builder — only on the Segments page.
        if (str_contains($screen->id, 'ams-segments')) {
            $this->enqueue_segment_builder();
        }

        // Form builder — only on the Forms page.
        if (str_contains($screen->id, 'ams-forms')) {
            $this->enqueue_form_builder();
        }

        // SMS campaign manager — only on the SMS page.
        if (str_contains($screen->id, 'ams-sms')) {
            $this->enqueue_sms_campaign();
        }
    }

    /**
     * Enqueue the SMS campaign manager bundle.
     */
    private function enqueue_sms_campaign(): void
    {
        wp_enqueue_script(
            'ams-sms-campaign',
            AMS_PLUGIN_URL . 'assets/js/sms-campaign.js',
            ['wp-element', 'wp-api-fetch'],
            AMS_VERSION,
            true
        );

        wp_localize_script('ams-sms-campaign', 'amsSmsCampaign', [
            'restUrl' => rest_url('ams/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Enqueue the React form builder bundle.
     */
    private function enqueue_form_builder(): void
    {
        wp_enqueue_script(
            'ams-form-builder',
            AMS_PLUGIN_URL . 'assets/js/form-builder.js',
            ['wp-element', 'wp-api-fetch'],
            AMS_VERSION,
            true
        );

        wp_localize_script('ams-form-builder', 'amsFormBuilder', [
            'restUrl' => rest_url('ams/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Enqueue the React segment builder bundle.
     */
    private function enqueue_segment_builder(): void
    {
        wp_enqueue_script(
            'ams-segment-builder',
            AMS_PLUGIN_URL . 'assets/js/segment-builder.js',
            ['wp-element', 'wp-api-fetch'],
            AMS_VERSION,
            true
        );

        wp_localize_script('ams-segment-builder', 'amsSegmentBuilder', [
            'restUrl' => rest_url('ams/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Enqueue the React flow builder bundle.
     */
    private function enqueue_flow_builder(): void
    {
        // WordPress bundled React + API Fetch dependencies.
        wp_enqueue_script(
            'ams-flow-builder',
            AMS_PLUGIN_URL . 'assets/js/flow-builder.js',
            ['wp-element', 'wp-api-fetch'],
            AMS_VERSION,
            true
        );

        // Pass nonce and REST URL to the script.
        wp_localize_script('ams-flow-builder', 'amsFlowBuilder', [
            'restUrl' => rest_url('ams/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }
}
