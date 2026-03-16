<?php
/**
 * Flow step executor — processes individual flow steps for enrolments.
 *
 * @package Apotheca\Marketing\Flows
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows;

use Apotheca\Marketing\Settings;
use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;
use Apotheca\Marketing\Flows\Steps\SendEmail;
use Apotheca\Marketing\Flows\Steps\SendSms;
use Apotheca\Marketing\Flows\Steps\AddTag;
use Apotheca\Marketing\Flows\Steps\RemoveTag;
use Apotheca\Marketing\Flows\Steps\UpdateField;
use Apotheca\Marketing\Flows\Steps\ConditionBranch;
use Apotheca\Marketing\Flows\Steps\Wait;
use Apotheca\Marketing\Flows\Steps\ExitFlow;

defined('ABSPATH') || exit;

final class StepExecutor
{
    private const PROCESS_HOOK = 'ams_flow_process_step';

    private EnrolmentRepository $enrolments;
    private SubscriberRepository $subscribers;

    public function __construct()
    {
        $this->enrolments = new EnrolmentRepository();
        $this->subscribers = new SubscriberRepository();

        add_action(self::PROCESS_HOOK, [$this, 'process_scheduled_step'], 10, 2);
    }

    /**
     * Execute the current step for an enrolment and advance.
     */
    public function execute(int $enrolment_id): void
    {
        $enrolment = $this->enrolments->find($enrolment_id);
        if (!$enrolment || $enrolment->status !== 'active') {
            return;
        }

        if (!$enrolment->current_step_id) {
            $this->enrolments->complete($enrolment_id);
            return;
        }

        $step = $this->enrolments->get_step((int) $enrolment->current_step_id);
        if (!$step) {
            $this->enrolments->complete($enrolment_id);
            return;
        }

        $subscriber = $this->subscribers->find((int) $enrolment->subscriber_id);
        if (!$subscriber || $subscriber->status === 'unsubscribed') {
            $this->enrolments->exit_enrolment($enrolment_id, 'subscriber_unsubscribed');
            return;
        }

        // Check frequency cap before sending.
        if (in_array($step->step_type, ['send_email', 'send_sms'], true)) {
            if (!$this->check_frequency_cap($subscriber, $step->step_type === 'send_sms' ? 'sms' : 'email')) {
                // Reschedule for 1 hour later.
                $this->schedule_step($enrolment_id, (int) $step->id, 3600);
                return;
            }

            if (!$this->is_within_send_window($subscriber)) {
                // Reschedule for next send window opening.
                $delay = $this->seconds_until_send_window($subscriber);
                $this->schedule_step($enrolment_id, (int) $step->id, $delay);
                return;
            }
        }

        // Execute the step.
        $processor = $this->get_processor($step->step_type);
        if (!$processor) {
            $this->advance($enrolment_id, $enrolment);
            return;
        }

        $result = $processor->process($subscriber, $step, $enrolment);

        // Handle condition branching.
        if ($step->step_type === 'condition' && $result instanceof ConditionResult) {
            if ($result->next_step_id) {
                $this->enrolments->advance_to_step($enrolment_id, $result->next_step_id);
                $this->schedule_step($enrolment_id, $result->next_step_id, 0);
            } else {
                $this->advance($enrolment_id, $enrolment);
            }
            return;
        }

        // Handle wait step — schedule deferred.
        if ($step->step_type === 'wait') {
            $delay_seconds = $this->calculate_delay_seconds((int) $step->delay_value, $step->delay_unit);
            $next = $this->enrolments->get_next_step((int) $enrolment->flow_id, (int) $step->id);
            if ($next) {
                $this->enrolments->advance_to_step($enrolment_id, (int) $next->id);
                $this->schedule_step($enrolment_id, (int) $next->id, $delay_seconds);
            } else {
                $this->enrolments->complete($enrolment_id);
            }
            return;
        }

        // Handle exit step.
        if ($step->step_type === 'exit') {
            $this->enrolments->exit_enrolment($enrolment_id, 'exit_step');
            return;
        }

        // Advance to next step immediately for non-wait steps.
        $this->advance($enrolment_id, $enrolment);
    }

    /**
     * Advance enrolment to the next step.
     */
    private function advance(int $enrolment_id, object $enrolment): void
    {
        $next = $this->enrolments->get_next_step(
            (int) $enrolment->flow_id,
            (int) $enrolment->current_step_id
        );

        if ($next) {
            $this->enrolments->advance_to_step($enrolment_id, (int) $next->id);
            $this->schedule_step($enrolment_id, (int) $next->id, 0);
        } else {
            $this->enrolments->complete($enrolment_id);
        }
    }

    /**
     * Schedule a step for deferred processing via Action Scheduler.
     */
    public function schedule_step(int $enrolment_id, int $step_id, int $delay_seconds = 0): void
    {
        if (!function_exists('as_schedule_single_action')) {
            return;
        }

        as_schedule_single_action(
            time() + max(0, $delay_seconds),
            self::PROCESS_HOOK,
            ['enrolment_id' => $enrolment_id, 'step_id' => $step_id],
            'ams'
        );
    }

    /**
     * Action Scheduler callback.
     */
    public function process_scheduled_step(int $enrolment_id, int $step_id): void
    {
        $enrolment = $this->enrolments->find($enrolment_id);
        if (!$enrolment || $enrolment->status !== 'active') {
            return;
        }

        // Verify the step hasn't changed (guard against race conditions).
        if ((int) $enrolment->current_step_id !== $step_id) {
            return;
        }

        $this->execute($enrolment_id);
    }

    /**
     * Check if subscriber is within the email/SMS frequency cap.
     */
    private function check_frequency_cap(object $subscriber, string $channel): bool
    {
        global $wpdb;

        $cap_key = $channel === 'sms' ? 'frequency_cap_sms' : 'frequency_cap_email';
        $max = (int) Settings::get($cap_key, $channel === 'sms' ? 2 : 3);

        $sends_table = $wpdb->prefix . 'ams_sends';
        $cutoff = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$sends_table}
             WHERE subscriber_id = %d AND channel = %s AND sent_at >= %s",
            (int) $subscriber->id,
            $channel,
            $cutoff
        ));

        return $count < $max;
    }

    /**
     * Check if current time is within the send window for the subscriber.
     */
    private function is_within_send_window(object $subscriber): bool
    {
        $start = (int) Settings::get('send_window_start', 8);
        $end = (int) Settings::get('send_window_end', 21);

        $tz = $this->get_subscriber_timezone($subscriber);
        $now = new \DateTime('now', $tz);
        $hour = (int) $now->format('G');

        return $hour >= $start && $hour < $end;
    }

    /**
     * Calculate seconds until the next send window opening.
     */
    private function seconds_until_send_window(object $subscriber): int
    {
        $start = (int) Settings::get('send_window_start', 8);
        $tz = $this->get_subscriber_timezone($subscriber);

        $now = new \DateTime('now', $tz);
        $next_window = clone $now;
        $next_window->setTime($start, 0, 0);

        if ($next_window <= $now) {
            $next_window->modify('+1 day');
        }

        return max(60, $next_window->getTimestamp() - $now->getTimestamp());
    }

    /**
     * Derive subscriber timezone from WooCommerce billing country.
     */
    private function get_subscriber_timezone(object $subscriber): \DateTimeZone
    {
        $wp_tz = wp_timezone();

        // Try to derive from WooCommerce customer data.
        $user = get_user_by('email', $subscriber->email);
        if ($user) {
            $country = get_user_meta($user->ID, 'billing_country', true);
            if ($country) {
                $tz_string = $this->country_to_timezone($country);
                if ($tz_string) {
                    try {
                        return new \DateTimeZone($tz_string);
                    } catch (\Exception $e) {
                        // Fall through to site timezone.
                    }
                }
            }
        }

        return $wp_tz;
    }

    /**
     * Map country code to a representative timezone.
     */
    private function country_to_timezone(string $country): string
    {
        $map = [
            'US' => 'America/New_York', 'CA' => 'America/Toronto', 'GB' => 'Europe/London',
            'AU' => 'Australia/Sydney', 'NZ' => 'Pacific/Auckland', 'DE' => 'Europe/Berlin',
            'FR' => 'Europe/Paris', 'ES' => 'Europe/Madrid', 'IT' => 'Europe/Rome',
            'NL' => 'Europe/Amsterdam', 'BE' => 'Europe/Brussels', 'AT' => 'Europe/Vienna',
            'CH' => 'Europe/Zurich', 'SE' => 'Europe/Stockholm', 'NO' => 'Europe/Oslo',
            'DK' => 'Europe/Copenhagen', 'FI' => 'Europe/Helsinki', 'IE' => 'Europe/Dublin',
            'PT' => 'Europe/Lisbon', 'PL' => 'Europe/Warsaw', 'CZ' => 'Europe/Prague',
            'JP' => 'Asia/Tokyo', 'CN' => 'Asia/Shanghai', 'IN' => 'Asia/Kolkata',
            'BR' => 'America/Sao_Paulo', 'MX' => 'America/Mexico_City', 'AR' => 'America/Argentina/Buenos_Aires',
            'ZA' => 'Africa/Johannesburg', 'SG' => 'Asia/Singapore', 'HK' => 'Asia/Hong_Kong',
            'KR' => 'Asia/Seoul', 'TW' => 'Asia/Taipei', 'TH' => 'Asia/Bangkok',
            'MY' => 'Asia/Kuala_Lumpur', 'PH' => 'Asia/Manila', 'IL' => 'Asia/Jerusalem',
            'AE' => 'Asia/Dubai', 'RU' => 'Europe/Moscow', 'TR' => 'Europe/Istanbul',
        ];

        return $map[strtoupper($country)] ?? '';
    }

    /**
     * Calculate delay in seconds from value + unit.
     */
    private function calculate_delay_seconds(int $value, string $unit): int
    {
        return match ($unit) {
            'minutes' => $value * MINUTE_IN_SECONDS,
            'hours'   => $value * HOUR_IN_SECONDS,
            'days'    => $value * DAY_IN_SECONDS,
            'weeks'   => $value * WEEK_IN_SECONDS,
            default   => $value * MINUTE_IN_SECONDS,
        };
    }

    /**
     * Get the step processor for a step type.
     */
    private function get_processor(string $step_type): ?object
    {
        return match ($step_type) {
            'send_email'   => new SendEmail(),
            'send_sms'     => new SendSms(),
            'add_tag'      => new AddTag(),
            'remove_tag'   => new RemoveTag(),
            'update_field' => new UpdateField(),
            'condition'    => new ConditionBranch(),
            'wait'         => new Wait(),
            'exit'         => new ExitFlow(),
            default        => null,
        };
    }
}
