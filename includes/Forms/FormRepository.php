<?php
/**
 * Form repository — DB operations for ams_forms.
 *
 * @package Apotheca\Marketing\Forms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Forms;

defined('ABSPATH') || exit;

final class FormRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ams_forms';
    }

    public function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }

    /**
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
        if (!empty($args['type'])) {
            $where .= ' AND type = %s';
            $values[] = sanitize_text_field($args['type']);
        }

        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC";

        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$values)) ?: [];
        }

        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Get all active forms (for public endpoint).
     *
     * @return object[]
     */
    public function get_active(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY id ASC"
        ) ?: [];
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);

        $wpdb->insert($this->table, [
            'name'             => sanitize_text_field($data['name'] ?? ''),
            'type'             => sanitize_text_field($data['type'] ?? 'modal'),
            'trigger_config'   => wp_json_encode($data['trigger_config'] ?? []),
            'targeting_config' => wp_json_encode($data['targeting_config'] ?? []),
            'fields'           => wp_json_encode($data['fields'] ?? []),
            'design_config'    => wp_json_encode($data['design_config'] ?? []),
            'success_config'   => wp_json_encode($data['success_config'] ?? []),
            'spin_config'      => wp_json_encode($data['spin_config'] ?? []),
            'status'           => 'draft',
            'views'            => 0,
            'submissions'      => 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $update = ['updated_at' => current_time('mysql', true)];

        $text_fields = ['name', 'type', 'status'];
        foreach ($text_fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = sanitize_text_field($data[$field]);
            }
        }

        $json_fields = ['trigger_config', 'targeting_config', 'fields', 'design_config', 'success_config', 'spin_config'];
        foreach ($json_fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = is_string($data[$field]) ? $data[$field] : wp_json_encode($data[$field]);
            }
        }

        return (bool) $wpdb->update($this->table, $update, ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table, ['id' => $id]);
    }

    /**
     * Increment view count.
     */
    public function increment_views(int $id): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET views = views + 1 WHERE id = %d",
            $id
        ));
    }

    /**
     * Increment submission count.
     */
    public function increment_submissions(int $id): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET submissions = submissions + 1 WHERE id = %d",
            $id
        ));
    }
}
