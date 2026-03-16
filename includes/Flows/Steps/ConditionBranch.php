<?php
/**
 * Condition branch flow step processor.
 *
 * Evaluates if/else logic based on subscriber data or engagement.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

use Apotheca\Marketing\Flows\ConditionResult;

defined('ABSPATH') || exit;

final class ConditionBranch implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        $conditions = json_decode($step->conditions ?: '{}', true) ?: [];

        $rules = $conditions['rules'] ?? [];
        $yes_step_id = $conditions['yes_step_id'] ?? null;
        $no_step_id = $conditions['no_step_id'] ?? null;

        $matched = $this->evaluate_rules($rules, $subscriber);

        $next_step_id = $matched ? $yes_step_id : $no_step_id;

        return new ConditionResult($matched, $next_step_id ? (int) $next_step_id : null);
    }

    /**
     * Evaluate a set of condition rules against a subscriber.
     *
     * @param array<array{field: string, operator: string, value: mixed}> $rules
     */
    private function evaluate_rules(array $rules, object $subscriber): bool
    {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            $field = $rule['field'] ?? '';
            $operator = $rule['operator'] ?? 'equals';
            $value = $rule['value'] ?? '';

            $actual = $this->get_subscriber_value($field, $subscriber);

            if (!$this->evaluate_condition($actual, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a value from the subscriber record.
     */
    private function get_subscriber_value(string $field, object $subscriber): mixed
    {
        // Direct subscriber fields.
        if (property_exists($subscriber, $field)) {
            return $subscriber->$field;
        }

        // Custom fields.
        $custom = json_decode($subscriber->custom_fields ?: '{}', true) ?: [];
        if (array_key_exists($field, $custom)) {
            return $custom[$field];
        }

        // Tags check.
        if ($field === 'has_tag') {
            return json_decode($subscriber->tags ?: '[]', true) ?: [];
        }

        // Engagement checks via database.
        if (str_starts_with($field, 'has_opened_') || str_starts_with($field, 'has_clicked_')) {
            return $this->check_engagement($field, $subscriber);
        }

        return null;
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluate_condition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals', 'is'        => (string) $actual === (string) $expected,
            'not_equals', 'is_not' => (string) $actual !== (string) $expected,
            'greater_than'        => (float) $actual > (float) $expected,
            'less_than'           => (float) $actual < (float) $expected,
            'contains'            => is_string($actual) && str_contains($actual, (string) $expected),
            'not_contains'        => is_string($actual) && !str_contains($actual, (string) $expected),
            'is_blank'            => empty($actual),
            'is_not_blank'        => !empty($actual),
            'has_tag'             => is_array($actual) && in_array((string) $expected, $actual, true),
            'not_has_tag'         => is_array($actual) && !in_array((string) $expected, $actual, true),
            'is_true'             => (bool) $actual === true || $actual === '1' || $actual === 1,
            'is_false'            => (bool) $actual === false || $actual === '0' || $actual === 0 || $actual === '',
            default               => false,
        };
    }

    /**
     * Check engagement (opens/clicks) from ams_sends.
     */
    private function check_engagement(string $field, object $subscriber): bool
    {
        global $wpdb;
        $sends_table = $wpdb->prefix . 'ams_sends';

        if (str_starts_with($field, 'has_opened_any')) {
            return (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$sends_table} WHERE subscriber_id = %d AND opened_at IS NOT NULL",
                (int) $subscriber->id
            ));
        }

        if (str_starts_with($field, 'has_clicked_any')) {
            return (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$sends_table} WHERE subscriber_id = %d AND clicked_at IS NOT NULL",
                (int) $subscriber->id
            ));
        }

        return false;
    }
}
