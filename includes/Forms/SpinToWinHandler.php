<?php
/**
 * Spin-to-win handler.
 *
 * Determines prize from weighted segments and generates WooCommerce coupons.
 * Server-side validation ensures the prize is legitimate.
 *
 * @package Apotheca\Marketing\Forms
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Forms;

defined('ABSPATH') || exit;

final class SpinToWinHandler
{
    /**
     * Determine the winning segment based on probability weights.
     *
     * @param array $segments Array of segments: [{label, probability, discount_type, discount_value, ...}]
     * @return array|null The winning segment or null if no segments.
     */
    public function determine_prize(array $segments): ?array
    {
        if (empty($segments)) {
            return null;
        }

        $total_weight = 0;
        foreach ($segments as $segment) {
            $total_weight += (float) ($segment['probability'] ?? 0);
        }

        if ($total_weight <= 0) {
            return $segments[0] ?? null;
        }

        $random = mt_rand(0, (int) ($total_weight * 1000)) / 1000;
        $cumulative = 0;

        foreach ($segments as $segment) {
            $cumulative += (float) ($segment['probability'] ?? 0);
            if ($random <= $cumulative) {
                return $segment;
            }
        }

        return end($segments) ?: null;
    }

    /**
     * Generate a WooCommerce coupon for the prize.
     *
     * @return string The coupon code, or empty string on failure.
     */
    public function generate_coupon(array $prize, string $email = ''): string
    {
        if (!class_exists('WC_Coupon')) {
            return '';
        }

        $code = 'AMS-SPIN-' . strtoupper(wp_generate_password(8, false));

        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_description('Spin-to-win prize: ' . sanitize_text_field($prize['label'] ?? 'Prize'));

        $discount_type = $prize['discount_type'] ?? 'percent';
        $discount_value = (float) ($prize['discount_value'] ?? 0);

        $coupon->set_discount_type($discount_type === 'fixed' ? 'fixed_cart' : 'percent');
        $coupon->set_amount($discount_value);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);

        if (!empty($email)) {
            $coupon->set_email_restrictions([$email]);
        }

        // Expire in 30 days by default.
        $expiry_days = (int) ($prize['expiry_days'] ?? 30);
        $coupon->set_date_expires(time() + ($expiry_days * DAY_IN_SECONDS));

        // Minimum spend if set.
        if (!empty($prize['minimum_spend'])) {
            $coupon->set_minimum_amount((float) $prize['minimum_spend']);
        }

        // Free shipping prize.
        if (!empty($prize['free_shipping'])) {
            $coupon->set_free_shipping(true);
        }

        $coupon->save();

        return $coupon->get_id() ? $code : '';
    }

    /**
     * Process a spin-to-win submission: determine prize and generate coupon.
     *
     * @return array{segment_index: int, label: string, coupon_code: string}
     */
    public function process_spin(object $form, string $email): array
    {
        $spin_config = json_decode($form->spin_config ?: '{}', true) ?: [];
        $segments = $spin_config['segments'] ?? [];

        if (empty($segments)) {
            return ['segment_index' => 0, 'label' => '', 'coupon_code' => ''];
        }

        $prize = $this->determine_prize($segments);
        if (!$prize) {
            return ['segment_index' => 0, 'label' => '', 'coupon_code' => ''];
        }

        $segment_index = 0;
        foreach ($segments as $i => $seg) {
            if ($seg['label'] === $prize['label']) {
                $segment_index = $i;
                break;
            }
        }

        $coupon_code = '';
        if (!empty($prize['discount_value']) || !empty($prize['free_shipping'])) {
            $coupon_code = $this->generate_coupon($prize, $email);
        }

        return [
            'segment_index' => $segment_index,
            'label'         => $prize['label'] ?? '',
            'coupon_code'   => $coupon_code,
        ];
    }
}
