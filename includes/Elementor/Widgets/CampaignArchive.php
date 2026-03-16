<?php
/**
 * Elementor Widget: AMS Campaign Archive.
 *
 * Grid/list display of past campaigns with card styling controls.
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

final class CampaignArchive extends Widget_Base
{
    public function get_name(): string
    {
        return 'ams_campaign_archive';
    }

    public function get_title(): string
    {
        return esc_html__('AMS Campaign Archive', 'apotheca-marketing-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-archive';
    }

    public function get_categories(): array
    {
        return ['apotheca-marketing'];
    }

    public function get_keywords(): array
    {
        return ['campaign', 'archive', 'newsletter', 'email', 'apotheca'];
    }

    protected function register_controls(): void
    {
        // Content section.
        $this->start_controls_section('section_content', [
            'label' => esc_html__('Content', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('layout', [
            'label'   => esc_html__('Layout', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'grid',
            'options' => [
                'grid' => esc_html__('Grid', 'apotheca-marketing-suite'),
                'list' => esc_html__('List', 'apotheca-marketing-suite'),
            ],
        ]);

        $this->add_control('columns', [
            'label'     => esc_html__('Columns', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::SELECT,
            'default'   => '3',
            'options'   => [
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ],
            'condition' => ['layout' => 'grid'],
        ]);

        $this->add_control('count', [
            'label'   => esc_html__('Number of Campaigns', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 6,
            'min'     => 1,
            'max'     => 50,
        ]);

        $this->add_control('show_date', [
            'label'   => esc_html__('Show Date', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_subject', [
            'label'   => esc_html__('Show Subject Line', 'apotheca-marketing-suite'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('empty_message', [
            'label'       => esc_html__('Empty Message', 'apotheca-marketing-suite'),
            'type'        => Controls_Manager::TEXT,
            'default'     => esc_html__('No campaigns published yet.', 'apotheca-marketing-suite'),
            'label_block' => true,
        ]);

        $this->end_controls_section();

        // Style — card.
        $this->start_controls_section('section_style_card', [
            'label' => esc_html__('Card', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_bg', [
            'label'     => esc_html__('Background Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-campaign-card' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_padding', [
            'label'      => esc_html__('Padding', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .ams-campaign-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .ams-campaign-card',
        ]);

        $this->add_control('card_border_radius', [
            'label'      => esc_html__('Border Radius', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .ams-campaign-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .ams-campaign-card',
        ]);

        $this->add_responsive_control('card_gap', [
            'label'      => esc_html__('Gap', 'apotheca-marketing-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .ams-campaign-grid' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        // Style — typography.
        $this->start_controls_section('section_style_typography', [
            'label' => esc_html__('Typography', 'apotheca-marketing-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'label'    => esc_html__('Title', 'apotheca-marketing-suite'),
            'selector' => '{{WRAPPER}} .ams-campaign-card__title',
        ]);

        $this->add_control('title_color', [
            'label'     => esc_html__('Title Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-campaign-card__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'date_typography',
            'label'    => esc_html__('Date', 'apotheca-marketing-suite'),
            'selector' => '{{WRAPPER}} .ams-campaign-card__date',
        ]);

        $this->add_control('date_color', [
            'label'     => esc_html__('Date Color', 'apotheca-marketing-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ams-campaign-card__date' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $campaigns = $this->get_campaigns((int) ($settings['count'] ?? 6));

        if (empty($campaigns)) {
            echo '<p class="ams-campaign-archive__empty">' . esc_html($settings['empty_message'] ?? '') . '</p>';
            return;
        }

        $layout  = $settings['layout'] ?? 'grid';
        $columns = (int) ($settings['columns'] ?? 3);
        $class   = 'grid' === $layout
            ? 'ams-campaign-grid ams-campaign-grid--cols-' . $columns
            : 'ams-campaign-list';

        echo '<div class="' . esc_attr($class) . '">';
        foreach ($campaigns as $campaign) {
            $this->render_card($campaign, $settings);
        }
        echo '</div>';
    }

    private function render_card(object $campaign, array $settings): void
    {
        echo '<div class="ams-campaign-card">';
        echo '<div class="ams-campaign-card__title">' . esc_html($campaign->name) . '</div>';

        if ('yes' === ($settings['show_subject'] ?? 'yes') && !empty($campaign->subject)) {
            echo '<div class="ams-campaign-card__subject">' . esc_html($campaign->subject) . '</div>';
        }

        if ('yes' === ($settings['show_date'] ?? 'yes') && !empty($campaign->sent_at)) {
            echo '<div class="ams-campaign-card__date">' . esc_html(date_i18n(get_option('date_format'), strtotime($campaign->sent_at))) . '</div>';
        }

        echo '</div>';
    }

    /**
     * Get sent campaigns (cached 15 minutes).
     */
    private function get_campaigns(int $limit): array
    {
        $cache_key = 'ams_campaign_archive_' . $limit;
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ams_campaigns';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT name, subject, sent_at FROM {$table} WHERE status = 'sent' ORDER BY sent_at DESC LIMIT %d",
            $limit
        ));

        $results = $results ?: [];
        set_transient($cache_key, $results, 15 * MINUTE_IN_SECONDS);

        return $results;
    }
}
