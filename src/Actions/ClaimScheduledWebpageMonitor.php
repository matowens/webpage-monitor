<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Attempts to claim one due monitor for queued execution using a durable row update.
 */
class ClaimScheduledWebpageMonitor
{
    /**
     * Treat claims older than this many minutes as abandoned and reclaimable.
     */
    public const STALE_CLAIM_MINUTES = 10;

    /**
     * Atomically claim a due monitor when it is unclaimed or stale-claimed.
     */
    public function execute(WebpageMonitor $monitor, string $claimToken, CarbonInterface $claimedAt): bool
    {
        $claimTime = Carbon::instance($claimedAt);
        $staleBefore = $claimTime->copy()->subMinutes(self::STALE_CLAIM_MINUTES);

        return WebpageMonitor::query()
            ->whereKey($monitor->id)
            ->where('is_active', true)
            ->whereNotNull('interval_minutes')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $claimTime)
            ->where(function ($query) use ($staleBefore) {
                $query->whereNull('claimed_at')
                    ->orWhere('claimed_at', '<=', $staleBefore);
            })
            ->update([
                'claimed_at' => $claimTime,
                'claim_token' => $claimToken,
            ]) === 1;
    }
}
