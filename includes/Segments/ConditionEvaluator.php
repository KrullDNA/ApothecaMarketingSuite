<?php
/**
 * Segment condition evaluator.
 *
 * Evaluates 25+ condition types with AND/OR nested group logic (up to 3 levels).
 *
 * @package Apotheca\Marketing\Segments
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Segments;

defined('ABSPATH') || exit;

final class ConditionEvaluator
{
    /**
     * Evaluate whether a subscriber matches a segment's conditions.
     *
     * @param object $subscriber The subscriber row from ams_subscribers.
     * @param array  $conditions The segment conditions structure.
     */
    public function matches(object $subscriber, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Top-level is a group.
        return $this->evaluate_group($subscriber, $conditions);
    }

    /**
     * Evaluate a condition group (AND/OR with nested rules/groups).
     *
     * Structure: {
     *   "logic": "AND"|"OR",
     *   "rules": [
     *     { "type": "condition_type", "operator": "...", "value": "..." },
     *     { "logic": "OR", "rules": [...] }  // nested group
     *   ]
     * }
     */
    private function evaluate_group(object $subscriber, array $group): bool
    {
        $logic = strtoupper($group['logic'] ?? 'AND');
        $rules = $group['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            // Nested group.
            if (isset($rule['logic'])) {
                $result = $this->evaluate_group($subscriber, $rule);
            } else {
                $result = $this->evaluate_rule($subscriber, $rule);
            }

            if ($logic === 'OR' && $result) {
                return true;
            }
            if ($logic === 'AND' && !$result) {
                return false;
            }
        }

        return $logic === 'AND';
    }

    /**
     * Evaluate a single condition rule.
     */
    private function evaluate_rule(object $subscriber, array $rule): bool
    {
        $type = $rule['type'] ?? '';
        $operator = $rule['operator'] ?? '';
        $value = $rule['value'] ?? '';

        return match ($type) {
            // Subscriber data conditions.
            'email_domain'      => $this->eval_email_domain($subscriber, $operator, $value),
            'first_name'        => $this->eval_text_field($subscriber->first_name ?? '', $operator, $value),
            'tag'               => $this->eval_tag($subscriber, $operator, $value),
            'custom_field'      => $this->eval_custom_field($subscriber, $operator, $value, $rule['field_name'] ?? ''),
            'source'            => $this->eval_simple($subscriber->source ?? '', $operator, $value),
            'gdpr_consent'      => $this->eval_boolean((bool) ($subscriber->gdpr_consent ?? false), $operator),
            'subscribed_date'   => $this->eval_date($subscriber->subscribed_at ?? '', $operator, $value),
            'predicted_clv'     => $this->eval_numeric((float) ($subscriber->predicted_clv ?? 0), $operator, $value, $rule['value2'] ?? ''),
            'churn_risk_score'  => $this->eval_numeric((float) ($subscriber->churn_risk_score ?? 0), $operator, $value),
            'rfm_segment'       => $this->eval_simple($subscriber->rfm_segment ?? '', $operator, $value),

            // Ecommerce conditions.
            'total_orders'         => $this->eval_numeric((float) ($subscriber->total_orders ?? 0), $operator, $value, $rule['value2'] ?? ''),
            'total_spent'          => $this->eval_numeric((float) ($subscriber->total_spent ?? 0), $operator, $value, $rule['value2'] ?? ''),
            'average_order_value'  => $this->eval_aov($subscriber, $operator, $value, $rule['value2'] ?? ''),
            'last_order_date'      => $this->eval_date($subscriber->last_order_date ?? '', $operator, $value),
            'purchased_product'    => $this->eval_purchased_product($subscriber, $operator, $value),
            'purchased_category'   => $this->eval_purchased_category($subscriber, $operator, $value),
            'last_order_status'    => $this->eval_last_order_status($subscriber, $operator, $value),
            'used_coupon'          => $this->eval_used_coupon($subscriber, $operator, $value),

            // Engagement conditions.
            'opened_campaign'      => $this->eval_campaign_engagement($subscriber, 'opened_at', $operator, $value),
            'clicked_campaign'     => $this->eval_campaign_engagement($subscriber, 'clicked_at', $operator, $value),
            'opened_any_email'     => $this->eval_any_engagement($subscriber, 'opened_at', $operator, $value),
            'clicked_any_email'    => $this->eval_any_engagement($subscriber, 'clicked_at', $operator, $value),
            'sms_opt_in'           => $this->eval_sms_opt_in($subscriber, $operator),
            'email_bounce_status'  => $this->eval_bounce_status($subscriber, $operator, $value),

            default => false,
        };
    }

    /* ── Subscriber Data Evaluators ── */

    private function eval_email_domain(object $subscriber, string $operator, string $value): bool
    {
        $email = $subscriber->email ?? '';
        $domain = substr(strrchr($email, '@') ?: '', 1);

        return match ($operator) {
            'is'       => strcasecmp($domain, $value) === 0,
            'is_not'   => strcasecmp($domain, $value) !== 0,
            'contains' => stripos($domain, $value) !== false,
            default    => false,
        };
    }

    private function eval_text_field(string $actual, string $operator, string $value): bool
    {
        return match ($operator) {
            'is'           => strcasecmp($actual, $value) === 0,
            'is_not'       => strcasecmp($actual, $value) !== 0,
            'is_blank'     => $actual === '',
            'is_not_blank' => $actual !== '',
            'contains'     => stripos($actual, $value) !== false,
            default        => false,
        };
    }

    private function eval_tag(object $subscriber, string $operator, string $value): bool
    {
        $tags = json_decode($subscriber->tags ?: '[]', true) ?: [];
        $check_tags = array_map('trim', explode(',', $value));

        return match ($operator) {
            'has'          => !empty(array_intersect($check_tags, $tags)),
            'does_not_have' => empty(array_intersect($check_tags, $tags)),
            default         => false,
        };
    }

    private function eval_custom_field(object $subscriber, string $operator, string $value, string $field_name): bool
    {
        $fields = json_decode($subscriber->custom_fields ?: '{}', true) ?: [];
        $actual = $fields[$field_name] ?? '';

        return match ($operator) {
            'equals'       => (string) $actual === $value,
            'not_equals'   => (string) $actual !== $value,
            'contains'     => stripos((string) $actual, $value) !== false,
            'greater_than' => (float) $actual > (float) $value,
            'less_than'    => (float) $actual < (float) $value,
            default        => false,
        };
    }

    private function eval_simple(string $actual, string $operator, string $value): bool
    {
        return match ($operator) {
            'is'     => strcasecmp($actual, $value) === 0,
            'is_not' => strcasecmp($actual, $value) !== 0,
            default  => false,
        };
    }

    private function eval_boolean(bool $actual, string $operator): bool
    {
        return match ($operator) {
            'is_true'  => $actual,
            'is_false' => !$actual,
            default    => false,
        };
    }

    private function eval_date(string $date_str, string $operator, string $value): bool
    {
        if (empty($date_str)) {
            return $operator === 'never' || $operator === 'is_blank';
        }

        $ts = strtotime($date_str);
        if ($ts === false) {
            return false;
        }

        return match ($operator) {
            'before'              => $ts < strtotime($value),
            'after'               => $ts > strtotime($value),
            'within_last_X_days'  => $ts >= strtotime("-{$value} days"),
            'more_than_X_days_ago' => $ts < strtotime("-{$value} days"),
            default               => false,
        };
    }

    private function eval_numeric(float $actual, string $operator, string $value, string $value2 = ''): bool
    {
        $v = (float) $value;

        return match ($operator) {
            'greater_than' => $actual > $v,
            'less_than'    => $actual < $v,
            'equals'       => abs($actual - $v) < 0.001,
            'between'      => $actual >= $v && $actual <= (float) $value2,
            default        => false,
        };
    }

    /* ── Ecommerce Evaluators ── */

    private function eval_aov(object $subscriber, string $operator, string $value, string $value2 = ''): bool
    {
        $orders = (int) ($subscriber->total_orders ?? 0);
        $spent = (float) ($subscriber->total_spent ?? 0);
        $aov = $orders > 0 ? $spent / $orders : 0.0;

        return $this->eval_numeric($aov, $operator, $value, $value2);
    }

    private function eval_purchased_product(object $subscriber, string $operator, string $value): bool
    {
        global $wpdb;

        $events_table = $wpdb->prefix . 'ams_events';
        $sub_id = (int) $subscriber->id;

        // Check product_ids JSON in placed_order or completed_purchase events.
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT product_ids FROM {$events_table}
             WHERE subscriber_id = %d AND event_type IN ('placed_order', 'completed_purchase')
               AND product_ids IS NOT NULL",
            $sub_id
        ));

        $all_product_ids = [];
        foreach ($rows as $json) {
            $ids = json_decode($json, true);
            if (is_array($ids)) {
                $all_product_ids = array_merge($all_product_ids, $ids);
            }
        }

        // Value can be product ID or SKU.
        $product_id = (int) $value;
        if ($product_id === 0 && function_exists('wc_get_product_id_by_sku')) {
            // Try lookup by SKU.
            $product_id = (int) wc_get_product_id_by_sku($value);
        }

        $has = in_array($product_id, array_map('intval', $all_product_ids), true);

        return match ($operator) {
            'has'     => $has,
            'has_not' => !$has,
            default   => false,
        };
    }

    private function eval_purchased_category(object $subscriber, string $operator, string $value): bool
    {
        global $wpdb;

        $events_table = $wpdb->prefix . 'ams_events';
        $sub_id = (int) $subscriber->id;

        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT product_ids FROM {$events_table}
             WHERE subscriber_id = %d AND event_type IN ('placed_order', 'completed_purchase')
               AND product_ids IS NOT NULL",
            $sub_id
        ));

        $all_product_ids = [];
        foreach ($rows as $json) {
            $ids = json_decode($json, true);
            if (is_array($ids)) {
                $all_product_ids = array_merge($all_product_ids, array_map('intval', $ids));
            }
        }

        $all_product_ids = array_unique($all_product_ids);

        $category_slug = sanitize_text_field($value);
        $has = false;

        foreach ($all_product_ids as $pid) {
            if (has_term($category_slug, 'product_cat', $pid)) {
                $has = true;
                break;
            }
        }

        return match ($operator) {
            'has'     => $has,
            'has_not' => !$has,
            default   => false,
        };
    }

    private function eval_last_order_status(object $subscriber, string $operator, string $value): bool
    {
        if (!function_exists('wc_get_orders')) {
            return false;
        }

        $email = $subscriber->email ?? '';
        if (empty($email)) {
            return false;
        }

        $orders = wc_get_orders([
            'billing_email' => $email,
            'limit'         => 1,
            'orderby'       => 'date',
            'order'         => 'DESC',
            'return'        => 'objects',
        ]);

        if (empty($orders)) {
            return false;
        }

        $status = $orders[0]->get_status();

        return match ($operator) {
            'is'     => $status === $value || 'wc-' . $status === $value,
            'is_not' => $status !== $value && 'wc-' . $status !== $value,
            default  => false,
        };
    }

    private function eval_used_coupon(object $subscriber, string $operator, string $value): bool
    {
        global $wpdb;

        $events_table = $wpdb->prefix . 'ams_events';
        $sub_id = (int) $subscriber->id;

        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT event_data FROM {$events_table}
             WHERE subscriber_id = %d AND event_type = 'placed_order'",
            $sub_id
        ));

        $all_coupons = [];
        foreach ($rows as $json) {
            $data = json_decode($json, true);
            if (!empty($data['coupon_codes']) && is_array($data['coupon_codes'])) {
                $all_coupons = array_merge($all_coupons, $data['coupon_codes']);
            }
        }

        $coupon = strtolower(trim($value));
        $has = in_array($coupon, array_map('strtolower', $all_coupons), true);

        return match ($operator) {
            'has'     => $has,
            'has_not' => !$has,
            default   => false,
        };
    }

    /* ── Engagement Evaluators ── */

    private function eval_campaign_engagement(object $subscriber, string $field, string $operator, string $value): bool
    {
        global $wpdb;

        $sends_table = $wpdb->prefix . 'ams_sends';
        $sub_id = (int) $subscriber->id;
        $campaign_id = (int) $value;

        $has = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$sends_table}
             WHERE subscriber_id = %d AND campaign_id = %d AND {$field} IS NOT NULL",
            $sub_id,
            $campaign_id
        ));

        return match ($operator) {
            'has'     => $has,
            'has_not' => !$has,
            default   => false,
        };
    }

    private function eval_any_engagement(object $subscriber, string $field, string $operator, string $value): bool
    {
        global $wpdb;

        $sends_table = $wpdb->prefix . 'ams_sends';
        $sub_id = (int) $subscriber->id;

        return match ($operator) {
            'ever' => (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$sends_table}
                 WHERE subscriber_id = %d AND channel = 'email' AND {$field} IS NOT NULL",
                $sub_id
            )),
            'never' => !(bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$sends_table}
                 WHERE subscriber_id = %d AND channel = 'email' AND {$field} IS NOT NULL",
                $sub_id
            )),
            'within_last_X_days' => (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$sends_table}
                 WHERE subscriber_id = %d AND channel = 'email' AND {$field} >= %s",
                $sub_id,
                gmdate('Y-m-d H:i:s', time() - ((int) $value * DAY_IN_SECONDS))
            )),
            default => false,
        };
    }

    private function eval_sms_opt_in(object $subscriber, string $operator): bool
    {
        $has_phone = !empty($subscriber->phone);

        return match ($operator) {
            'is_true'  => $has_phone,
            'is_false' => !$has_phone,
            default    => false,
        };
    }

    private function eval_bounce_status(object $subscriber, string $operator, string $value): bool
    {
        global $wpdb;

        $sends_table = $wpdb->prefix . 'ams_sends';
        $sub_id = (int) $subscriber->id;

        // Check the most recent email send for bounce status.
        $last_send = $wpdb->get_row($wpdb->prepare(
            "SELECT bounced_at, status FROM {$sends_table}
             WHERE subscriber_id = %d AND channel = 'email'
             ORDER BY created_at DESC LIMIT 1",
            $sub_id
        ));

        $status = 'none';
        if ($last_send && $last_send->bounced_at) {
            $status = $last_send->status === 'hard_bounced' ? 'hard' : 'soft';
        }

        return match ($operator) {
            'is'     => $status === $value,
            'is_not' => $status !== $value,
            default  => false,
        };
    }
}
