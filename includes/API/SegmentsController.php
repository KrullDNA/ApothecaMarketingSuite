<?php
/**
 * REST API controller for segments.
 *
 * Provides CRUD endpoints and a live count preview for the React segment builder.
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Segments\SegmentRepository;
use Apotheca\Marketing\Segments\SegmentCalculator;

defined('ABSPATH') || exit;

final class SegmentsController
{
    private const NAMESPACE = 'ams/v1';
    private SegmentRepository $repo;
    private SegmentCalculator $calculator;

    public function __construct()
    {
        $this->repo = new SegmentRepository();
        $this->calculator = new SegmentCalculator();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/segments', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_segments'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_segment'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/segments/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_segment'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_segment'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_segment'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/segments/preview', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'preview_count'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/segments/condition-types', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_condition_types'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    public function check_permission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /**
     * GET /ams/v1/segments
     */
    public function list_segments(\WP_REST_Request $request): \WP_REST_Response
    {
        $segments = $this->repo->list();
        return new \WP_REST_Response($segments, 200);
    }

    /**
     * POST /ams/v1/segments
     */
    public function create_segment(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();

        if (empty($data['name'])) {
            return new \WP_REST_Response(['message' => 'Segment name is required.'], 400);
        }

        $id = $this->repo->create($data);

        // Calculate initial count.
        $segment = $this->repo->find($id);
        if ($segment) {
            $count = $this->calculator->calculate($segment);
            $this->repo->update_count($id, $count);
            $segment = $this->repo->find($id);
        }

        return new \WP_REST_Response($segment, 201);
    }

    /**
     * GET /ams/v1/segments/{id}
     */
    public function get_segment(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $segment = $this->repo->find($id);

        if (!$segment) {
            return new \WP_REST_Response(['message' => 'Segment not found.'], 404);
        }

        return new \WP_REST_Response($segment, 200);
    }

    /**
     * PUT/PATCH /ams/v1/segments/{id}
     */
    public function update_segment(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $segment = $this->repo->find($id);

        if (!$segment) {
            return new \WP_REST_Response(['message' => 'Segment not found.'], 404);
        }

        $data = $request->get_json_params();
        $this->repo->update($id, $data);

        // Recalculate count if conditions changed.
        if (array_key_exists('conditions', $data)) {
            $updated = $this->repo->find($id);
            $count = $this->calculator->calculate($updated);
            $this->repo->update_count($id, $count);
        }

        $updated = $this->repo->find($id);
        return new \WP_REST_Response($updated, 200);
    }

    /**
     * DELETE /ams/v1/segments/{id}
     */
    public function delete_segment(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $segment = $this->repo->find($id);

        if (!$segment) {
            return new \WP_REST_Response(['message' => 'Segment not found.'], 404);
        }

        $this->repo->delete($id);

        return new \WP_REST_Response(['message' => 'Segment deleted.'], 200);
    }

    /**
     * POST /ams/v1/segments/preview — live count preview.
     */
    public function preview_count(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $conditions = $data['conditions'] ?? [];

        if (empty($conditions)) {
            return new \WP_REST_Response(['count' => 0], 200);
        }

        $count = $this->calculator->count_matching_subscribers($conditions);

        return new \WP_REST_Response(['count' => $count], 200);
    }

    /**
     * GET /ams/v1/segments/condition-types — available condition types for the builder.
     */
    public function get_condition_types(\WP_REST_Request $request): \WP_REST_Response
    {
        $types = [
            [
                'group' => 'Subscriber Data',
                'conditions' => [
                    ['type' => 'email_domain', 'label' => 'Email Domain', 'operators' => ['is', 'is_not', 'contains'], 'value_type' => 'text'],
                    ['type' => 'first_name', 'label' => 'First Name', 'operators' => ['is', 'is_not', 'is_blank', 'is_not_blank', 'contains'], 'value_type' => 'text'],
                    ['type' => 'tag', 'label' => 'Tag', 'operators' => ['has', 'does_not_have'], 'value_type' => 'text'],
                    ['type' => 'custom_field', 'label' => 'Custom Field', 'operators' => ['equals', 'not_equals', 'contains', 'greater_than', 'less_than'], 'value_type' => 'text', 'extra_fields' => ['field_name']],
                    ['type' => 'source', 'label' => 'Source', 'operators' => ['is', 'is_not'], 'value_type' => 'text'],
                    ['type' => 'gdpr_consent', 'label' => 'GDPR Consent', 'operators' => ['is_true', 'is_false'], 'value_type' => 'none'],
                    ['type' => 'subscribed_date', 'label' => 'Subscribed Date', 'operators' => ['before', 'after', 'within_last_X_days', 'more_than_X_days_ago'], 'value_type' => 'date'],
                    ['type' => 'predicted_clv', 'label' => 'Predicted CLV', 'operators' => ['greater_than', 'less_than', 'equals', 'between'], 'value_type' => 'number'],
                    ['type' => 'churn_risk_score', 'label' => 'Churn Risk Score', 'operators' => ['greater_than', 'less_than', 'equals'], 'value_type' => 'number'],
                    ['type' => 'rfm_segment', 'label' => 'RFM Segment', 'operators' => ['is', 'is_not'], 'value_type' => 'select', 'options' => ['Champions', 'Big Spenders', 'Loyal', 'New Customers', 'Potential', 'At Risk', 'About to Sleep', 'Lost', 'Other']],
                ],
            ],
            [
                'group' => 'Ecommerce',
                'conditions' => [
                    ['type' => 'total_orders', 'label' => 'Total Orders', 'operators' => ['greater_than', 'less_than', 'equals', 'between'], 'value_type' => 'number'],
                    ['type' => 'total_spent', 'label' => 'Total Spent', 'operators' => ['greater_than', 'less_than', 'equals', 'between'], 'value_type' => 'number'],
                    ['type' => 'average_order_value', 'label' => 'Average Order Value', 'operators' => ['greater_than', 'less_than', 'equals', 'between'], 'value_type' => 'number'],
                    ['type' => 'last_order_date', 'label' => 'Last Order Date', 'operators' => ['before', 'after', 'within_last_X_days', 'more_than_X_days_ago'], 'value_type' => 'date'],
                    ['type' => 'purchased_product', 'label' => 'Purchased Product', 'operators' => ['has', 'has_not'], 'value_type' => 'text'],
                    ['type' => 'purchased_category', 'label' => 'Purchased Category', 'operators' => ['has', 'has_not'], 'value_type' => 'text'],
                    ['type' => 'last_order_status', 'label' => 'Last Order Status', 'operators' => ['is', 'is_not'], 'value_type' => 'select', 'options' => ['completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed']],
                    ['type' => 'used_coupon', 'label' => 'Used Coupon', 'operators' => ['has', 'has_not'], 'value_type' => 'text'],
                ],
            ],
            [
                'group' => 'Engagement',
                'conditions' => [
                    ['type' => 'opened_campaign', 'label' => 'Opened Campaign', 'operators' => ['has', 'has_not'], 'value_type' => 'text'],
                    ['type' => 'clicked_campaign', 'label' => 'Clicked Campaign', 'operators' => ['has', 'has_not'], 'value_type' => 'text'],
                    ['type' => 'opened_any_email', 'label' => 'Opened Any Email', 'operators' => ['ever', 'never', 'within_last_X_days'], 'value_type' => 'number'],
                    ['type' => 'clicked_any_email', 'label' => 'Clicked Any Email', 'operators' => ['ever', 'never', 'within_last_X_days'], 'value_type' => 'number'],
                    ['type' => 'sms_opt_in', 'label' => 'SMS Opt-in', 'operators' => ['is_true', 'is_false'], 'value_type' => 'none'],
                    ['type' => 'email_bounce_status', 'label' => 'Email Bounce Status', 'operators' => ['is', 'is_not'], 'value_type' => 'select', 'options' => ['none', 'soft', 'hard']],
                ],
            ],
        ];

        return new \WP_REST_Response($types, 200);
    }
}
