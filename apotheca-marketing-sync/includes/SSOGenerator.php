<?php
/**
 * SSO link generator — adds admin toolbar link to Marketing Suite.
 *
 * Creates time-limited, HMAC-signed SSO tokens for cross-domain login.
 *
 * @package Apotheca\MarketingSync
 */

declare(strict_types=1);

namespace Apotheca\MarketingSync;

defined('ABSPATH') || exit;

final class SSOGenerator
{
    public function __construct()
    {
        add_action('admin_bar_menu', [$this, 'add_toolbar_link'], 90);
    }

    /**
     * Add Marketing Suite link to admin toolbar.
     */
    public function add_toolbar_link(\WP_Admin_Bar $admin_bar): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $endpoint_url = Settings::get('endpoint_url', '');
        if (!$endpoint_url) {
            return;
        }

        $sso_url = self::generate_link();
        if (!$sso_url) {
            return;
        }

        $admin_bar->add_node([
            'id'    => 'ams-marketing-suite',
            'title' => 'Marketing Suite',
            'href'  => $sso_url,
            'meta'  => [
                'target' => '_blank',
                'title'  => 'Open Apotheca Marketing Suite',
            ],
        ]);
    }

    /**
     * Generate an SSO link to the marketing subdomain.
     *
     * @return string|null The SSO URL or null if not configured.
     */
    public static function generate_link(): ?string
    {
        $endpoint_url = Settings::get('endpoint_url', '');
        $shared_secret = Settings::get_shared_secret();

        if (!$endpoint_url || !$shared_secret) {
            return null;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return null;
        }

        $token_data = [
            'user_id' => $user->ID,
            'email'   => $user->user_email,
            'name'    => $user->display_name,
            'expires' => time() + 60,
            'nonce'   => wp_generate_password(16, false),
        ];

        $token = base64_encode(wp_json_encode($token_data));
        $signature = hash_hmac('sha256', $token, $shared_secret);

        return rtrim($endpoint_url, '/') . '/ams-sso/?' . http_build_query([
            'token' => $token,
            'sig'   => $signature,
        ]);
    }
}
