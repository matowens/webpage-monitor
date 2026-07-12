<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Advances a monitor's next scheduled time without drifting from its prior schedule.
 */
class CalculateNextRunAt
{
    /**
     * Move the prior scheduled time forward until it is strictly in the future.
     */
    public function execute(CarbonInterface $previousNextRunAt, int $intervalMinutes, CarbonInterface $executedAt): CarbonInterface
    {
        $nextRunAt = Carbon::instance($previousNextRunAt)->copy();
        $executionTime = Carbon::instance($executedAt);

        while ($nextRunAt->lessThanOrEqualTo($executionTime)) {
            $nextRunAt->addMinutes($intervalMinutes);
        }

        return $nextRunAt;
    }
}
