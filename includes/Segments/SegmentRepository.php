<?php
/**
 * Segment repository — DB operations for ams_segments.
 *
 * @package Apotheca\Marketing\Segments
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Segments;

defined('ABSPATH') || exit;

final class SegmentRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ams_segments';
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

        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        return $wpdb->get_results($sql) ?: [];
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);

        $wpdb->insert($this->table, [
            'name'             => sanitize_text_field($data['name'] ?? ''),
            'conditions'       => wp_json_encode($data['conditions'] ?? []),
            'subscriber_count' => 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $update = ['updated_at' => current_time('mysql', true)];

        if (array_key_exists('name', $data)) {
            $update['name'] = sanitize_text_field($data['name']);
        }
        if (array_key_exists('conditions', $data)) {
            $update['conditions'] = wp_json_encode($data['conditions']);
        }
        if (array_key_exists('subscriber_count', $data)) {
            $update['subscriber_count'] = (int) $data['subscriber_count'];
        }
        if (array_key_exists('last_calculated', $data)) {
            $update['last_calculated'] = $data['last_calculated'];
        }

        return (bool) $wpdb->update($this->table, $update, ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table, ['id' => $id]);
    }

    /**
     * Update cached subscriber count and calculation timestamp.
     */
    public function update_count(int $id, int $count): void
    {
        global $wpdb;
        $wpdb->update($this->table, [
            'subscriber_count' => $count,
            'last_calculated'  => current_time('mysql', true),
            'updated_at'       => current_time('mysql', true),
        ], ['id' => $id]);
    }
}
