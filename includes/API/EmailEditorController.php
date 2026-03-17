<?php
/**
 * REST API controller for the visual email template editor.
 *
 * Endpoints:
 * - GET    /ams/v1/email-templates           — list templates
 * - GET    /ams/v1/email-templates/(?P<id>\d+) — get single template
 * - POST   /ams/v1/email-templates           — create template
 * - PUT    /ams/v1/email-templates/(?P<id>\d+) — update template
 * - DELETE /ams/v1/email-templates/(?P<id>\d+) — delete template
 * - POST   /ams/v1/email-templates/(?P<id>\d+)/preview — render preview HTML
 * - POST   /ams/v1/email-templates/render    — render blocks to final HTML
 * - GET    /ams/v1/email-templates/products   — search WooCommerce products
 * - POST   /ams/v1/email-templates/(?P<id>\d+)/duplicate — duplicate a template
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Email\EmailRenderer;

defined('ABSPATH') || exit;

final class EmailEditorController
{
    private string $namespace = 'ams/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        // List templates.
        register_rest_route($this->namespace, '/email-templates', [
            'methods'             => 'GET',
            'callback'            => [$this, 'list_templates'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Create template.
        register_rest_route($this->namespace, '/email-templates', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_template'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Get single template.
        register_rest_route($this->namespace, '/email-templates/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_template'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Update template.
        register_rest_route($this->namespace, '/email-templates/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_template'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Delete template.
        register_rest_route($this->namespace, '/email-templates/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_template'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Preview (render template to HTML).
        register_rest_route($this->namespace, '/email-templates/(?P<id>\d+)/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'preview_template'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Render arbitrary blocks to HTML (for live preview).
        register_rest_route($this->namespace, '/email-templates/render', [
            'methods'             => 'POST',
            'callback'            => [$this, 'render_blocks'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Product search (for product block picker).
        register_rest_route($this->namespace, '/email-templates/products', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search_products'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Duplicate template.
        register_rest_route($this->namespace, '/email-templates/(?P<id>\d+)/duplicate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'duplicate_template'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /**
     * List all email templates.
     */
    public function list_templates(\WP_REST_Request $request): \WP_REST_Response
    {
        $templates = get_option('ams_email_templates', []);

        $result = [];
        foreach ($templates as $id => $tpl) {
            $result[] = [
                'id'         => $id,
                'name'       => $tpl['name'] ?? '',
                'updated_at' => $tpl['updated_at'] ?? '',
                'created_at' => $tpl['created_at'] ?? '',
            ];
        }

        // Sort by updated_at descending.
        usort($result, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Get a single template.
     */
    public function get_template(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $templates = get_option('ams_email_templates', []);

        if (!isset($templates[$id])) {
            return new \WP_REST_Response(['error' => 'Template not found.'], 404);
        }

        return new \WP_REST_Response(array_merge(['id' => $id], $templates[$id]), 200);
    }

    /**
     * Create a new template.
     */
    public function create_template(\WP_REST_Request $request): \WP_REST_Response
    {
        $templates = get_option('ams_email_templates', []);
        $id = ((int) max(array_keys($templates) ?: [0])) + 1;

        $now = current_time('mysql');

        $templates[$id] = [
            'name'         => sanitize_text_field($request->get_param('name') ?? 'Untitled Template'),
            'blocks'       => $request->get_param('blocks') ?? [],
            'global_style' => $request->get_param('global_style') ?? [],
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        update_option('ams_email_templates', $templates);

        return new \WP_REST_Response(array_merge(['id' => $id], $templates[$id]), 201);
    }

    /**
     * Update an existing template.
     */
    public function update_template(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $templates = get_option('ams_email_templates', []);

        if (!isset($templates[$id])) {
            return new \WP_REST_Response(['error' => 'Template not found.'], 404);
        }

        if ($request->has_param('name')) {
            $templates[$id]['name'] = sanitize_text_field($request->get_param('name'));
        }

        if ($request->has_param('blocks')) {
            $templates[$id]['blocks'] = $request->get_param('blocks');
        }

        if ($request->has_param('global_style')) {
            $templates[$id]['global_style'] = $request->get_param('global_style');
        }

        $templates[$id]['updated_at'] = current_time('mysql');

        update_option('ams_email_templates', $templates);

        return new \WP_REST_Response(array_merge(['id' => $id], $templates[$id]), 200);
    }

    /**
     * Delete a template.
     */
    public function delete_template(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $templates = get_option('ams_email_templates', []);

        if (!isset($templates[$id])) {
            return new \WP_REST_Response(['error' => 'Template not found.'], 404);
        }

        unset($templates[$id]);
        update_option('ams_email_templates', $templates);

        return new \WP_REST_Response(['deleted' => true], 200);
    }

    /**
     * Preview a saved template — render its blocks to HTML.
     */
    public function preview_template(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $templates = get_option('ams_email_templates', []);

        if (!isset($templates[$id])) {
            return new \WP_REST_Response(['error' => 'Template not found.'], 404);
        }

        $renderer = new EmailRenderer();
        $html = $renderer->render(
            $templates[$id]['blocks'] ?? [],
            $templates[$id]['global_style'] ?? []
        );

        return new \WP_REST_Response(['html' => $html], 200);
    }

    /**
     * Render arbitrary blocks to HTML (for live preview in editor).
     */
    public function render_blocks(\WP_REST_Request $request): \WP_REST_Response
    {
        $blocks       = $request->get_param('blocks') ?? [];
        $global_style = $request->get_param('global_style') ?? [];

        $renderer = new EmailRenderer();
        $html = $renderer->render($blocks, $global_style);

        return new \WP_REST_Response(['html' => $html], 200);
    }

    /**
     * Search WooCommerce products (for product block picker).
     */
    public function search_products(\WP_REST_Request $request): \WP_REST_Response
    {
        $search = sanitize_text_field($request->get_param('search') ?? '');
        $limit  = min(20, (int) ($request->get_param('per_page') ?? 10));

        $args = [
            'status'  => 'publish',
            'limit'   => $limit,
            'orderby' => 'name',
            'order'   => 'ASC',
        ];

        if ($search) {
            $args['s'] = $search;
        }

        if (!function_exists('wc_get_products')) {
            return new \WP_REST_Response([], 200);
        }

        $products = wc_get_products($args);
        $result   = [];

        foreach ($products as $product) {
            $result[] = [
                'id'    => $product->get_id(),
                'name'  => $product->get_name(),
                'price' => $product->get_price_html(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: '',
            ];
        }

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate_template(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $templates = get_option('ams_email_templates', []);

        if (!isset($templates[$id])) {
            return new \WP_REST_Response(['error' => 'Template not found.'], 404);
        }

        $new_id = ((int) max(array_keys($templates) ?: [0])) + 1;
        $now = current_time('mysql');

        $templates[$new_id] = [
            'name'         => $templates[$id]['name'] . ' (Copy)',
            'blocks'       => $templates[$id]['blocks'],
            'global_style' => $templates[$id]['global_style'],
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        update_option('ams_email_templates', $templates);

        return new \WP_REST_Response(array_merge(['id' => $new_id], $templates[$new_id]), 201);
    }
}
