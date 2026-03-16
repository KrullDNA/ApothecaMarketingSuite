<?php
/**
 * Form front-end loader.
 *
 * Conditionally enqueues the ams-forms.js bundle only when at least one
 * active form exists. Passes page context and cart value to the script.
 *
 * @package Apotheca\Marketing\Forms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Forms;

defined('ABSPATH') || exit;

final class FormLoader
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue'], 20);
        add_shortcode('ams_form', [$this, 'shortcode']);
    }

    /**
     * Conditionally enqueue the front-end forms bundle.
     */
    public function maybe_enqueue(): void
    {
        $repo = new FormRepository();
        $active = $repo->get_active();

        if (empty($active)) {
            return;
        }

        wp_enqueue_script(
            'ams-forms',
            AMS_PLUGIN_URL . 'assets/js/ams-forms.js',
            [],
            AMS_VERSION,
            true
        );

        $page_id = get_queried_object_id();
        $cart_total = 0;
        if (function_exists('WC') && WC()->cart) {
            $cart_total = (float) WC()->cart->get_cart_contents_total();
        }

        wp_localize_script('ams-forms', 'amsFormsConfig', [
            'restUrl'   => esc_url_raw(rest_url('ams/v1')),
            'pageId'    => $page_id,
            'cartTotal' => $cart_total,
        ]);
    }

    /**
     * Shortcode: [ams_form id="123"]
     */
    public function shortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'ams_form');
        $form_id = (int) $atts['id'];

        if ($form_id <= 0) {
            return '';
        }

        return '<div data-ams-embed="' . esc_attr($form_id) . '"></div>';
    }
}
