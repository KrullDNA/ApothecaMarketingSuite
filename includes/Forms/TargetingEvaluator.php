<?php
/**
 * Form targeting evaluator.
 *
 * Evaluates targeting_config rules to determine which forms should
 * display on a given page request. Runs server-side for page/segment
 * rules and emits client-side config for scroll/time/exit-intent rules.
 *
 * @package Apotheca\Marketing\Forms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Forms;

defined('ABSPATH') || exit;

final class TargetingEvaluator
{
    /**
     * Filter active forms down to those matching the current request context.
     *
     * @param object[] $forms All active forms.
     * @param array    $context Request context: page_id, device, subscriber_id.
     * @return object[] Forms that pass server-side targeting.
     */
    public function filter(array $forms, array $context): array
    {
        $matched = [];

        foreach ($forms as $form) {
            $config = json_decode($form->targeting_config ?: '{}', true) ?: [];

            if (!$this->matches_page($config, $context)) {
                continue;
            }

            if (!$this->matches_device($config, $context)) {
                continue;
            }

            if (!$this->matches_segment($config, $context)) {
                continue;
            }

            $matched[] = $form;
        }

        return $matched;
    }

    /**
     * Check page targeting: all, include list, or exclude list.
     */
    private function matches_page(array $config, array $context): bool
    {
        $page_rule = $config['pages'] ?? 'all';
        $page_id = (int) ($context['page_id'] ?? 0);

        if ($page_rule === 'all') {
            return true;
        }

        if ($page_rule === 'specific') {
            $include_ids = array_map('intval', $config['page_ids'] ?? []);
            return in_array($page_id, $include_ids, true);
        }

        if ($page_rule === 'exclude') {
            $exclude_ids = array_map('intval', $config['exclude_page_ids'] ?? []);
            return !in_array($page_id, $exclude_ids, true);
        }

        return true;
    }

    /**
     * Check device targeting.
     */
    private function matches_device(array $config, array $context): bool
    {
        $device_rule = $config['device'] ?? 'all';

        if ($device_rule === 'all') {
            return true;
        }

        $is_mobile = $context['is_mobile'] ?? false;

        if ($device_rule === 'desktop' && $is_mobile) {
            return false;
        }

        if ($device_rule === 'mobile' && !$is_mobile) {
            return false;
        }

        return true;
    }

    /**
     * Check segment targeting (requires known subscriber).
     */
    private function matches_segment(array $config, array $context): bool
    {
        $segment_id = (int) ($config['segment_id'] ?? 0);
        if ($segment_id === 0) {
            return true;
        }

        $subscriber_id = (int) ($context['subscriber_id'] ?? 0);
        if ($subscriber_id === 0) {
            return false;
        }

        $calculator = new \Apotheca\Marketing\Segments\SegmentCalculator();
        $segment_repo = new \Apotheca\Marketing\Segments\SegmentRepository();
        $segment = $segment_repo->find($segment_id);

        if (!$segment) {
            return true;
        }

        $conditions = json_decode($segment->conditions ?: '{}', true) ?: [];
        if (empty($conditions)) {
            return true;
        }

        $matching_ids = $calculator->get_matching_subscriber_ids($conditions, 0);
        return in_array($subscriber_id, $matching_ids, true);
    }

    /**
     * Build client-side trigger config for a form (scroll, time, exit-intent, etc.).
     * These rules are evaluated in the browser JS, not on the server.
     */
    public function get_client_triggers(object $form): array
    {
        $config = json_decode($form->targeting_config ?: '{}', true) ?: [];
        $trigger = json_decode($form->trigger_config ?: '{}', true) ?: [];

        $client = [];

        // Scroll depth trigger.
        if (!empty($trigger['scroll_depth'])) {
            $client['scroll_depth'] = (int) $trigger['scroll_depth'];
        }

        // Time on page trigger (seconds).
        if (!empty($trigger['time_on_page'])) {
            $client['time_on_page'] = (int) $trigger['time_on_page'];
        }

        // Exit intent (desktop only).
        if (!empty($trigger['exit_intent'])) {
            $client['exit_intent'] = true;
        }

        // Cart value minimum.
        if (!empty($config['cart_value_min'])) {
            $client['cart_value_min'] = (float) $config['cart_value_min'];
        }

        // New vs returning visitor.
        if (!empty($config['visitor_type']) && $config['visitor_type'] !== 'all') {
            $client['visitor_type'] = $config['visitor_type'];
        }

        // UTM matching.
        if (!empty($config['utm_rules']) && is_array($config['utm_rules'])) {
            $client['utm_rules'] = $config['utm_rules'];
        }

        // Frequency cap (days).
        $client['frequency_cap_days'] = (int) ($config['frequency_cap_days'] ?? 0);

        return $client;
    }
}
