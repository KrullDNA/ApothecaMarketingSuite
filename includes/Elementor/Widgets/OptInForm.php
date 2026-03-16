<?php
/**
 * Elementor Widget: AMS Opt-In Form.
 *
 * Renders a saved AMS pop-up/embedded form inside Elementor with full style controls.
 *
 * @package Apotheca\Marketing\Elementor\Widgets
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

defined('ABSPATH') || exit;

final class OptInForm extends Widget_Base
{
    public function get_name(): string
    {
        return 'ams_opt_in_form';
    }

    public function get_title(): string
    {
        return esc_html__('AMS Opt-In Form', 'apotheca-marketing-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories(): array
    {
        return ['apotheca-marketing'];
    }

    public function get_keywords(): array
    {
        return ['form', 'opt-in', 'subscribe', 'email', 'newsletter', 'apotheca'];
    }

    protected function register_controls(): void
    {
        // Content section — form selection.
        $this->start_controls_section('section_content', [
            'label' => esc_html__('Form Selection', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('form_id', [
            'label'       => esc_html__('Select Form', 'apotheca-marketing-suite'),
            'type'        => Controls_Manager::SELECT,
            'default'     => '',
            'options'     => $this->get_form_options(),
            'description' => esc_html__('Choose a form created in Apotheca® Marketing > Forms.', 'apotheca-marketing-suite'),
        ]);

        $this->add_control('show_name_field', [
            'label'        => esc_html__('Show Name Field', 'apotheca-marketing-suite'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'label_on'     => esc_html__('Yes', 'apotheca-marketing-suite'),
            'label_off'    => esc_html__('No', 'apotheca-marketing-suite'),
        ]);

        $this->add_control('success_message', [
            'label'       => esc_html__('Success Message', 'apotheca-marketing-suite'),
            'type'        => Controls_Manager::TEXT,
            'default'     => esc_html__('Thanks for subscribing!', 'apotheca-marketing-suite'),
            'label_block' => true,
        ]);

        $this->end_controls_section();

        // Style — form container.
        $this->start_controls_section('section_style_container', [
            'label' => esc_html__('Container', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('container_bg', [
            'label'     => esc_html__('Background Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-optin-form' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('container_padding', [
            'label'      => esc_html__('Padding', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => ['{{WRAPPER}} .ams-optin-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'container_border',
            'selector' => '{{WRAPPER}} .ams-optin-form',
        ]);

        $this->add_control('container_border_radius', [
            'label'      => esc_html__('Border Radius', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .ams-optin-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'container_shadow',
            'selector' => '{{WRAPPER}} .ams-optin-form',
        ]);

        $this->end_controls_section();

        // Style — input fields.
        $this->start_controls_section('section_style_inputs', [
            'label' => esc_html__('Input Fields', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'input_typography',
            'selector' => '{{WRAPPER}} .ams-optin-form input[type="text"], {{WRAPPER}} .ams-optin-form input[type="email"]',
        ]);

        $this->add_control('input_text_color', [
            'label'     => esc_html__('Text Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-optin-form input' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('input_bg_color', [
            'label'     => esc_html__('Background Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-optin-form input' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('input_padding', [
            'label'      => esc_html__('Padding', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .ams-optin-form input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'input_border',
            'selector' => '{{WRAPPER}} .ams-optin-form input',
        ]);

        $this->end_controls_section();

        // Style — submit button.
        $this->start_controls_section('section_style_button', [
            'label' => esc_html__('Submit Button', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'button_typography',
            'selector' => '{{WRAPPER}} .ams-optin-form button',
        ]);

        $this->add_control('button_text_color', [
            'label'     => esc_html__('Text Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-optin-form button' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('button_bg_color', [
            'label'     => esc_html__('Background Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-optin-form button' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('button_hover_bg', [
            'label'     => esc_html__('Hover Background', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-optin-form button:hover' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('button_padding', [
            'label'      => esc_html__('Padding', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .ams-optin-form button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'button_border',
            'selector' => '{{WRAPPER}} .ams-optin-form button',
        ]);

        $this->add_control('button_border_radius', [
            'label'      => esc_html__('Border Radius', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .ams-optin-form button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $form_id  = (int) ($settings['form_id'] ?? 0);

        if (!$form_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p style="padding:20px;text-align:center;color:#999;">'
                    . esc_html__('Please select a form in the widget settings.', 'apotheca-marketing-suite')
                    . '</p>';
            }
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ams_forms';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $form_id));

        if (!$form) {
            return;
        }

        $config       = json_decode($form->config ?? '{}', true) ?: [];
        $show_name    = 'yes' === ($settings['show_name_field'] ?? 'yes');
        $success_msg  = esc_attr($settings['success_message'] ?? __('Thanks for subscribing!', 'apotheca-marketing-suite'));
        $button_text  = $config['button_text'] ?? __('Subscribe', 'apotheca-marketing-suite');
        $nonce_action = 'ams_form_submit_' . $form_id;

        echo '<div class="ams-optin-form" data-form-id="' . esc_attr((string) $form_id) . '" data-success="' . $success_msg . '">';
        echo '<form method="post" class="ams-optin-form__inner">';
        wp_nonce_field($nonce_action, 'ams_form_nonce');
        echo '<input type="hidden" name="ams_form_id" value="' . esc_attr((string) $form_id) . '">';

        if ($show_name) {
            echo '<input type="text" name="ams_name" placeholder="' . esc_attr__('Your name', 'apotheca-marketing-suite') . '" class="ams-optin-form__input">';
        }

        echo '<input type="email" name="ams_email" placeholder="' . esc_attr__('Your email', 'apotheca-marketing-suite') . '" required class="ams-optin-form__input">';
        echo '<button type="submit" class="ams-optin-form__button">' . esc_html($button_text) . '</button>';
        echo '</form>';
        echo '<div class="ams-optin-form__success" style="display:none;">' . esc_html($settings['success_message'] ?? '') . '</div>';
        echo '</div>';

        // Inline submission script (no external JS dependency).
        echo '<script>
(function(){
    var wrap = document.querySelector("[data-form-id=\'' . esc_js((string) $form_id) . '\']");
    if(!wrap) return;
    var form = wrap.querySelector("form");
    form.addEventListener("submit", function(e){
        e.preventDefault();
        var fd = new FormData(form);
        fd.append("action", "ams_form_submit");
        fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {method:"POST", body:fd})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.success){
                form.style.display="none";
                wrap.querySelector(".ams-optin-form__success").style.display="block";
            }
        });
    });
})();
</script>';
    }

    /**
     * Retrieve all AMS forms as dropdown options.
     */
    private function get_form_options(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ams_forms';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $forms = $wpdb->get_results("SELECT id, name FROM {$table} ORDER BY name ASC");

        $options = ['' => esc_html__('— Select —', 'apotheca-marketing-suite')];

        if ($forms) {
            foreach ($forms as $form) {
                $options[$form->id] = esc_html($form->name);
            }
        }

        return $options;
    }
}
