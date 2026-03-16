<?php
/**
 * REST API controller for flows and flow steps.
 *
 * Provides CRUD endpoints for the React flow builder.
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Flows\FlowRepository;

defined('ABSPATH') || exit;

final class FlowsController
{
    private const NAMESPACE = 'ams/v1';
    private FlowRepository $repo;

    public function __construct()
    {
        $this->repo = new FlowRepository();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/flows', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_flows'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_flow'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_flow'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_flow'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_flow'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>\d+)/steps', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_steps'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save_steps'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/templates', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_templates'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/templates/import', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'import_template'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Permission check — requires manage_woocommerce capability.
     */
    public function check_permission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /**
     * GET /ams/v1/flows
     */
    public function list_flows(\WP_REST_Request $request): \WP_REST_Response
    {
        $args = [];
        if ($request->get_param('status')) {
            $args['status'] = $request->get_param('status');
        }
        if ($request->get_param('trigger_type')) {
            $args['trigger_type'] = $request->get_param('trigger_type');
        }

        $flows = $this->repo->list($args);

        // Attach step counts.
        foreach ($flows as &$flow) {
            $steps = $this->repo->get_steps((int) $flow->id);
            $flow->step_count = count($steps);
        }

        return new \WP_REST_Response($flows, 200);
    }

    /**
     * POST /ams/v1/flows
     */
    public function create_flow(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();

        if (empty($data['name'])) {
            return new \WP_REST_Response(['message' => 'Flow name is required.'], 400);
        }

        $id = $this->repo->create($data);

        if (!empty($data['steps']) && is_array($data['steps'])) {
            $this->repo->save_steps($id, $data['steps']);
        }

        $flow = $this->repo->find($id);
        $flow->steps = $this->repo->get_steps($id);

        return new \WP_REST_Response($flow, 201);
    }

    /**
     * GET /ams/v1/flows/{id}
     */
    public function get_flow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $flow = $this->repo->find($id);

        if (!$flow) {
            return new \WP_REST_Response(['message' => 'Flow not found.'], 404);
        }

        $flow->steps = $this->repo->get_steps($id);

        return new \WP_REST_Response($flow, 200);
    }

    /**
     * PUT/PATCH /ams/v1/flows/{id}
     */
    public function update_flow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $flow = $this->repo->find($id);

        if (!$flow) {
            return new \WP_REST_Response(['message' => 'Flow not found.'], 404);
        }

        $data = $request->get_json_params();
        $this->repo->update($id, $data);

        if (isset($data['steps']) && is_array($data['steps'])) {
            $this->repo->save_steps($id, $data['steps']);
        }

        $updated = $this->repo->find($id);
        $updated->steps = $this->repo->get_steps($id);

        return new \WP_REST_Response($updated, 200);
    }

    /**
     * DELETE /ams/v1/flows/{id}
     */
    public function delete_flow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $flow = $this->repo->find($id);

        if (!$flow) {
            return new \WP_REST_Response(['message' => 'Flow not found.'], 404);
        }

        $this->repo->delete($id);

        return new \WP_REST_Response(['message' => 'Flow deleted.'], 200);
    }

    /**
     * GET /ams/v1/flows/{id}/steps
     */
    public function get_steps(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $steps = $this->repo->get_steps($id);

        return new \WP_REST_Response($steps, 200);
    }

    /**
     * POST /ams/v1/flows/{id}/steps
     */
    public function save_steps(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $flow = $this->repo->find($id);

        if (!$flow) {
            return new \WP_REST_Response(['message' => 'Flow not found.'], 404);
        }

        $steps = $request->get_json_params();
        if (!is_array($steps)) {
            return new \WP_REST_Response(['message' => 'Steps must be an array.'], 400);
        }

        $this->repo->save_steps($id, $steps);
        $saved = $this->repo->get_steps($id);

        return new \WP_REST_Response($saved, 200);
    }

    /**
     * GET /ams/v1/flows/templates — list available templates.
     */
    public function list_templates(\WP_REST_Request $request): \WP_REST_Response
    {
        $templates_dir = AMS_PLUGIN_DIR . 'templates/flows/';
        $templates = [];

        if (!is_dir($templates_dir)) {
            return new \WP_REST_Response($templates, 200);
        }

        $files = glob($templates_dir . '*.json');
        foreach ($files ?: [] as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $templates[] = [
                    'slug'         => basename($file, '.json'),
                    'name'         => $data['name'] ?? basename($file, '.json'),
                    'description'  => $data['description'] ?? '',
                    'trigger_type' => $data['trigger_type'] ?? '',
                    'step_count'   => count($data['steps'] ?? []),
                ];
            }
        }

        return new \WP_REST_Response($templates, 200);
    }

    /**
     * POST /ams/v1/flows/templates/import — import a template as a new flow.
     */
    public function import_template(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = sanitize_file_name($request->get_param('slug') ?? '');

        if (empty($slug)) {
            return new \WP_REST_Response(['message' => 'Template slug is required.'], 400);
        }

        $file = AMS_PLUGIN_DIR . 'templates/flows/' . $slug . '.json';
        if (!file_exists($file)) {
            return new \WP_REST_Response(['message' => 'Template not found.'], 404);
        }

        $template = json_decode(file_get_contents($file), true);
        if (!$template) {
            return new \WP_REST_Response(['message' => 'Invalid template file.'], 500);
        }

        $flow_id = $this->repo->create([
            'name'           => $template['name'] ?? 'Imported Flow',
            'trigger_type'   => $template['trigger_type'] ?? '',
            'trigger_config' => $template['trigger_config'] ?? [],
            'status'         => 'draft',
        ]);

        if (!empty($template['steps']) && is_array($template['steps'])) {
            $this->repo->save_steps($flow_id, $template['steps']);
        }

        $flow = $this->repo->find($flow_id);
        $flow->steps = $this->repo->get_steps($flow_id);

        return new \WP_REST_Response($flow, 201);
    }
}
