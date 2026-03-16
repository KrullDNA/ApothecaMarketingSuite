<?php
/**
 * Dashboard admin page.
 *
 * @package Apotheca\Marketing\Admin
 */

defined('ABSPATH') || exit;

use Apotheca\Marketing\Subscriber\Repository;

$repo = new Repository();
$stats = $repo->list(['per_page' => 1]);
$total_subscribers = $stats['total'];
?>

<div class="ams-dashboard-overview">
    <div class="ams-stats-cards" style="display:flex;gap:20px;margin:20px 0;">
        <div class="ams-stat-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;flex:1;">
            <h3 style="margin:0 0 8px;color:#646970;"><?php esc_html_e('Total Subscribers', 'apotheca-marketing-suite'); ?></h3>
            <p style="font-size:28px;font-weight:600;margin:0;"><?php echo esc_html(number_format_i18n($total_subscribers)); ?></p>
        </div>
        <div class="ams-stat-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;flex:1;">
            <h3 style="margin:0 0 8px;color:#646970;"><?php esc_html_e('Active Flows', 'apotheca-marketing-suite'); ?></h3>
            <p style="font-size:28px;font-weight:600;margin:0;" id="ams-active-flows">—</p>
        </div>
        <div class="ams-stat-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;flex:1;">
            <h3 style="margin:0 0 8px;color:#646970;"><?php esc_html_e('Campaigns Sent', 'apotheca-marketing-suite'); ?></h3>
            <p style="font-size:28px;font-weight:600;margin:0;" id="ams-campaigns-sent">—</p>
        </div>
    </div>

    <p><?php esc_html_e('Welcome to Apotheca® Marketing Suite. Use the menu to manage your subscribers, flows, campaigns, and more.', 'apotheca-marketing-suite'); ?></p>
</div>
