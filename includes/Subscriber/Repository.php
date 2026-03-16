<?php
/**
 * Subscriber repository — all DB operations for ams_subscribers.
 *
 * @package Apotheca\Marketing\Subscriber
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Subscriber;

defined('ABSPATH') || exit;

final class Repository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ams_subscribers';
    }

    /**
     * Find subscriber by ID.
     *
     * @return object|null
     */
    public function find(int $id): ?object
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
    }

    /**
     * Find subscriber by email.
     *
     * @return object|null
     */
    public function find_by_email(string $email): ?object
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s", $email));
    }

    /**
     * Find subscriber by unsubscribe token.
     *
     * @return object|null
     */
    public function find_by_token(string $token): ?object
    {
        global $wpdb;
        if (empty($token)) {
            return null;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE unsubscribe_token = %s", $token));
    }

    /**
     * Create or update a subscriber.
     *
     * @param array<string, mixed> $data
     * @return int Subscriber ID.
     */
    public function upsert(array $data): int
    {
        global $wpdb;

        $email = sanitize_email($data['email'] ?? '');
        if (empty($email)) {
            return 0;
        }

        $existing = $this->find_by_email($email);

        $now = current_time('mysql', true);

        if ($existing) {
            $update_data = $this->sanitize_fields($data);
            $update_data['updated_at'] = $now;
            unset($update_data['email'], $update_data['id'], $update_data['created_at']);

            $wpdb->update($this->table, $update_data, ['id' => $existing->id]);
            return (int) $existing->id;
        }

        $insert_data = $this->sanitize_fields($data);
        $insert_data['email'] = $email;
        $insert_data['created_at'] = $now;
        $insert_data['updated_at'] = $now;

        if (empty($insert_data['unsubscribe_token'])) {
            $insert_data['unsubscribe_token'] = wp_generate_password(48, false);
        }

        if (empty($insert_data['subscribed_at']) && ($insert_data['status'] ?? 'subscribed') === 'subscribed') {
            $insert_data['subscribed_at'] = $now;
        }

        $wpdb->insert($this->table, $insert_data);
        return (int) $wpdb->insert_id;
    }

    /**
     * Update specific fields for a subscriber.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql', true);
        unset($data['id'], $data['email']);

        return (bool) $wpdb->update($this->table, $this->sanitize_fields($data), ['id' => $id]);
    }

    /**
     * Unsubscribe a subscriber by ID.
     */
    public function unsubscribe(int $id): bool
    {
        $now = current_time('mysql', true);
        return $this->update($id, [
            'status'          => 'unsubscribed',
            'unsubscribed_at' => $now,
        ]);
    }

    /**
     * Get paginated subscribers list.
     *
     * @param array<string, mixed> $args
     * @return array{items: array, total: int}
     */
    public function list(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => '',
            'search'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $values = [];

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $values[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where .= ' AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $allowed_orderby = ['id', 'email', 'first_name', 'last_name', 'status', 'created_at', 'total_orders', 'total_spent', 'rfm_score'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $offset = max(0, ((int) $args['page'] - 1) * (int) $args['per_page']);
        $limit = max(1, (int) $args['per_page']);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";
        $total = empty($values)
            ? (int) $wpdb->get_var($count_sql)
            : (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$values));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_values = array_merge($values, [$limit, $offset]);

        $items = $wpdb->get_results($wpdb->prepare($query, ...$query_values));

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }

    /**
     * Sanitize subscriber data fields.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitize_fields(array $data): array
    {
        $sanitized = [];

        $text_fields = ['first_name', 'last_name', 'status', 'source', 'rfm_score', 'rfm_segment', 'unsubscribe_token'];
        foreach ($text_fields as $field) {
            if (array_key_exists($field, $data)) {
                $sanitized[$field] = sanitize_text_field((string) $data[$field]);
            }
        }

        if (array_key_exists('phone', $data)) {
            $sanitized['phone'] = preg_replace('/[^0-9+\-\s()]/', '', (string) $data['phone']);
        }

        $json_fields = ['tags', 'custom_fields'];
        foreach ($json_fields as $field) {
            if (array_key_exists($field, $data)) {
                $sanitized[$field] = is_string($data[$field]) ? $data[$field] : wp_json_encode($data[$field]);
            }
        }

        $int_fields = ['gdpr_consent', 'total_orders', 'churn_risk_score', 'sms_opt_in', 'best_send_hour'];
        foreach ($int_fields as $field) {
            if (array_key_exists($field, $data)) {
                $sanitized[$field] = (int) $data[$field];
            }
        }

        $decimal_fields = ['predicted_clv', 'total_spent'];
        foreach ($decimal_fields as $field) {
            if (array_key_exists($field, $data)) {
                $sanitized[$field] = round((float) $data[$field], 2);
            }
        }

        $datetime_fields = ['subscribed_at', 'unsubscribed_at', 'gdpr_timestamp', 'predicted_next_order', 'last_order_date', 'created_at', 'updated_at'];
        foreach ($datetime_fields as $field) {
            if (array_key_exists($field, $data)) {
                $sanitized[$field] = sanitize_text_field((string) $data[$field]);
            }
        }

        return $sanitized;
    }
}
