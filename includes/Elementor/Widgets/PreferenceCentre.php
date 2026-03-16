<?php
/**
 * Elementor Widget: AMS Preference Centre.
 *
 * Lets subscribers manage their email/SMS preferences via a token-identified cookie.
 *
 * @package Apotheca\Marketing\Elementor\Widgets
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

defined('ABSPATH') || exit;

final class PreferenceCentre extends Widget_Base
{
    public function get_name(): string
    {
        return 'ams_preference_centre';
    }

    public function get_title(): string
    {
        return esc_html__('AMS Preference Centre', 'apotheca-marketing-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-preferences';
    }

    public function get_categories(): array
    {
        return ['apotheca-marketing'];
    }

    public function get_keywords(): array
    {
        return ['preferences', 'unsubscribe', 'manage', 'email', 'sms', 'apotheca'];
    }

    protected function register_controls(): void
    {
        // Content section.
        $this->start_controls_section('section_content', [
            'label' => esc_html__('Content', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('heading_text', [
            'label'       => esc_html__('Heading', 'apotheca-marketing-suite'),
            'type'        => Controls_Manager::TEXT,
            'default'     => esc_html__('Manage Your Preferences', 'apotheca-marketing-suite'),
            'label_block' => true,
        ]);

        $this->add_control('show_email_toggle', [
            'label'   => esc_html__('Show Email Toggle', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_sms_toggle', [
            'label'   => esc_html__('Show SMS Toggle', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('save_button_text', [
            'label'   => esc_html__('Save Button Text', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__('Save Preferences', 'apotheca-marketing-suite'),
        ]);

        $this->add_control('success_message', [
            'label'   => esc_html__('Success Message', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__('Preferences saved!', 'apotheca-marketing-suite'),
        ]);

        $this->add_control('not_identified_message', [
            'label'       => esc_html__('Not Identified Message', 'apotheca-marketing-suite'),
            'type'        => Controls_Manager::TEXTAREA,
            'default'     => esc_html__('We could not identify your subscription. Please use the link in your most recent email.', 'apotheca-marketing-suite'),
            'label_block' => true,
        ]);

        $this->end_controls_section();

        // Style — container.
        $this->start_controls_section('section_style_container', [
            'label' => esc_html__('Container', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('container_bg', [
            'label'     => esc_html__('Background', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-pref-centre' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('container_padding', [
            'label'      => esc_html__('Padding', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .ams-pref-centre' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'container_border',
            'selector' => '{{WRAPPER}} .ams-pref-centre',
        ]);

        $this->end_controls_section();

        // Style — heading.
        $this->start_controls_section('section_style_heading', [
            'label' => esc_html__('Heading', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'heading_typography',
            'selector' => '{{WRAPPER}} .ams-pref-centre__heading',
        ]);

        $this->add_control('heading_color', [
            'label'     => esc_html__('Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-pref-centre__heading' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();

        // Style — button.
        $this->start_controls_section('section_style_button', [
            'label' => esc_html__('Save Button', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'button_typography',
            'selector' => '{{WRAPPER}} .ams-pref-centre__save',
        ]);

        $this->add_control('button_color', [
            'label'     => esc_html__('Text Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-pref-centre__save' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('button_bg', [
            'label'     => esc_html__('Background', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-pref-centre__save' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('button_padding', [
            'label'      => esc_html__('Padding', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .ams-pref-centre__save' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Identify subscriber from token cookie or query string.
        $token = sanitize_text_field($_GET['ams_token'] ?? $_COOKIE['ams_subscriber_token'] ?? '');
        $subscriber = null;

        if ($token) {
            $subscriber = $this->get_subscriber_by_token($token);
        }

        echo '<div class="ams-pref-centre">';
        echo '<h3 class="ams-pref-centre__heading">' . esc_html($settings['heading_text'] ?? '') . '</h3>';

        if (!$subscriber) {
            echo '<p class="ams-pref-centre__not-identified">' . esc_html($settings['not_identified_message'] ?? '') . '</p>';
            echo '</div>';
            return;
        }

        $nonce = wp_create_nonce('ams_pref_centre_' . $subscriber->id);

        echo '<form class="ams-pref-centre__form" data-subscriber="' . esc_attr((string) $subscriber->id) . '">';
        echo '<input type="hidden" name="ams_pref_nonce" value="' . esc_attr($nonce) . '">';
        echo '<input type="hidden" name="subscriber_id" value="' . esc_attr((string) $subscriber->id) . '">';

        if ('yes' === ($settings['show_email_toggle'] ?? 'yes')) {
            $email_checked = 'subscribed' === $subscriber->status ? 'checked' : '';
            echo '<label class="ams-pref-centre__toggle">';
            echo '<input type="checkbox" name="email_subscribed" value="1" ' . $email_checked . '>';
            echo '<span>' . esc_html__('Email communications', 'apotheca-marketing-suite') . '</span>';
            echo '</label>';
        }

        if ('yes' === ($settings['show_sms_toggle'] ?? 'yes')) {
            $sms_checked = !empty($subscriber->sms_consent) ? 'checked' : '';
            echo '<label class="ams-pref-centre__toggle">';
            echo '<input type="checkbox" name="sms_subscribed" value="1" ' . $sms_checked . '>';
            echo '<span>' . esc_html__('SMS communications', 'apotheca-marketing-suite') . '</span>';
            echo '</label>';
        }

        echo '<button type="submit" class="ams-pref-centre__save">' . esc_html($settings['save_button_text'] ?? 'Save') . '</button>';
        echo '<div class="ams-pref-centre__success" style="display:none;">' . esc_html($settings['success_message'] ?? '') . '</div>';
        echo '</form>';
        echo '</div>';

        // Inline submission script.
        echo '<script>
(function(){
    var form = document.querySelector(".ams-pref-centre__form");
    if(!form) return;
    form.addEventListener("submit", function(e){
        e.preventDefault();
        var fd = new FormData(form);
        fd.append("action", "ams_update_preferences");
        fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {method:"POST", body:fd})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.success){
                form.querySelector(".ams-pref-centre__success").style.display="block";
                setTimeout(function(){ form.querySelector(".ams-pref-centre__success").style.display="none"; }, 3000);
            }
        });
    });
})();
</script>';
    }

    /**
     * Look up subscriber by unsubscribe token.
     */
    private function get_subscriber_by_token(string $token): ?object
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE unsubscribe_token = %s LIMIT 1",
            $token
        ));

        return $row ?: null;
    }
}
