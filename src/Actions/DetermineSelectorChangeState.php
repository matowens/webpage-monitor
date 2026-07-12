<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Compares selector results against the latest prior successful selector hash.
 */
class DetermineSelectorChangeState
{
    /**
     * Classify a selector result against the latest prior successful selector hash.
     *
     * Failed runs and rows without a content hash are ignored so they never become a
     * baseline for later change detection.
     */
    public function execute(WebpageMonitor $monitor, string $contentHash): ChangeState
    {
        $previousCheck = $monitor->checks()
            ->whereNotNull('content_hash')
            ->whereIn('change_state', [
                ChangeState::Baseline,
                ChangeState::Unchanged,
                ChangeState::Changed,
            ])
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();

        if ($previousCheck === null) {
            return ChangeState::Baseline;
        }

        return $previousCheck->content_hash === $contentHash
            ? ChangeState::Unchanged
            : ChangeState::Changed;
    }
}
