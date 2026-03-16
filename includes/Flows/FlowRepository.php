<?php
/**
 * Flow repository — DB operations for ams_flows and ams_flow_steps.
 *
 * @package Apotheca\Marketing\Flows
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows;

defined('ABSPATH') || exit;

final class FlowRepository
{
    private string $flows_table;
    private string $steps_table;

    public function __construct()
    {
        global $wpdb;
        $this->flows_table = $wpdb->prefix . 'ams_flows';
        $this->steps_table = $wpdb->prefix . 'ams_flow_steps';
    }

    /**
     * Get a flow by ID.
     */
    public function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->flows_table} WHERE id = %d",
            $id
        ));
    }

    /**
     * Get all flows with optional filtering.
     *
     * @return object[]
     */
    public function list(array $args = []): array
    {
        global $wpdb;

        $where = '1=1';
        $values = [];

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $values[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['trigger_type'])) {
            $where .= ' AND trigger_type = %s';
            $values[] = sanitize_text_field($args['trigger_type']);
        }

        $sql = "SELECT * FROM {$this->flows_table} WHERE {$where} ORDER BY created_at DESC";
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Get all active flows for a specific trigger type.
     *
     * @return object[]
     */
    public function get_active_by_trigger(string $trigger_type): array
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->flows_table} WHERE trigger_type = %s AND status = 'active'",
            $trigger_type
        ));
        return $results ?: [];
    }

    /**
     * Create a new flow.
     */
    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);

        $wpdb->insert($this->flows_table, [
            'name'           => sanitize_text_field($data['name'] ?? ''),
            'trigger_type'   => sanitize_text_field($data['trigger_type'] ?? ''),
            'trigger_config' => wp_json_encode($data['trigger_config'] ?? []),
            'status'         => sanitize_text_field($data['status'] ?? 'draft'),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a flow.
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $update = ['updated_at' => current_time('mysql', true)];

        if (array_key_exists('name', $data)) {
            $update['name'] = sanitize_text_field($data['name']);
        }
        if (array_key_exists('trigger_type', $data)) {
            $update['trigger_type'] = sanitize_text_field($data['trigger_type']);
        }
        if (array_key_exists('trigger_config', $data)) {
            $update['trigger_config'] = wp_json_encode($data['trigger_config']);
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = sanitize_text_field($data['status']);
        }

        return (bool) $wpdb->update($this->flows_table, $update, ['id' => $id]);
    }

    /**
     * Delete a flow and its steps.
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        $wpdb->delete($this->steps_table, ['flow_id' => $id]);
        return (bool) $wpdb->delete($this->flows_table, ['id' => $id]);
    }

    /**
     * Get all steps for a flow, ordered.
     *
     * @return object[]
     */
    public function get_steps(int $flow_id): array
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->steps_table} WHERE flow_id = %d ORDER BY step_order ASC",
            $flow_id
        ));
        return $results ?: [];
    }

    /**
     * Save steps for a flow (replaces all existing steps).
     *
     * @param array[] $steps
     */
    public function save_steps(int $flow_id, array $steps): void
    {
        global $wpdb;

        // Remove existing steps.
        $wpdb->delete($this->steps_table, ['flow_id' => $flow_id]);

        $now = current_time('mysql', true);

        foreach ($steps as $order => $step) {
            $wpdb->insert($this->steps_table, [
                'flow_id'      => $flow_id,
                'step_type'    => sanitize_text_field($step['step_type'] ?? ''),
                'step_order'   => (int) ($step['step_order'] ?? $order),
                'delay_value'  => (int) ($step['delay_value'] ?? 0),
                'delay_unit'   => sanitize_text_field($step['delay_unit'] ?? 'minutes'),
                'subject'      => sanitize_text_field($step['subject'] ?? ''),
                'preview_text' => sanitize_text_field($step['preview_text'] ?? ''),
                'body_html'    => wp_kses_post($step['body_html'] ?? ''),
                'body_text'    => sanitize_textarea_field($step['body_text'] ?? ''),
                'sms_body'     => sanitize_textarea_field($step['sms_body'] ?? ''),
                'conditions'   => wp_json_encode($step['conditions'] ?? []),
                'created_at'   => $now,
            ]);
        }
    }
}
