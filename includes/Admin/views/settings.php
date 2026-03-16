<?php
/**
 * Settings admin page.
 *
 * @package Apotheca\Marketing\Admin
 */

defined('ABSPATH') || exit;

use Apotheca\Marketing\Settings;

// Handle form submission.
if (isset($_POST['ams_save_settings']) && check_admin_referer('ams_settings_save', 'ams_settings_nonce')) {
    $new_settings = [
        'checkout_optin_enabled'    => !empty($_POST['checkout_optin_enabled']),
        'checkout_optin_label'      => sanitize_text_field(wp_unslash($_POST['checkout_optin_label'] ?? '')),
        'registration_capture'      => !empty($_POST['registration_capture']),
        'gdpr_double_optin'         => !empty($_POST['gdpr_double_optin']),
        'gdpr_consent_text'         => sanitize_textarea_field(wp_unslash($_POST['gdpr_consent_text'] ?? '')),
        'abandoned_cart_timeout'    => max(15, (int) ($_POST['abandoned_cart_timeout'] ?? 60)),
        'frequency_cap_email'       => max(1, (int) ($_POST['frequency_cap_email'] ?? 3)),
        'frequency_cap_sms'         => max(1, (int) ($_POST['frequency_cap_sms'] ?? 2)),
        'send_window_start'         => max(0, min(23, (int) ($_POST['send_window_start'] ?? 8))),
        'send_window_end'           => max(0, min(23, (int) ($_POST['send_window_end'] ?? 21))),
        'attribution_window_days'   => max(1, (int) ($_POST['attribution_window_days'] ?? 5)),
        'unsubscribe_page_title'    => sanitize_text_field(wp_unslash($_POST['unsubscribe_page_title'] ?? '')),
        'unsubscribe_page_message'  => sanitize_textarea_field(wp_unslash($_POST['unsubscribe_page_message'] ?? '')),
        'remove_data_on_uninstall'  => !empty($_POST['remove_data_on_uninstall']),
    ];

    Settings::update($new_settings);
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'apotheca-marketing-suite') . '</p></div>';
}

$s = Settings::all();
?>

<form method="post" action="">
    <?php wp_nonce_field('ams_settings_save', 'ams_settings_nonce'); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Checkout Opt-In', 'apotheca-marketing-suite'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="checkout_optin_enabled" value="1" <?php checked($s['checkout_optin_enabled']); ?> />
                    <?php esc_html_e('Show marketing opt-in checkbox on checkout', 'apotheca-marketing-suite'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="checkout_optin_label"><?php esc_html_e('Checkout Label', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <input type="text" name="checkout_optin_label" id="checkout_optin_label" class="large-text" value="<?php echo esc_attr($s['checkout_optin_label']); ?>" />
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Registration Capture', 'apotheca-marketing-suite'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="registration_capture" value="1" <?php checked($s['registration_capture']); ?> />
                    <?php esc_html_e('Capture subscribers on WooCommerce account registration', 'apotheca-marketing-suite'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Double Opt-In', 'apotheca-marketing-suite'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="gdpr_double_optin" value="1" <?php checked($s['gdpr_double_optin']); ?> />
                    <?php esc_html_e('Require email confirmation before activating subscriber (GDPR recommended)', 'apotheca-marketing-suite'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="gdpr_consent_text"><?php esc_html_e('Consent Text', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <textarea name="gdpr_consent_text" id="gdpr_consent_text" rows="3" class="large-text"><?php echo esc_textarea($s['gdpr_consent_text']); ?></textarea>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="abandoned_cart_timeout"><?php esc_html_e('Abandoned Cart Timeout (min)', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <input type="number" name="abandoned_cart_timeout" id="abandoned_cart_timeout" value="<?php echo esc_attr((string) $s['abandoned_cart_timeout']); ?>" min="15" step="1" class="small-text" />
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="frequency_cap_email"><?php esc_html_e('Email Frequency Cap (per 24h)', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <input type="number" name="frequency_cap_email" id="frequency_cap_email" value="<?php echo esc_attr((string) $s['frequency_cap_email']); ?>" min="1" class="small-text" />
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="frequency_cap_sms"><?php esc_html_e('SMS Frequency Cap (per 24h)', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <input type="number" name="frequency_cap_sms" id="frequency_cap_sms" value="<?php echo esc_attr((string) $s['frequency_cap_sms']); ?>" min="1" class="small-text" />
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Send Window', 'apotheca-marketing-suite'); ?></th>
            <td>
                <input type="number" name="send_window_start" value="<?php echo esc_attr((string) $s['send_window_start']); ?>" min="0" max="23" class="small-text" />
                <?php esc_html_e('to', 'apotheca-marketing-suite'); ?>
                <input type="number" name="send_window_end" value="<?php echo esc_attr((string) $s['send_window_end']); ?>" min="0" max="23" class="small-text" />
                <p class="description"><?php esc_html_e('Restrict sends to this hour range in subscriber local time.', 'apotheca-marketing-suite'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="attribution_window_days"><?php esc_html_e('Attribution Window (days)', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <input type="number" name="attribution_window_days" id="attribution_window_days" value="<?php echo esc_attr((string) $s['attribution_window_days']); ?>" min="1" class="small-text" />
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="unsubscribe_page_title"><?php esc_html_e('Unsubscribe Page Title', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <input type="text" name="unsubscribe_page_title" id="unsubscribe_page_title" class="regular-text" value="<?php echo esc_attr($s['unsubscribe_page_title']); ?>" />
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="unsubscribe_page_message"><?php esc_html_e('Unsubscribe Message', 'apotheca-marketing-suite'); ?></label></th>
            <td>
                <textarea name="unsubscribe_page_message" id="unsubscribe_page_message" rows="3" class="large-text"><?php echo esc_textarea($s['unsubscribe_page_message']); ?></textarea>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Uninstall Data', 'apotheca-marketing-suite'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="remove_data_on_uninstall" value="1" <?php checked($s['remove_data_on_uninstall'] ?? false); ?> />
                    <?php esc_html_e('Remove all plugin data when uninstalling (cannot be undone)', 'apotheca-marketing-suite'); ?>
                </label>
            </td>
        </tr>
    </table>

    <?php submit_button(__('Save Settings', 'apotheca-marketing-suite'), 'primary', 'ams_save_settings'); ?>
</form>
