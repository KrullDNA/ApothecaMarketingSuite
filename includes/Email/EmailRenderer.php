<?php
/**
 * Email HTML renderer — converts editor blocks into email-safe HTML.
 *
 * Features:
 * - Montserrat font with 3-tier fallback (webmail @import, Outlook Century Gothic, plain text)
 * - MSO conditional comments for Outlook table-based layout
 * - Mobile responsive with @media queries preserved in <head>
 * - CSS inlining via CssInliner
 *
 * @package Apotheca\Marketing\Email
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Email;

defined('ABSPATH') || exit;

final class EmailRenderer
{
    private CssInliner $inliner;

    public function __construct()
    {
        $this->inliner = new CssInliner();
    }

    /**
     * Render editor blocks into final email HTML.
     *
     * @param array  $blocks       Array of block data from the editor.
     * @param array  $global_style Global style settings (bg color, text color, etc.).
     * @return string Complete email HTML document.
     */
    public function render(array $blocks, array $global_style = []): string
    {
        $bg_color    = $global_style['bg_color'] ?? '#f4f4f5';
        $content_bg  = $global_style['content_bg'] ?? '#ffffff';
        $text_color  = $global_style['text_color'] ?? '#1f2937';
        $link_color  = $global_style['link_color'] ?? '#7c3aed';
        $content_width = $global_style['content_width'] ?? '600';
        $preheader   = $global_style['preheader'] ?? '';

        $blocks_html = '';
        foreach ($blocks as $block) {
            $blocks_html .= $this->render_block($block, $global_style);
        }

        $html = $this->get_document_wrapper(
            $blocks_html,
            $bg_color,
            $content_bg,
            $text_color,
            $link_color,
            (int) $content_width,
            $preheader
        );

        // Inline CSS for maximum email client compatibility.
        return $this->inliner->inline($html);
    }

    /**
     * Render a single block.
     */
    private function render_block(array $block, array $global_style): string
    {
        $type = $block['type'] ?? '';

        return match ($type) {
            'text'      => $this->render_text_block($block),
            'image'     => $this->render_image_block($block),
            'button'    => $this->render_button_block($block),
            'divider'   => $this->render_divider_block($block),
            'spacer'    => $this->render_spacer_block($block),
            'columns'   => $this->render_columns_block($block, $global_style),
            'product'   => $this->render_product_block($block),
            'social'    => $this->render_social_block($block),
            'reviews'   => $this->render_reviews_block($block),
            'header'    => $this->render_header_block($block),
            'footer'    => $this->render_footer_block($block),
            'html'      => $this->render_html_block($block),
            default     => '',
        };
    }

    /**
     * Text block — rich text content.
     */
    private function render_text_block(array $block): string
    {
        $content   = $block['content'] ?? '';
        $padding   = $block['padding'] ?? '10px 0';
        $align     = $block['align'] ?? 'left';
        $font_size = $block['font_size'] ?? '16px';

        return '<tr><td style="padding:' . esc_attr($padding) . ';text-align:' . esc_attr($align) . ';font-size:' . esc_attr($font_size) . ';">'
            . wp_kses_post($content)
            . '</td></tr>';
    }

    /**
     * Image block.
     */
    private function render_image_block(array $block): string
    {
        $src    = esc_url($block['src'] ?? '');
        $alt    = esc_attr($block['alt'] ?? '');
        $width  = esc_attr($block['width'] ?? '100%');
        $align  = $block['align'] ?? 'center';
        $link   = $block['link'] ?? '';
        $padding = $block['padding'] ?? '10px 0';

        $img = '<img src="' . $src . '" alt="' . $alt . '" width="' . $width . '" style="display:block;max-width:100%;height:auto;border:0;">';

        if ($link) {
            $img = '<a href="' . esc_url($link) . '" target="_blank">' . $img . '</a>';
        }

        return '<tr><td style="padding:' . esc_attr($padding) . ';text-align:' . esc_attr($align) . ';">'
            . $img
            . '</td></tr>';
    }

    /**
     * Button block.
     */
    private function render_button_block(array $block): string
    {
        $text     = esc_html($block['text'] ?? 'Click Here');
        $url      = esc_url($block['url'] ?? '#');
        $bg       = $block['bg_color'] ?? '#7c3aed';
        $color    = $block['text_color'] ?? '#ffffff';
        $radius   = $block['border_radius'] ?? '4px';
        $padding  = $block['padding'] ?? '10px 0';
        $align    = $block['align'] ?? 'center';
        $btn_pad  = $block['button_padding'] ?? '12px 30px';

        // VML button for Outlook support.
        $vml = '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" '
            . 'href="' . $url . '" style="height:auto;v-text-anchor:middle;width:auto;" '
            . 'arcsize="10%" strokecolor="' . esc_attr($bg) . '" fillcolor="' . esc_attr($bg) . '">'
            . '<w:anchorlock/><center style="color:' . esc_attr($color) . ';font-family:Century Gothic,sans-serif;font-size:16px;font-weight:bold;">'
            . $text . '</center></v:roundrect><![endif]-->';

        $html_btn = '<!--[if !mso]><!--><a href="' . $url . '" target="_blank" '
            . 'style="display:inline-block;padding:' . esc_attr($btn_pad) . ';background-color:' . esc_attr($bg) . ';'
            . 'color:' . esc_attr($color) . ';text-decoration:none;border-radius:' . esc_attr($radius) . ';'
            . 'font-family:Montserrat,Century Gothic,sans-serif;font-size:16px;font-weight:600;">'
            . $text . '</a><!--<![endif]-->';

        return '<tr><td style="padding:' . esc_attr($padding) . ';text-align:' . esc_attr($align) . ';">'
            . $vml . $html_btn
            . '</td></tr>';
    }

    /**
     * Divider block.
     */
    private function render_divider_block(array $block): string
    {
        $color   = $block['color'] ?? '#e5e7eb';
        $width   = $block['width'] ?? '100%';
        $height  = $block['height'] ?? '1px';
        $padding = $block['padding'] ?? '10px 0';

        return '<tr><td style="padding:' . esc_attr($padding) . ';">'
            . '<hr style="border:0;height:' . esc_attr($height) . ';background:' . esc_attr($color) . ';width:' . esc_attr($width) . ';margin:0 auto;">'
            . '</td></tr>';
    }

    /**
     * Spacer block.
     */
    private function render_spacer_block(array $block): string
    {
        $height = $block['height'] ?? '20px';
        return '<tr><td style="height:' . esc_attr($height) . ';line-height:' . esc_attr($height) . ';font-size:1px;">&nbsp;</td></tr>';
    }

    /**
     * Columns block — multi-column layout with MSO table fallback.
     */
    private function render_columns_block(array $block, array $global_style): string
    {
        $layout  = $block['layout'] ?? '50-50';
        $columns = $block['columns'] ?? [];
        $padding = $block['padding'] ?? '10px 0';

        $widths = match ($layout) {
            '60-40'  => [60, 40],
            '40-60'  => [40, 60],
            '33-33-33' => [33, 33, 33],
            default  => [50, 50],
        };

        $content_width = (int) ($global_style['content_width'] ?? 600);

        $html = '<tr><td style="padding:' . esc_attr($padding) . ';">';

        // MSO conditional: start table row for Outlook.
        $html .= '<!--[if mso]><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><![endif]-->';

        foreach ($columns as $i => $col_blocks) {
            $pct = $widths[$i] ?? 50;
            $px_width = (int) round($content_width * $pct / 100);

            $html .= '<!--[if mso]><td valign="top" width="' . $px_width . '"><![endif]-->';
            $html .= '<div class="ams-col" style="display:inline-block;vertical-align:top;width:100%;max-width:' . $pct . '%;">';
            $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">';

            if (is_array($col_blocks)) {
                foreach ($col_blocks as $col_block) {
                    $html .= $this->render_block($col_block, $global_style);
                }
            }

            $html .= '</table></div>';
            $html .= '<!--[if mso]></td><![endif]-->';
        }

        $html .= '<!--[if mso]></tr></table><![endif]-->';
        $html .= '</td></tr>';

        return $html;
    }

    /**
     * Product block — WooCommerce product card.
     */
    private function render_product_block(array $block): string
    {
        $product_id = (int) ($block['product_id'] ?? 0);
        $padding    = $block['padding'] ?? '10px 0';

        if (!$product_id || !function_exists('wc_get_product')) {
            return '';
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }

        $name  = esc_html($product->get_name());
        $price = wp_kses_post($product->get_price_html());
        $url   = esc_url($product->get_permalink());
        $img   = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') ?: '';

        $html = '<tr><td style="padding:' . esc_attr($padding) . ';">';
        $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr>';

        if ($img) {
            $html .= '<td style="width:120px;padding-right:15px;vertical-align:top;">'
                . '<a href="' . $url . '"><img src="' . esc_url($img) . '" alt="' . esc_attr($name) . '" width="120" style="display:block;border:0;border-radius:4px;"></a>'
                . '</td>';
        }

        $html .= '<td style="vertical-align:top;">'
            . '<a href="' . $url . '" style="text-decoration:none;color:inherit;font-weight:600;font-size:16px;">' . $name . '</a><br>'
            . '<span style="font-size:14px;color:#6b7280;">' . $price . '</span>'
            . '</td>';

        $html .= '</tr></table></td></tr>';

        return $html;
    }

    /**
     * Social links block.
     */
    private function render_social_block(array $block): string
    {
        $links   = $block['links'] ?? [];
        $align   = $block['align'] ?? 'center';
        $padding = $block['padding'] ?? '10px 0';
        $size    = $block['icon_size'] ?? '32';

        if (empty($links)) {
            return '';
        }

        $html = '<tr><td style="padding:' . esc_attr($padding) . ';text-align:' . esc_attr($align) . ';">';
        foreach ($links as $link) {
            $url   = esc_url($link['url'] ?? '#');
            $label = esc_attr($link['platform'] ?? '');
            $icon  = $this->get_social_icon_url($label);

            $html .= '<a href="' . $url . '" target="_blank" style="display:inline-block;margin:0 5px;">'
                . '<img src="' . esc_url($icon) . '" alt="' . $label . '" width="' . esc_attr($size) . '" height="' . esc_attr($size) . '" style="display:block;border:0;">'
                . '</a>';
        }
        $html .= '</td></tr>';

        return $html;
    }

    /**
     * Reviews block — review request, social proof display, or review gating.
     */
    private function render_reviews_block(array $block): string
    {
        $mode    = $block['mode'] ?? 'social_proof';
        $padding = $block['padding'] ?? '10px 0';

        return match ($mode) {
            'review_request' => $this->render_review_request($block, $padding),
            'review_gating'  => $this->render_review_gating($block, $padding),
            default          => $this->render_social_proof_reviews($block, $padding),
        };
    }

    /**
     * Review request mode — asks for a review with a CTA link.
     */
    private function render_review_request(array $block, string $padding): string
    {
        $heading = esc_html($block['heading'] ?? 'How was your experience?');
        $body    = wp_kses_post($block['body'] ?? 'We\'d love to hear your thoughts on your recent purchase.');
        $cta     = esc_html($block['cta_text'] ?? 'Leave a Review');
        $url     = esc_url($block['cta_url'] ?? '{{review_url}}');
        $bg      = $block['cta_bg'] ?? '#7c3aed';
        $color   = $block['cta_color'] ?? '#ffffff';

        return '<tr><td style="padding:' . esc_attr($padding) . ';text-align:center;">'
            . '<h3 style="margin:0 0 8px;font-size:20px;">' . $heading . '</h3>'
            . '<p style="margin:0 0 16px;font-size:14px;color:#6b7280;">' . $body . '</p>'
            . '<a href="' . $url . '" target="_blank" style="display:inline-block;padding:12px 30px;background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';text-decoration:none;border-radius:4px;font-weight:600;">'
            . $cta . '</a>'
            . '</td></tr>';
    }

    /**
     * Social proof mode — display existing reviews.
     */
    private function render_social_proof_reviews(array $block, string $padding): string
    {
        $product_id = (int) ($block['product_id'] ?? 0);
        $max_reviews = (int) ($block['max_reviews'] ?? 3);
        $heading    = esc_html($block['heading'] ?? 'What our customers say');

        $html = '<tr><td style="padding:' . esc_attr($padding) . ';">';
        $html .= '<h3 style="text-align:center;margin:0 0 12px;font-size:18px;">' . $heading . '</h3>';

        $reviews = $this->get_product_reviews($product_id, $max_reviews);

        if (empty($reviews)) {
            $html .= '<p style="text-align:center;color:#9ca3af;font-size:14px;">' . esc_html__('No reviews yet.', 'apotheca-marketing-suite') . '</p>';
        } else {
            foreach ($reviews as $review) {
                $stars = str_repeat('&#9733;', (int) $review->rating) . str_repeat('&#9734;', 5 - (int) $review->rating);
                $html .= '<div style="padding:10px 0;border-bottom:1px solid #f3f4f6;">'
                    . '<div style="color:#f59e0b;font-size:16px;">' . $stars . '</div>'
                    . '<p style="margin:4px 0 2px;font-size:14px;">' . esc_html(wp_trim_words($review->comment_content, 30)) . '</p>'
                    . '<span style="font-size:12px;color:#9ca3af;">— ' . esc_html($review->comment_author) . '</span>'
                    . '</div>';
            }
        }

        $html .= '</td></tr>';
        return $html;
    }

    /**
     * Review gating mode — asks satisfaction first, routes accordingly.
     */
    private function render_review_gating(array $block, string $padding): string
    {
        $heading     = esc_html($block['heading'] ?? 'How would you rate your experience?');
        $positive_url = esc_url($block['positive_url'] ?? '{{review_url}}');
        $negative_url = esc_url($block['negative_url'] ?? '{{support_url}}');
        $positive_text = esc_html($block['positive_text'] ?? 'Great!');
        $negative_text = esc_html($block['negative_text'] ?? 'Could be better');

        return '<tr><td style="padding:' . esc_attr($padding) . ';text-align:center;">'
            . '<h3 style="margin:0 0 16px;font-size:20px;">' . $heading . '</h3>'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;"><tr>'
            . '<td style="padding:0 8px;">'
            . '<a href="' . $positive_url . '" target="_blank" style="display:inline-block;padding:12px 24px;background:#10b981;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;">'
            . $positive_text . '</a></td>'
            . '<td style="padding:0 8px;">'
            . '<a href="' . $negative_url . '" target="_blank" style="display:inline-block;padding:12px 24px;background:#6b7280;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;">'
            . $negative_text . '</a></td>'
            . '</tr></table>'
            . '</td></tr>';
    }

    /**
     * Header block.
     */
    private function render_header_block(array $block): string
    {
        $logo_url = esc_url($block['logo_url'] ?? '');
        $logo_width = $block['logo_width'] ?? '150';
        $padding  = $block['padding'] ?? '20px 0';
        $align    = $block['align'] ?? 'center';

        $html = '<tr><td style="padding:' . esc_attr($padding) . ';text-align:' . esc_attr($align) . ';">';
        if ($logo_url) {
            $html .= '<img src="' . $logo_url . '" alt="" width="' . esc_attr($logo_width) . '" style="display:inline-block;border:0;height:auto;">';
        }
        $html .= '</td></tr>';

        return $html;
    }

    /**
     * Footer block.
     */
    private function render_footer_block(array $block): string
    {
        $content = wp_kses_post($block['content'] ?? '');
        $padding = $block['padding'] ?? '20px 0';

        return '<tr><td style="padding:' . esc_attr($padding) . ';text-align:center;font-size:12px;color:#9ca3af;">'
            . $content
            . '</td></tr>';
    }

    /**
     * Raw HTML block.
     */
    private function render_html_block(array $block): string
    {
        $content = $block['content'] ?? '';
        $padding = $block['padding'] ?? '10px 0';

        return '<tr><td style="padding:' . esc_attr($padding) . ';">'
            . wp_kses_post($content)
            . '</td></tr>';
    }

    /**
     * Build the full email document wrapper with Montserrat 3-tier font fallback.
     */
    private function get_document_wrapper(
        string $body,
        string $bg_color,
        string $content_bg,
        string $text_color,
        string $link_color,
        int $content_width,
        string $preheader
    ): string {
        $preheader_html = '';
        if ($preheader) {
            $preheader_html = '<div style="display:none;font-size:1px;color:' . esc_attr($bg_color) . ';line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">'
                . esc_html($preheader)
                . '</div>';
        }

        return '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<title></title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:AllowPNG/>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
@import url("https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap");

body, table, td {
    font-family: Montserrat, Century Gothic, CenturyGothic, AppleGothic, sans-serif;
    color: ' . esc_attr($text_color) . ';
}
a { color: ' . esc_attr($link_color) . '; }

@media only screen and (max-width: 620px) {
    .ams-col {
        display: block !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    .ams-email-body {
        width: 100% !important;
        min-width: 100% !important;
    }
}
</style>
<!--[if mso]>
<style type="text/css">
body, table, td { font-family: Century Gothic, CenturyGothic, AppleGothic, sans-serif !important; }
</style>
<![endif]-->
</head>
<body style="margin:0;padding:0;background-color:' . esc_attr($bg_color) . ';-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
' . $preheader_html . '
<!--[if mso]>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="' . $content_width . '" align="center"><tr><td>
<![endif]-->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="ams-email-body" style="max-width:' . $content_width . 'px;margin:0 auto;background-color:' . esc_attr($content_bg) . ';">
' . $body . '
</table>
<!--[if mso]>
</td></tr></table>
<![endif]-->
</body>
</html>';
    }

    /**
     * Get social icon placeholder URL.
     */
    private function get_social_icon_url(string $platform): string
    {
        $base = AMS_PLUGIN_URL . 'assets/images/social/';
        $platform = strtolower($platform);

        $icons = [
            'facebook'  => 'facebook.png',
            'twitter'   => 'twitter.png',
            'instagram' => 'instagram.png',
            'linkedin'  => 'linkedin.png',
            'youtube'   => 'youtube.png',
            'tiktok'    => 'tiktok.png',
            'pinterest' => 'pinterest.png',
        ];

        return $base . ($icons[$platform] ?? 'link.png');
    }

    /**
     * Get product reviews for social proof display.
     */
    private function get_product_reviews(int $product_id, int $limit): array
    {
        if (!$product_id) {
            return [];
        }

        $args = [
            'post_id' => $product_id,
            'status'  => 'approve',
            'type'    => 'review',
            'number'  => $limit,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'meta_query' => [
                [
                    'key'     => 'rating',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $comments = get_comments($args);

        // Attach ratings.
        foreach ($comments as $comment) {
            $comment->rating = (int) get_comment_meta($comment->comment_ID, 'rating', true);
        }

        return $comments;
    }
}
