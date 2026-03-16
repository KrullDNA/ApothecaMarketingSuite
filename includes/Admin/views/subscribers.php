<?php
/**
 * Subscribers list admin page.
 *
 * @package Apotheca\Marketing\Admin
 */

defined('ABSPATH') || exit;

use Apotheca\Marketing\Subscriber\Repository;

$repo = new Repository();

$current_page = max(1, (int) ($_GET['paged'] ?? 1));
$search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
$status_filter = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));

$result = $repo->list([
    'page'     => $current_page,
    'per_page' => 20,
    'search'   => $search,
    'status'   => $status_filter,
]);

$subscribers = $result['items'];
$total = $result['total'];
$total_pages = (int) ceil($total / 20);
?>

<form method="get" action="">
    <input type="hidden" name="page" value="ams-subscribers" />
    <p class="search-box">
        <label class="screen-reader-text" for="ams-subscriber-search"><?php esc_html_e('Search Subscribers', 'apotheca-marketing-suite'); ?></label>
        <input type="search" id="ams-subscriber-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by email or name...', 'apotheca-marketing-suite'); ?>" />
        <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search', 'apotheca-marketing-suite'); ?>" />
    </p>
</form>

<table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
    <thead>
        <tr>
            <th scope="col"><?php esc_html_e('Email', 'apotheca-marketing-suite'); ?></th>
            <th scope="col"><?php esc_html_e('Name', 'apotheca-marketing-suite'); ?></th>
            <th scope="col"><?php esc_html_e('Status', 'apotheca-marketing-suite'); ?></th>
            <th scope="col"><?php esc_html_e('Source', 'apotheca-marketing-suite'); ?></th>
            <th scope="col"><?php esc_html_e('Orders', 'apotheca-marketing-suite'); ?></th>
            <th scope="col"><?php esc_html_e('Total Spent', 'apotheca-marketing-suite'); ?></th>
            <th scope="col"><?php esc_html_e('RFM', 'apotheca-marketing-suite'); ?></th>
            <th scope="col"><?php esc_html_e('Subscribed', 'apotheca-marketing-suite'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($subscribers)) : ?>
            <tr>
                <td colspan="8"><?php esc_html_e('No subscribers found.', 'apotheca-marketing-suite'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($subscribers as $sub) : ?>
                <tr>
                    <td><strong><?php echo esc_html($sub->email); ?></strong></td>
                    <td><?php echo esc_html(trim($sub->first_name . ' ' . $sub->last_name)); ?></td>
                    <td>
                        <span class="ams-status-badge ams-status-<?php echo esc_attr($sub->status); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $sub->status))); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(ucfirst($sub->source)); ?></td>
                    <td><?php echo esc_html((string) $sub->total_orders); ?></td>
                    <td><?php echo esc_html(function_exists('wc_price') ? wc_price((float) $sub->total_spent) : '$' . number_format((float) $sub->total_spent, 2)); ?></td>
                    <td><?php echo esc_html($sub->rfm_segment ?: '—'); ?></td>
                    <td><?php echo $sub->subscribed_at ? esc_html(wp_date(get_option('date_format'), strtotime($sub->subscribed_at))) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo wp_kses_post(paginate_links([
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'current' => $current_page,
                'total'   => $total_pages,
                'type'    => 'plain',
            ]));
            ?>
        </div>
    </div>
<?php endif; ?>
