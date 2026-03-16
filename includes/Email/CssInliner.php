<?php
/**
 * Pure PHP CSS inliner for email HTML.
 *
 * Converts <style> blocks into inline style attributes while preserving
 * @import rules in the <head> (needed for webmail font loading).
 *
 * @package Apotheca\Marketing\Email
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Email;

defined('ABSPATH') || exit;

final class CssInliner
{
    /**
     * Inline CSS from <style> blocks into the HTML elements.
     *
     * Preserves @import and @media rules in <head> (not inlined).
     */
    public function inline(string $html): string
    {
        // Extract all <style> blocks.
        $styles = [];
        $html = preg_replace_callback(
            '/<style[^>]*>(.*?)<\/style>/si',
            function (array $matches) use (&$styles): string {
                $css = $matches[1];

                // Preserve @import rules — re-inject into head later.
                $imports = '';
                $css = preg_replace_callback(
                    '/@import\s+[^;]+;/i',
                    function (array $m) use (&$imports): string {
                        $imports .= $m[0] . "\n";
                        return '';
                    },
                    $css
                );

                // Preserve @media rules — keep in <style> in head for responsive emails.
                $media_rules = '';
                $css = preg_replace_callback(
                    '/@media\s+[^{]+\{(?:[^{}]*\{[^}]*\})*[^}]*\}/si',
                    function (array $m) use (&$media_rules): string {
                        $media_rules .= $m[0] . "\n";
                        return '';
                    },
                    $css
                );

                $styles[] = [
                    'css'     => trim($css),
                    'imports' => trim($imports),
                    'media'   => trim($media_rules),
                ];

                return '<!-- ams-style-placeholder -->';
            },
            $html
        );

        // Parse CSS rules and apply inline.
        $rule_map = [];
        foreach ($styles as $block) {
            $rules = $this->parse_rules($block['css']);
            foreach ($rules as $selector => $declarations) {
                $rule_map[$selector] = ($rule_map[$selector] ?? '') . $declarations;
            }
        }

        // Apply rules to matching elements using DOMDocument.
        if (!empty($rule_map)) {
            $html = $this->apply_inline_styles($html, $rule_map);
        }

        // Re-inject preserved @import and @media into <head>.
        $head_css = '';
        foreach ($styles as $block) {
            if ($block['imports']) {
                $head_css .= $block['imports'] . "\n";
            }
            if ($block['media']) {
                $head_css .= $block['media'] . "\n";
            }
        }

        // Remove placeholders.
        $html = str_replace('<!-- ams-style-placeholder -->', '', $html);

        if ($head_css) {
            $style_tag = '<style>' . trim($head_css) . '</style>';
            if (str_contains($html, '</head>')) {
                $html = str_replace('</head>', $style_tag . '</head>', $html);
            }
        }

        return $html;
    }

    /**
     * Parse CSS text into selector => declarations map.
     */
    private function parse_rules(string $css): array
    {
        $rules = [];
        // Remove comments.
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Match selector { declarations }.
        preg_match_all('/([^{]+)\{([^}]*)\}/s', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selectors = array_map('trim', explode(',', trim($match[1])));
            $declarations = trim($match[2]);

            foreach ($selectors as $selector) {
                if ($selector && $declarations) {
                    $rules[$selector] = ($rules[$selector] ?? '') . $declarations;
                }
            }
        }

        return $rules;
    }

    /**
     * Apply inline styles to HTML elements using DOMDocument.
     */
    private function apply_inline_styles(string $html, array $rule_map): string
    {
        // Suppress DOMDocument warnings for HTML5 tags.
        $prev = libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $xpath = new \DOMXPath($doc);

        foreach ($rule_map as $selector => $declarations) {
            $xpathQuery = $this->css_to_xpath($selector);
            if (!$xpathQuery) {
                continue;
            }

            $nodes = @$xpath->query($xpathQuery);
            if (!$nodes) {
                continue;
            }

            $declarations = rtrim(trim($declarations), ';') . ';';

            foreach ($nodes as $node) {
                if (!($node instanceof \DOMElement)) {
                    continue;
                }

                $existing = $node->getAttribute('style');
                $existing = $existing ? rtrim($existing, ';') . ';' : '';
                $node->setAttribute('style', $existing . $declarations);
            }
        }

        $result = $doc->saveHTML();
        // Remove the XML encoding declaration we added.
        $result = str_replace('<?xml encoding="UTF-8">', '', $result);

        libxml_use_internal_errors($prev);

        return $result;
    }

    /**
     * Convert a simple CSS selector to XPath.
     *
     * Supports: tag, .class, #id, tag.class, tag#id, and basic attribute selectors.
     */
    private function css_to_xpath(string $selector): ?string
    {
        $selector = trim($selector);

        // Skip pseudo-classes and pseudo-elements — can't be inlined.
        if (str_contains($selector, ':')) {
            return null;
        }

        // Descendant combinator (space).
        $parts = preg_split('/\s+/', $selector);
        $xpath_parts = [];

        foreach ($parts as $part) {
            $xp = $this->single_selector_to_xpath($part);
            if (!$xp) {
                return null;
            }
            $xpath_parts[] = $xp;
        }

        return '//' . implode('//', $xpath_parts);
    }

    /**
     * Convert a single simple selector (no combinators) to XPath segment.
     */
    private function single_selector_to_xpath(string $sel): ?string
    {
        // #id
        if (preg_match('/^#([\w-]+)$/', $sel, $m)) {
            return '*[@id="' . $m[1] . '"]';
        }

        // .class
        if (preg_match('/^\.([\w-]+)$/', $sel, $m)) {
            return '*[contains(concat(" ",normalize-space(@class)," ")," ' . $m[1] . ' ")]';
        }

        // tag#id
        if (preg_match('/^([\w-]+)#([\w-]+)$/', $sel, $m)) {
            return $m[1] . '[@id="' . $m[2] . '"]';
        }

        // tag.class
        if (preg_match('/^([\w-]+)\.([\w-]+)$/', $sel, $m)) {
            return $m[1] . '[contains(concat(" ",normalize-space(@class)," ")," ' . $m[2] . ' ")]';
        }

        // Plain tag.
        if (preg_match('/^[\w-]+$/', $sel)) {
            return $sel;
        }

        return null;
    }
}
