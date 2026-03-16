<?php
/**
 * REST API controller for forms.
 *
 * Admin CRUD endpoints and a public endpoint that returns active forms
 * matching the current page targeting.
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Forms\FormRepository;
use Apotheca\Marketing\Forms\FormSubmissionHandler;
use Apotheca\Marketing\Forms\SpinToWinHandler;
use Apotheca\Marketing\Forms\TargetingEvaluator;
use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class FormsController
{
    private const NAMESPACE = 'ams/v1';
    private FormRepository $repo;

    public function __construct()
    {
        $this->repo = new FormRepository();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        // Admin CRUD routes.
        register_rest_route(self::NAMESPACE, '/forms', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_forms'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_form'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_form'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_form'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_form'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        // Public endpoint: active forms for a page.
        register_rest_route(self::NAMESPACE, '/forms/active', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_active_forms'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Public endpoint: form submission.
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/submit', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'submit_form'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Public endpoint: record a view.
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/view', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'record_view'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Public endpoint: spin-to-win result.
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/spin', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'spin_result'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function check_admin_permission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /* ── Admin CRUD ── */

    public function list_forms(\WP_REST_Request $request): \WP_REST_Response
    {
        $args = [];
        if ($request->get_param('status')) {
            $args['status'] = $request->get_param('status');
        }
        if ($request->get_param('type')) {
            $args['type'] = $request->get_param('type');
        }
        return new \WP_REST_Response($this->repo->list($args), 200);
    }

    public function create_form(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        if (empty($data['name'])) {
            return new \WP_REST_Response(['message' => 'Form name is required.'], 400);
        }
        $id = $this->repo->create($data);
        return new \WP_REST_Response($this->repo->find($id), 201);
    }

    public function get_form(\WP_REST_Request $request): \WP_REST_Response
    {
        $form = $this->repo->find((int) $request->get_param('id'));
        if (!$form) {
            return new \WP_REST_Response(['message' => 'Form not found.'], 404);
        }
        return new \WP_REST_Response($form, 200);
    }

    public function update_form(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if (!$this->repo->find($id)) {
            return new \WP_REST_Response(['message' => 'Form not found.'], 404);
        }
        $this->repo->update($id, $request->get_json_params());
        return new \WP_REST_Response($this->repo->find($id), 200);
    }

    public function delete_form(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if (!$this->repo->find($id)) {
            return new \WP_REST_Response(['message' => 'Form not found.'], 404);
        }
        $this->repo->delete($id);
        return new \WP_REST_Response(['message' => 'Form deleted.'], 200);
    }

    /* ── Public Endpoints ── */

    /**
     * GET /ams/v1/forms/active?page_id=X
     *
     * Returns forms matching the current page's targeting, with only the
     * data needed for front-end rendering (no admin-only config).
     */
    public function get_active_forms(\WP_REST_Request $request): \WP_REST_Response
    {
        $page_id = (int) ($request->get_param('page_id') ?? 0);

        // Identify subscriber from cookie if present.
        $subscriber_id = 0;
        $token = sanitize_text_field($_COOKIE['ams_subscriber_token'] ?? '');
        if (!empty($token)) {
            $sub_repo = new SubscriberRepository();
            $sub = $sub_repo->find_by_token($token);
            if ($sub) {
                $subscriber_id = (int) $sub->id;
            }
        }

        $is_mobile = wp_is_mobile();

        $context = [
            'page_id'       => $page_id,
            'is_mobile'     => $is_mobile,
            'subscriber_id' => $subscriber_id,
        ];

        $evaluator = new TargetingEvaluator();
        $active_forms = $this->repo->get_active();
        $matched = $evaluator->filter($active_forms, $context);

        $output = [];
        foreach ($matched as $form) {
            $output[] = [
                'id'              => (int) $form->id,
                'type'            => $form->type,
                'fields'          => json_decode($form->fields ?: '[]', true),
                'design'          => json_decode($form->design_config ?: '{}', true),
                'success'         => json_decode($form->success_config ?: '{}', true),
                'triggers'        => $evaluator->get_client_triggers($form),
                'spin_config'     => $form->type === 'spin_to_win'
                    ? $this->get_public_spin_config($form)
                    : null,
            ];
        }

        return new \WP_REST_Response($output, 200);
    }

    /**
     * POST /ams/v1/forms/{id}/submit
     */
    public function submit_form(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('id');
        $data = $request->get_json_params();

        // Verify nonce for logged-in users; public forms use rate limiting.
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        $handler = new FormSubmissionHandler();
        $result = $handler->handle($form_id, $data, $ip);

        $status = $result['success'] ? 200 : 400;
        return new \WP_REST_Response($result, $status);
    }

    /**
     * POST /ams/v1/forms/{id}/view
     */
    public function record_view(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('id');
        $form = $this->repo->find($form_id);
        if ($form && $form->status === 'active') {
            $this->repo->increment_views($form_id);
        }
        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * POST /ams/v1/forms/{id}/spin
     *
     * Server-side spin result after email is submitted.
     */
    public function spin_result(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('id');
        $form = $this->repo->find($form_id);

        if (!$form || $form->type !== 'spin_to_win') {
            return new \WP_REST_Response(['message' => 'Invalid spin form.'], 400);
        }

        $data = $request->get_json_params();
        $email = sanitize_email($data['email'] ?? '');
        if (empty($email)) {
            return new \WP_REST_Response(['message' => 'Email is required.'], 400);
        }

        $spin_handler = new SpinToWinHandler();
        $result = $spin_handler->process_spin($form, $email);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Get spin config for public display (segment labels + colours only, no probabilities).
     */
    private function get_public_spin_config(object $form): array
    {
        $config = json_decode($form->spin_config ?: '{}', true) ?: [];
        $segments = $config['segments'] ?? [];

        $public_segments = [];
        foreach ($segments as $seg) {
            $public_segments[] = [
                'label' => $seg['label'] ?? '',
                'color' => $seg['color'] ?? '#4A90D9',
            ];
        }

        return ['segments' => $public_segments];
    }
}
