<?php
/**
 * Flow enrolment repository — DB operations for ams_flow_enrolments.
 *
 * @package Apotheca\Marketing\Flows
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows;

defined('ABSPATH') || exit;

final class EnrolmentRepository
{
    private string $table;
    private string $flows_table;
    private string $steps_table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ams_flow_enrolments';
        $this->flows_table = $wpdb->prefix . 'ams_flows';
        $this->steps_table = $wpdb->prefix . 'ams_flow_steps';
    }

    /**
     * Find enrolment by ID.
     */
    public function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }

    /**
     * Check if subscriber is already active in a flow.
     */
    public function is_active_in_flow(int $flow_id, int $subscriber_id): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE flow_id = %d AND subscriber_id = %d AND status = 'active'",
            $flow_id,
            $subscriber_id
        ));
    }

    /**
     * Enrol subscriber in a flow. Returns enrolment ID or 0 if already enrolled.
     */
    public function enrol(int $flow_id, int $subscriber_id): int
    {
        global $wpdb;

        if ($this->is_active_in_flow($flow_id, $subscriber_id)) {
            return 0;
        }

        // Get the first step of the flow.
        $first_step = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->steps_table} WHERE flow_id = %d ORDER BY step_order ASC LIMIT 1",
            $flow_id
        ));

        $wpdb->insert($this->table, [
            'flow_id'         => $flow_id,
            'subscriber_id'   => $subscriber_id,
            'current_step_id' => $first_step ? (int) $first_step->id : null,
            'status'          => 'active',
            'enrolled_at'     => current_time('mysql', true),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Advance enrolment to the next step.
     */
    public function advance_to_step(int $enrolment_id, int $step_id): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $this->table,
            ['current_step_id' => $step_id],
            ['id' => $enrolment_id]
        );
    }

    /**
     * Mark enrolment as completed.
     */
    public function complete(int $enrolment_id): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $this->table,
            [
                'status'       => 'completed',
                'completed_at' => current_time('mysql', true),
            ],
            ['id' => $enrolment_id]
        );
    }

    /**
     * Exit subscriber from enrolment.
     */
    public function exit_enrolment(int $enrolment_id, string $reason = ''): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $this->table,
            [
                'status'      => 'exited',
                'exited_at'   => current_time('mysql', true),
                'exit_reason' => sanitize_text_field($reason),
            ],
            ['id' => $enrolment_id]
        );
    }

    /**
     * Exit all active enrolments for a subscriber (e.g. on unsubscribe).
     */
    public function exit_all_for_subscriber(int $subscriber_id, string $reason = 'unsubscribed'): int
    {
        global $wpdb;
        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET status = 'exited', exited_at = %s, exit_reason = %s WHERE subscriber_id = %d AND status = 'active'",
            current_time('mysql', true),
            sanitize_text_field($reason),
            $subscriber_id
        ));
    }

    /**
     * Get all active enrolments (for step processing).
     *
     * @return object[]
     */
    public function get_active_enrolments(int $limit = 100): array
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, f.status AS flow_status
             FROM {$this->table} e
             INNER JOIN {$this->flows_table} f ON f.id = e.flow_id
             WHERE e.status = 'active' AND f.status = 'active'
             ORDER BY e.enrolled_at ASC
             LIMIT %d",
            $limit
        ));
        return $results ?: [];
    }

    /**
     * Get the next step after the current one in a flow.
     */
    public function get_next_step(int $flow_id, int $current_step_id): ?object
    {
        global $wpdb;

        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT step_order FROM {$this->steps_table} WHERE id = %d AND flow_id = %d",
            $current_step_id,
            $flow_id
        ));

        if (!$current) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->steps_table} WHERE flow_id = %d AND step_order > %d ORDER BY step_order ASC LIMIT 1",
            $flow_id,
            (int) $current->step_order
        ));
    }

    /**
     * Get flow step by ID.
     */
    public function get_step(int $step_id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->steps_table} WHERE id = %d",
            $step_id
        ));
    }

    /**
     * Get all active enrolments for a specific subscriber.
     *
     * @return object[]
     */
    public function get_active_for_subscriber(int $subscriber_id): array
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE subscriber_id = %d AND status = 'active'",
            $subscriber_id
        ));
        return $results ?: [];
    }
}
