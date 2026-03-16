<?php
/**
 * RFM change trigger — fires when subscriber RFM segment changes.
 *
 * Listens for a custom action fired by the RFM scoring engine (Session 3).
 *
 * @package Apotheca\Marketing\Flows\Triggers
 */

declare(strict_types=1);

namespace Apotheca\Marketing\Flows\Triggers;

defined('ABSPATH') || exit;

final class RfmChange extends AbstractTrigger
{
    protected function get_trigger_type(): string
    {
        return 'rfm_change';
    }

    public function register(): void
    {
        add_action('ams_rfm_segment_changed', [$this, 'on_rfm_changed'], 10, 3);
    }

    /**
     * Fired when the RFM scoring engine detects a segment change.
     *
     * @param int    $subscriber_id   The subscriber ID.
     * @param string $old_segment     Previous RFM segment name.
     * @param string $new_segment     New RFM segment name.
     */
    public function on_rfm_changed(int $subscriber_id, string $old_segment, string $new_segment): void
    {
        $active_flows = $this->flows->get_active_by_trigger('rfm_change');

        foreach ($active_flows as $flow) {
            $config = json_decode($flow->trigger_config ?: '{}', true) ?: [];

            // If specific segments are configured, check match.
            if (!empty($config['from_segment']) && $config['from_segment'] !== $old_segment) {
                continue;
            }
            if (!empty($config['to_segment']) && $config['to_segment'] !== $new_segment) {
                continue;
            }

            $enrolment_id = $this->enrolments->enrol((int) $flow->id, $subscriber_id);
            if ($enrolment_id > 0) {
                $this->executor->execute($enrolment_id);
            }
        }
    }
}
