<?php

namespace Rivetworks\WebpageMonitor\Console;

use Illuminate\Console\Command;
use Rivetworks\WebpageMonitor\Actions\RunDueWebpageMonitors;
use Rivetworks\WebpageMonitor\Data\RunDueWebpageMonitorsResult;

/**
 * Claims and dispatches all active saved monitors that are due for queued execution.
 */
class RunDueWebpageMonitorsCommand extends Command
{
    protected $signature = 'webpage-monitor:run-due';

    protected $description = 'Dispatch all due webpage monitors once.';

    /**
     * Process the current due-monitor batch and render a concise summary.
     */
    public function handle(RunDueWebpageMonitors $runDueWebpageMonitors): int
    {
        $result = $runDueWebpageMonitors->execute();

        $this->renderResult($result);

        return self::SUCCESS;
    }

    /**
     * Render due-monitor batch totals and any concise exception lines.
     */
    private function renderResult(RunDueWebpageMonitorsResult $result): void
    {
        $this->line('Due monitors: '.$result->dueMonitors);
        $this->line('Dispatched: '.$result->dispatched);
        $this->line('Skipped/Already Claimed: '.$result->skippedAlreadyClaimed);
        $this->line('Exceptions: '.$result->exceptions);

        foreach ($result->exceptionMessages as $exceptionMessage) {
            $this->line($exceptionMessage);
        }
    }
}
