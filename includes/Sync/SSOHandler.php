<?php
/**
 * SSO endpoint handler on the marketing subdomain.
 *
 * Validates HMAC-signed tokens from the main store, auto-creates
 * admin users, and logs them in.
 *
 * @package Apotheca\Marketing\Sync
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Sync;

use Apotheca\Marketing\Settings;

defined('ABSPATH') || exit;

final class SSOHandler
{
    public function __construct()
    {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_endpoint']);
    }

    /**
     * Register the /ams-sso/ rewrite rule.
     */
    public function register_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^ams-sso/?$',
            'index.php?ams_sso=1',
            'top'
        );
        add_filter('query_vars', function (array $vars): array {
            $vars[] = 'ams_sso';
            return $vars;
        });
    }

    /**
     * Handle the SSO login request.
     */
    public function handle_endpoint(): void
    {
        if (!get_query_var('ams_sso')) {
            return;
        }

        $token_b64 = sanitize_text_field($_GET['token'] ?? '');
        $signature = sanitize_text_field($_GET['sig'] ?? '');

        if (!$token_b64 || !$signature) {
            $this->redirect_with_error();
            exit;
        }

        // Get shared secret.
        $shared_secret = $this->get_shared_secret();
        if (!$shared_secret) {
            $this->redirect_with_error();
            exit;
        }

        // Verify HMAC signature.
        $expected = hash_hmac('sha256', $token_b64, $shared_secret);
        if (!hash_equals($expected, $signature)) {
            $this->redirect_with_error();
            exit;
        }

        // Decode token.
        $token_json = base64_decode($token_b64);
        if (!$token_json) {
            $this->redirect_with_error();
            exit;
        }

        $token_data = json_decode($token_json, true);
        if (!is_array($token_data)) {
            $this->redirect_with_error();
            exit;
        }

        // Verify expiry.
        $expires = (int) ($token_data['expires'] ?? 0);
        if ($expires < time()) {
            $this->redirect_with_error();
            exit;
        }

        // Verify nonce is not replayed.
        $nonce = $token_data['nonce'] ?? '';
        if (!$nonce || $this->is_nonce_used($nonce)) {
            $this->redirect_with_error();
            exit;
        }

        // Mark nonce as used.
        $this->mark_nonce_used($nonce);

        $email = sanitize_email($token_data['email'] ?? '');
        $main_user_id = (int) ($token_data['user_id'] ?? 0);
        $display_name = sanitize_text_field($token_data['name'] ?? '');

        if (!$email || !$main_user_id) {
            $this->redirect_with_error();
            exit;
        }

        // Find or create the local user.
        $user = get_user_by('email', $email);

        if (!$user) {
            // Auto-create administrator account.
            $username = sanitize_user(str_replace('@', '_at_', $email));
            $password = wp_generate_password(24, true);

            $user_id = wp_insert_user([
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password,
                'display_name' => $display_name ?: $username,
                'role'         => 'administrator',
            ]);

            if (is_wp_error($user_id)) {
                $this->redirect_with_error();
                exit;
            }

            // Link to main site user.
            update_user_meta($user_id, 'ams_main_site_user_id', $main_user_id);

            $user = get_user_by('id', $user_id);
        } else {
            // Update the link if not set.
            if (!get_user_meta($user->ID, 'ams_main_site_user_id', true)) {
                update_user_meta($user->ID, 'ams_main_site_user_id', $main_user_id);
            }
        }

        if (!$user) {
            $this->redirect_with_error();
            exit;
        }

        // Log the user in.
        wp_set_auth_cookie($user->ID, false);
        wp_set_current_user($user->ID);

        // Redirect to dashboard.
        wp_safe_redirect(admin_url('admin.php?page=ams-dashboard'));
        exit;
    }

    /**
     * Get the decrypted shared secret.
     */
    private function get_shared_secret(): string
    {
        // Try plain text first (for simple setups).
        $plain = Settings::get('sync_shared_secret', '');
        if ($plain && strlen($plain) > 0 && strlen($plain) < 100) {
            return $plain;
        }

        // Try encrypted version.
        $encrypted = Settings::get('sync_shared_secret_encrypted', '');
        if (!$encrypted) {
            return $plain ?: '';
        }

        $key = defined('AUTH_KEY') ? AUTH_KEY : 'ams-sync-fallback-key';
        $data = base64_decode($encrypted);
        if (false === $data || strlen($data) < 17) {
            return '';
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted ?: '';
    }

    /**
     * Check if a nonce has already been used.
     */
    private function is_nonce_used(string $nonce): bool
    {
        $used = get_transient('ams_sso_nonce_' . $nonce);
        return (bool) $used;
    }

    /**
     * Mark a nonce as used (120 second TTL).
     */
    private function mark_nonce_used(string $nonce): void
    {
        set_transient('ams_sso_nonce_' . $nonce, 1, 120);
    }

    /**
     * Redirect to login with error.
     */
    private function redirect_with_error(): void
    {
        wp_safe_redirect(add_query_arg('ams_sso_error', '1', wp_login_url()));
        exit;
    }
}
