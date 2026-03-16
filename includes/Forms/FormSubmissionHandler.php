<?php
/**
 * Form submission handler.
 *
 * Processes form submissions: validates, creates/updates subscriber,
 * applies success actions (tags, flow enrolment), handles GDPR.
 *
 * @package Apotheca\Marketing\Forms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Forms;

use Apotheca\Marketing\Settings;
use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class FormSubmissionHandler
{
    private FormRepository $forms;
    private SubscriberRepository $subscribers;

    public function __construct()
    {
        $this->forms = new FormRepository();
        $this->subscribers = new SubscriberRepository();
    }

    /**
     * Process a form submission.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function handle(int $form_id, array $submission, string $ip): array
    {
        $form = $this->forms->find($form_id);
        if (!$form || $form->status !== 'active') {
            return ['success' => false, 'message' => 'Form not found or inactive.'];
        }

        // Rate limiting: max 10 submissions per IP per minute.
        $rate_key = 'ams_form_rate_' . md5($ip);
        $count = (int) get_transient($rate_key);
        if ($count >= 10) {
            return ['success' => false, 'message' => 'Too many submissions. Please try again later.'];
        }
        set_transient($rate_key, $count + 1, 60);

        // Validate required email.
        $email = sanitize_email($submission['email'] ?? '');
        if (empty($email) || !is_email($email)) {
            return ['success' => false, 'message' => 'Please provide a valid email address.'];
        }

        // Build subscriber data from form fields.
        $sub_data = [
            'email'  => $email,
            'source' => sanitize_text_field($submission['_source'] ?? 'form-' . $form_id),
        ];

        if (!empty($submission['first_name'])) {
            $sub_data['first_name'] = sanitize_text_field($submission['first_name']);
        }
        if (!empty($submission['last_name'])) {
            $sub_data['last_name'] = sanitize_text_field($submission['last_name']);
        }
        if (!empty($submission['phone'])) {
            $sub_data['phone'] = preg_replace('/[^0-9+\-\s()]/', '', $submission['phone']);
        }

        // Handle birthday field.
        $custom_fields = [];
        if (!empty($submission['birthday_month']) && !empty($submission['birthday_day'])) {
            $custom_fields['birthday'] = sprintf(
                '%02d-%02d',
                (int) $submission['birthday_month'],
                (int) $submission['birthday_day']
            );
        }

        // Handle custom fields from radio, checkbox, dropdown, hidden.
        $fields_config = json_decode($form->fields ?: '[]', true) ?: [];
        foreach ($fields_config as $field) {
            $field_type = $field['type'] ?? '';
            $field_name = $field['name'] ?? '';
            if (in_array($field_type, ['radio', 'checkbox', 'dropdown', 'hidden'], true) && !empty($field_name)) {
                if (isset($submission[$field_name])) {
                    $val = $submission[$field_name];
                    $custom_fields[$field_name] = is_array($val)
                        ? array_map('sanitize_text_field', $val)
                        : sanitize_text_field($val);
                }
            }
        }

        if (!empty($custom_fields)) {
            $existing = $this->subscribers->find_by_email($email);
            $existing_fields = [];
            if ($existing) {
                $existing_fields = json_decode($existing->custom_fields ?: '{}', true) ?: [];
            }
            $sub_data['custom_fields'] = array_merge($existing_fields, $custom_fields);
        }

        // GDPR consent.
        $success_config = json_decode($form->success_config ?: '{}', true) ?: [];
        $form_double_optin = !empty($success_config['double_optin']);
        $global_double_optin = Settings::get('gdpr_double_optin', false);
        $needs_double_optin = $form_double_optin || $global_double_optin;

        if (!empty($submission['gdpr_consent'])) {
            $sub_data['gdpr_consent'] = 1;
            $sub_data['gdpr_timestamp'] = current_time('mysql', true);
        }

        if ($needs_double_optin) {
            $sub_data['status'] = 'pending';
        } else {
            $sub_data['status'] = 'subscribed';
        }

        // Upsert subscriber.
        $subscriber_id = $this->subscribers->upsert($sub_data);
        if (!$subscriber_id) {
            return ['success' => false, 'message' => 'Unable to process subscription.'];
        }

        // Send double opt-in email if needed.
        if ($needs_double_optin) {
            $subscriber = $this->subscribers->find($subscriber_id);
            if ($subscriber) {
                do_action('ams_send_double_optin_email', $subscriber);
            }
        }

        // Increment submission count.
        $this->forms->increment_submissions($form_id);

        // Apply success actions.
        $this->apply_success_actions($subscriber_id, $success_config);

        // Get success response.
        $response_data = $this->get_success_response($success_config, $form);

        return [
            'success' => true,
            'message' => $response_data['message'],
            'data'    => $response_data,
        ];
    }

    /**
     * Apply success actions: add tag, enrol in flow.
     */
    private function apply_success_actions(int $subscriber_id, array $config): void
    {
        // Add tag(s).
        if (!empty($config['add_tags'])) {
            $subscriber = $this->subscribers->find($subscriber_id);
            if ($subscriber) {
                $existing_tags = json_decode($subscriber->tags ?: '[]', true) ?: [];
                $new_tags = array_map('trim', explode(',', $config['add_tags']));
                $merged = array_unique(array_merge($existing_tags, $new_tags));
                $this->subscribers->update($subscriber_id, ['tags' => $merged]);
            }
        }

        // Enrol in flow.
        if (!empty($config['enrol_flow_id'])) {
            $flow_id = (int) $config['enrol_flow_id'];
            if ($flow_id > 0) {
                $enrolment_repo = new \Apotheca\Marketing\Flows\EnrolmentRepository();
                $enrolment_repo->enrol($flow_id, $subscriber_id);
            }
        }
    }

    /**
     * Build the success response data.
     */
    private function get_success_response(array $config, object $form): array
    {
        $action = $config['action'] ?? 'message';
        $message = $config['message'] ?? 'Thank you for subscribing!';

        $data = [
            'action'  => $action,
            'message' => $message,
        ];

        if ($action === 'redirect' && !empty($config['redirect_url'])) {
            $data['redirect_url'] = esc_url($config['redirect_url']);
        }

        // Spin-to-win: include the prize result.
        if ($form->type === 'spin_to_win' && !empty($config['spin_prize'])) {
            $data['spin_prize'] = $config['spin_prize'];
        }

        return $data;
    }
}
