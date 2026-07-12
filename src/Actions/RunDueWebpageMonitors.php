<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Rivetworks\WebpageMonitor\Data\RunDueWebpageMonitorsResult;
use Rivetworks\WebpageMonitor\Jobs\RunScheduledWebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Throwable;

/**
 * Finds due monitors, claims them, and dispatches one queued job per successful claim.
 */
class RunDueWebpageMonitors
{
    /**
     * Compose the durable claim workflow with queued job dispatch.
     */
    public function __construct(
        public ClaimScheduledWebpageMonitor $claimScheduledWebpageMonitor,
        public ReleaseWebpageMonitorClaim $releaseWebpageMonitorClaim,
        public Dispatcher $dispatcher,
    ) {}

    /**
     * Claim due monitors in deterministic order and dispatch queued execution jobs.
     *
     * Dispatch-time exceptions are reported, their claims are released immediately, and the
     * remaining due monitors continue through the batch.
     */
    public function execute(): RunDueWebpageMonitorsResult
    {
        $dueMonitorsQuery = WebpageMonitor::query()
            ->where('is_active', true)
            ->whereNotNull('interval_minutes')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', Carbon::now())
            ->orderBy('next_run_at')
            ->orderBy('id');

        $dueMonitors = $dueMonitorsQuery->get();
        $dueMonitorCount = $dueMonitors->count();
        $dispatched = 0;
        $skippedAlreadyClaimed = 0;
        $exceptions = 0;
        $exceptionMessages = [];

        foreach ($dueMonitors as $monitor) {
            $claimedAt = Carbon::now();
            $claimToken = (string) Str::uuid();

            try {
                if (! $this->claimScheduledWebpageMonitor->execute($monitor, $claimToken, $claimedAt)) {
                    $skippedAlreadyClaimed++;

                    continue;
                }

                $this->dispatcher->dispatch(new RunScheduledWebpageMonitor($monitor->id, $claimToken));

                $dispatched++;
            } catch (Throwable $exception) {
                report($exception);

                $this->releaseWebpageMonitorClaim->execute($monitor->id, $claimToken);

                $exceptions++;
                $exceptionMessages[] = 'Monitor '.$monitor->id.': unexpected exception prevented dispatch.';
            }
        }

        return new RunDueWebpageMonitorsResult(
            dueMonitors: $dueMonitorCount,
            dispatched: $dispatched,
            skippedAlreadyClaimed: $skippedAlreadyClaimed,
            exceptions: $exceptions,
            exceptionMessages: $exceptionMessages,
        );
    }
}
