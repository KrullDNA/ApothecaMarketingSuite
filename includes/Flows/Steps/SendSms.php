<?php
/**
 * Send SMS flow step processor.
 *
 * Queues SMS via SmsSender (async via Action Scheduler).
 * Respects sms_opt_in flag — silently skips if not opted in.
 *
 * @package Apotheca\Marketing\Flows\Steps
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Steps;

use Apotheca\Marketing\Sms\SmsSender;

defined('ABSPATH') || exit;

final class SendSms implements StepProcessorInterface
{
    public function process(object $subscriber, object $step, object $enrolment): mixed
    {
        // Check phone, status, and SMS opt-in.
        if (empty($subscriber->phone) || $subscriber->status === 'unsubscribed') {
            return false;
        }

        if (empty($subscriber->sms_opt_in)) {
            return false;
        }

        $body = SmsSender::replace_tokens($step->sms_body ?? '', $subscriber);

        // Append STOP instructions for TCPA compliance.
        $body .= "\n\nReply STOP to unsubscribe.";

        // Extract MMS media URL from step conditions if present.
        $conditions = json_decode($step->conditions ?: '{}', true) ?: [];
        $media_url = $conditions['media_url'] ?? null;

        // Queue via SmsSender (creates send record and schedules Action Scheduler job).
        $sender = new SmsSender();
        $sender->queue(
            $subscriber->phone,
            $body,
            (int) $subscriber->id,
            (int) $step->id,
            0,
            $media_url
        );

        return true;
    }
}
