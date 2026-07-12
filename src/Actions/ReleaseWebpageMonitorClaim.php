<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Clears a queued-execution claim only when the caller still owns the token.
 */
class ReleaseWebpageMonitorClaim
{
    /**
     * Release a monitor claim if the stored token still matches the caller's token.
     */
    public function execute(int $monitorId, string $claimToken): void
    {
        WebpageMonitor::query()
            ->whereKey($monitorId)
            ->where('claim_token', $claimToken)
            ->update([
                'claimed_at' => null,
                'claim_token' => null,
            ]);
    }
}
