<?php
/**
 * REST API controller for SMS.
 *
 * Admin: SMS campaign CRUD, credential management, test send.
 * Public: Twilio inbound webhook, delivery status webhook.
 *
 * @package Apotheca\Marketing\API
 */

declare(strict_types=1);

namespace Apotheca\Marketing\API;

use Apotheca\Marketing\Sms\CredentialEncryptor;
use Apotheca\Marketing\Sms\SmsSender;
use Apotheca\Marketing\Sms\TwilioProvider;
use Apotheca\Marketing\Sms\WebhookHandler;
use Apotheca\Marketing\Subscriber\Repository as SubscriberRepository;

defined('ABSPATH') || exit;

final class SmsController
{
    private const NAMESPACE = 'ams/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        // Admin: SMS credentials.
        register_rest_route(self::NAMESPACE, '/sms/credentials', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_credentials'],
                'permission_callback' => [$this, 'check_admin'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save_credentials'],
                'permission_callback' => [$this, 'check_admin'],
            ],
        ]);

        // Admin: test send.
        register_rest_route(self::NAMESPACE, '/sms/test', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'test_send'],
                'permission_callback' => [$this, 'check_admin'],
            ],
        ]);

        // Admin: SMS campaigns CRUD.
        register_rest_route(self::NAMESPACE, '/sms/campaigns', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_campaigns'],
                'permission_callback' => [$this, 'check_admin'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_campaign'],
                'permission_callback' => [$this, 'check_admin'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/sms/campaigns/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_campaign'],
                'permission_callback' => [$this, 'check_admin'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_campaign'],
                'permission_callback' => [$this, 'check_admin'],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_campaign'],
                'permission_callback' => [$this, 'check_admin'],
            ],
        ]);

        // Admin: send SMS campaign.
        register_rest_route(self::NAMESPACE, '/sms/campaigns/(?P<id>\d+)/send', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'send_campaign'],
                'permission_callback' => [$this, 'check_admin'],
            ],
        ]);

        // Public: Twilio inbound webhook.
        register_rest_route(self::NAMESPACE, '/sms/webhook', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_inbound_webhook'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Public: Twilio delivery status webhook.
        register_rest_route(self::NAMESPACE, '/sms/status', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_status_webhook'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function check_admin(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /* ── Credentials ── */

    public function get_credentials(\WP_REST_Request $request): \WP_REST_Response
    {
        $creds = CredentialEncryptor::retrieve();
        // Mask the auth token for display.
        $creds['auth_token'] = !empty($creds['auth_token'])
            ? str_repeat('•', max(0, strlen($creds['auth_token']) - 4)) . substr($creds['auth_token'], -4)
            : '';

        $creds['is_configured'] = CredentialEncryptor::has_credentials();
        $creds['webhook_url']   = rest_url('ams/v1/sms/webhook');
        $creds['status_url']    = rest_url('ams/v1/sms/status');

        return new \WP_REST_Response($creds, 200);
    }

    public function save_credentials(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();

        // Only update auth_token if it doesn't contain mask characters.
        $existing = CredentialEncryptor::retrieve();
        $auth_token = $data['auth_token'] ?? '';
        if (str_contains($auth_token, '•')) {
            $auth_token = $existing['auth_token'];
        }

        CredentialEncryptor::store([
            'account_sid' => sanitize_text_field($data['account_sid'] ?? ''),
            'auth_token'  => $auth_token,
            'from_number' => sanitize_text_field($data['from_number'] ?? ''),
            'help_text'   => sanitize_text_field($data['help_text'] ?? ''),
        ]);

        return new \WP_REST_Response(['message' => 'Credentials saved.'], 200);
    }

    /* ── Test Send ── */

    public function test_send(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $phone = sanitize_text_field($data['phone'] ?? '');
        $body  = sanitize_textarea_field($data['body'] ?? 'Test message from Apotheca Marketing Suite.');

        if (empty($phone)) {
            return new \WP_REST_Response(['message' => 'Phone number is required.'], 400);
        }

        $provider = new TwilioProvider();
        if (!$provider->is_configured()) {
            return new \WP_REST_Response(['message' => 'SMS provider not configured.'], 400);
        }

        $result = $provider->send($phone, $body);

        if ($result['success']) {
            return new \WP_REST_Response(['message' => 'Test SMS sent. SID: ' . $result['sid']], 200);
        }

        return new \WP_REST_Response(['message' => 'Send failed: ' . $result['error']], 400);
    }

    /* ── SMS Campaigns ── */

    public function list_campaigns(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_campaigns';
        $campaigns = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE type = 'sms' ORDER BY created_at DESC"
        ) ?: [];

        return new \WP_REST_Response($campaigns, 200);
    }

    public function create_campaign(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        if (empty($data['name'])) {
            return new \WP_REST_Response(['message' => 'Campaign name is required.'], 400);
        }

        global $wpdb;
        $now = current_time('mysql', true);

        $wpdb->insert($wpdb->prefix . 'ams_campaigns', [
            'name'       => sanitize_text_field($data['name']),
            'type'       => 'sms',
            'status'     => 'draft',
            'segment_id' => (int) ($data['segment_id'] ?? 0) ?: null,
            'sms_body'   => sanitize_textarea_field($data['sms_body'] ?? ''),
            'body_html'  => sanitize_textarea_field($data['media_url'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $wpdb->insert_id;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ams_campaigns WHERE id = %d",
            $id
        ));

        return new \WP_REST_Response($campaign, 201);
    }

    public function get_campaign(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request->get_param('id');
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ams_campaigns WHERE id = %d AND type = 'sms'",
            $id
        ));

        if (!$campaign) {
            return new \WP_REST_Response(['message' => 'Campaign not found.'], 404);
        }

        return new \WP_REST_Response($campaign, 200);
    }

    public function update_campaign(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request->get_param('id');
        $table = $wpdb->prefix . 'ams_campaigns';

        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND type = 'sms'", $id));
        if (!$campaign) {
            return new \WP_REST_Response(['message' => 'Campaign not found.'], 404);
        }

        $data = $request->get_json_params();
        $update = ['updated_at' => current_time('mysql', true)];

        if (isset($data['name'])) $update['name'] = sanitize_text_field($data['name']);
        if (isset($data['sms_body'])) $update['sms_body'] = sanitize_textarea_field($data['sms_body']);
        if (isset($data['segment_id'])) $update['segment_id'] = (int) $data['segment_id'] ?: null;
        if (isset($data['status'])) $update['status'] = sanitize_text_field($data['status']);
        if (isset($data['media_url'])) $update['body_html'] = esc_url_raw($data['media_url']);

        $wpdb->update($table, $update, ['id' => $id]);

        $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return new \WP_REST_Response($updated, 200);
    }

    public function delete_campaign(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request->get_param('id');
        $wpdb->delete($wpdb->prefix . 'ams_campaigns', ['id' => $id, 'type' => 'sms']);
        return new \WP_REST_Response(['message' => 'Campaign deleted.'], 200);
    }

    /**
     * POST /ams/v1/sms/campaigns/{id}/send — send the campaign to its segment.
     */
    public function send_campaign(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = (int) $request->get_param('id');
        $table = $wpdb->prefix . 'ams_campaigns';

        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND type = 'sms'", $id));
        if (!$campaign) {
            return new \WP_REST_Response(['message' => 'Campaign not found.'], 404);
        }
        if ($campaign->status === 'sent') {
            return new \WP_REST_Response(['message' => 'Campaign already sent.'], 400);
        }

        $provider = new TwilioProvider();
        if (!$provider->is_configured()) {
            return new \WP_REST_Response(['message' => 'SMS provider not configured.'], 400);
        }

        // Get matching subscribers.
        $segment_id = (int) ($campaign->segment_id ?? 0);
        $subscriber_ids = [];

        if ($segment_id > 0) {
            $seg_repo = new \Apotheca\Marketing\Segments\SegmentRepository();
            $segment = $seg_repo->find($segment_id);
            if ($segment) {
                $calculator = new \Apotheca\Marketing\Segments\SegmentCalculator();
                $conditions = json_decode($segment->conditions ?: '{}', true) ?: [];
                $subscriber_ids = $calculator->get_matching_subscriber_ids($conditions);
            }
        } else {
            // All SMS-opted-in subscribers.
            $subscriber_ids = $wpdb->get_col(
                "SELECT id FROM {$wpdb->prefix}ams_subscribers WHERE status = 'subscribed' AND sms_opt_in = 1 AND phone != ''"
            );
        }

        $sub_repo = new SubscriberRepository();
        $sender = new SmsSender();
        $queued = 0;
        $media_url = $campaign->body_html ?: null; // body_html stores media_url for SMS campaigns.

        foreach ($subscriber_ids as $sub_id) {
            $subscriber = $sub_repo->find((int) $sub_id);
            if (!$subscriber || empty($subscriber->phone) || empty($subscriber->sms_opt_in)) {
                continue;
            }

            $body = SmsSender::replace_tokens($campaign->sms_body ?? '', $subscriber);
            $body .= "\n\nReply STOP to unsubscribe.";

            $sender->queue(
                $subscriber->phone,
                $body,
                (int) $subscriber->id,
                0,
                (int) $campaign->id,
                $media_url
            );
            $queued++;
        }

        // Mark campaign as sent.
        $wpdb->update($table, [
            'status'  => 'sent',
            'sent_at' => current_time('mysql', true),
        ], ['id' => $id]);

        return new \WP_REST_Response([
            'message' => "Campaign queued for {$queued} subscriber" . ($queued !== 1 ? 's' : '') . '.',
            'queued'  => $queued,
        ], 200);
    }

    /* ── Public Webhooks ── */

    /**
     * POST /ams/v1/sms/webhook — inbound STOP/UNSTOP/HELP.
     */
    public function handle_inbound_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_body_params();
        $signature = $request->get_header('X-Twilio-Signature') ?? '';
        $url = rest_url('ams/v1/sms/webhook');

        $handler = new WebhookHandler();
        $result = $handler->handle_inbound($params, $signature, $url);

        // Send auto-reply if provided.
        if (!empty($result['reply']) && !empty($params['From'])) {
            $provider = new TwilioProvider();
            $provider->reply($params['From'], $result['reply']);
        }

        $status = $result['success'] ? 200 : 403;
        return new \WP_REST_Response($result, $status);
    }

    /**
     * POST /ams/v1/sms/status — delivery status callback.
     */
    public function handle_status_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_body_params();
        $signature = $request->get_header('X-Twilio-Signature') ?? '';
        $url = rest_url('ams/v1/sms/status');

        $handler = new WebhookHandler();
        $result = $handler->handle_status($params, $signature, $url);

        return new \WP_REST_Response($result, $result['success'] ? 200 : 403);
    }
}
