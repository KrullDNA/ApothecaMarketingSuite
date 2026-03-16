<?php
/**
 * Remove tag flow step processor.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class RemoveTag implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        $conditions = json_decode($step->conditions ?: '{}', true) ?: [];
        $tag = sanitize_text_field($conditions['tag'] ?? '');

        if (empty($tag)) {
            return false;
        }

        $tags = json_decode($subscriber->tags ?: '[]', true) ?: [];
        $tags = array_values(array_filter($tags, fn(string $t) => $t !== $tag));

        $repo = new SubscriberRepository();
        $repo->update((int) $subscriber->id, ['tags' => $tags]);

        return true;
    }
}
