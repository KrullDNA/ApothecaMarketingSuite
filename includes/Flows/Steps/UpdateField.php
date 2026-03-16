<?php
/**
 * Update custom field flow step processor.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class UpdateField implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        $conditions = json_decode($step->conditions ?: '{}', true) ?: [];
        $field_name = sanitize_text_field($conditions['field_name'] ?? '');
        $field_value = sanitize_text_field($conditions['field_value'] ?? '');

        if (empty($field_name)) {
            return false;
        }

        $custom_fields = json_decode($subscriber->custom_fields ?: '{}', true) ?: [];
        $custom_fields[$field_name] = $field_value;

        $repo = new SubscriberRepository();
        $repo->update((int) $subscriber->id, ['custom_fields' => $custom_fields]);

        return true;
    }
}
