<?php
/**
 * Add tag flow step processor.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class AddTag implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        $conditions = json_decode($step->conditions ?: '{}', true) ?: [];
        $tag = sanitize_text_field($conditions['tag'] ?? '');

        if (empty($tag)) {
            return false;
        }

        $tags = json_decode($subscriber->tags ?: '[]', true) ?: [];

        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $repo = new SubscriberRepository();
            $repo->update((int) $subscriber->id, ['tags' => $tags]);
        }

        return true;
    }
}
