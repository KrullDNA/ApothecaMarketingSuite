<?php
/**
 * Elementor Widget: AMS Subscriber Count Badge.
 *
 * Displays a cached subscriber count with full typography controls.
 *
 * @package Apotheca\Marketing\Elementor\Widgets
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;

defined('ABSPATH') || exit;

final class SubscriberCountBadge extends Widget_Base
{
    public function get_name(): string
    {
        return 'ams_subscriber_count_badge';
    }

    public function get_title(): string
    {
        return esc_html__('AMS Subscriber Count', 'apotheca-marketing-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-counter';
    }

    public function get_categories(): array
    {
        return ['apotheca-marketing'];
    }

    public function get_keywords(): array
    {
        return ['subscriber', 'count', 'badge', 'social proof', 'apotheca'];
    }

    protected function register_controls(): void
    {
        // Content section.
        $this->start_controls_section('section_content', [
            'label' => esc_html__('Content', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('prefix_text', [
            'label'       => esc_html__('Prefix Text', 'apotheca-marketing-suite'),
            'type'        => Controls_Manager::TEXT,
            'default'     => esc_html__('Join', 'apotheca-marketing-suite'),
            'label_block' => true,
        ]);

        $this->add_control('suffix_text', [
            'label'       => esc_html__('Suffix Text', 'apotheca-marketing-suite'),
            'type'        => Controls_Manager::TEXT,
            'default'     => esc_html__('subscribers', 'apotheca-marketing-suite'),
            'label_block' => true,
        ]);

        $this->add_control('number_format', [
            'label'   => esc_html__('Number Format', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'formatted',
            'options' => [
                'formatted' => esc_html__('1,234', 'apotheca-marketing-suite'),
                'rounded'   => esc_html__('1.2k', 'apotheca-marketing-suite'),
                'raw'       => esc_html__('1234', 'apotheca-marketing-suite'),
            ],
        ]);

        $this->add_control('alignment', [
            'label'   => esc_html__('Alignment', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left'   => ['title' => esc_html__('Left', 'apotheca-marketing-suite'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => esc_html__('Center', 'apotheca-marketing-suite'), 'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => esc_html__('Right', 'apotheca-marketing-suite'), 'icon' => 'eicon-text-align-right'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .ams-subscriber-badge' => 'text-align: {{VALUE}};'],
        ]);

        $this->end_controls_section();

        // Style — count number.
        $this->start_controls_section('section_style_number', [
            'label' => esc_html__('Count Number', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'number_typography',
            'selector' => '{{WRAPPER}} .ams-subscriber-badge__count',
        ]);

        $this->add_control('number_color', [
            'label'     => esc_html__('Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-subscriber-badge__count' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'     => 'number_shadow',
            'selector' => '{{WRAPPER}} .ams-subscriber-badge__count',
        ]);

        $this->end_controls_section();

        // Style — prefix/suffix text.
        $this->start_controls_section('section_style_text', [
            'label' => esc_html__('Text', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'text_typography',
            'selector' => '{{WRAPPER}} .ams-subscriber-badge__text',
        ]);

        $this->add_control('text_color', [
            'label'     => esc_html__('Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-subscriber-badge__text' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $count    = $this->get_subscriber_count();
        $display  = $this->format_count($count, $settings['number_format'] ?? 'formatted');
        $prefix   = $settings['prefix_text'] ?? '';
        $suffix   = $settings['suffix_text'] ?? '';

        echo '<div class="ams-subscriber-badge">';
        if ($prefix) {
            echo '<span class="ams-subscriber-badge__text">' . esc_html($prefix) . '</span> ';
        }
        echo '<span class="ams-subscriber-badge__count">' . esc_html($display) . '</span>';
        if ($suffix) {
            echo ' <span class="ams-subscriber-badge__text">' . esc_html($suffix) . '</span>';
        }
        echo '</div>';
    }

    /**
     * Get cached subscriber count (15-minute transient).
     */
    private function get_subscriber_count(): int
    {
        $cached = get_transient('ams_subscriber_count_badge');
        if (false !== $cached) {
            return (int) $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ams_subscribers';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'subscribed'");

        set_transient('ams_subscriber_count_badge', $count, 15 * MINUTE_IN_SECONDS);

        return $count;
    }

    private function format_count(int $count, string $format): string
    {
        return match ($format) {
            'rounded' => $count >= 1000 ? round($count / 1000, 1) . 'k' : (string) $count,
            'raw'     => (string) $count,
            default   => number_format($count),
        };
    }
}
