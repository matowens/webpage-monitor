<?php

namespace Rivetworks\WebpageMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Actions\CalculateNextRunAt;
use Rivetworks\WebpageMonitor\Actions\ReleaseWebpageMonitorClaim;
use Rivetworks\WebpageMonitor\Actions\RunWebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use RuntimeException;
use Throwable;

/**
 * Runs one claimed scheduled monitor and advances its schedule after normal completion.
 */
class RunScheduledWebpageMonitor implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /**
     * Count only unexpected execution exceptions toward permanent queue failure.
     */
    public int $maxExceptions = 3;

    /**
     * Carry only the monitor identity and claim token across the queue boundary.
     */
    public function __construct(
        public int $monitorId,
        public string $claimToken,
    ) {}

    /**
     * Allow headroom for overlap-related queue releases without reducing the meaningful
     * monitor execution budget for unexpected exceptions.
     */
    public function tries(): int
    {
        return 6;
    }

    /**
     * Count only uncaught execution exceptions against the meaningful failure budget.
     */
    /**
     * Back off conservatively between unexpected exception retries.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 90];
    }

    /**
     * Prevent concurrent execution deliveries for the same monitor while still allowing
     * released overlap deliveries to retry later.
     *
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('webpage-monitor:'.$this->monitorId))
                ->releaseAfter(60)
                ->expireAfter(120),
        ];
    }

    /**
     * Run the existing monitor workflow only when this queued delivery still owns the claim.
     *
     * Queue delivery attempts include overlap-related releases. Actual monitor execution
     * begins only once this method reaches the existing monitor workflow.
     *
     * Normal monitor outcomes complete successfully, release the claim, and advance the
     * schedule. Unexpected exceptions are allowed to bubble so queue retries can apply.
     */
    public function handle(
        RunWebpageMonitor $runWebpageMonitor,
        CalculateNextRunAt $calculateNextRunAt,
        ReleaseWebpageMonitorClaim $releaseWebpageMonitorClaim,
    ): void {
        $monitor = WebpageMonitor::query()->find($this->monitorId);

        if ($monitor === null) {
            return;
        }

        if ($monitor->claim_token !== $this->claimToken) {
            return;
        }

        if (! $monitor->is_active || $monitor->interval_minutes === null || $monitor->next_run_at === null) {
            $releaseWebpageMonitorClaim->execute($monitor->id, $this->claimToken);

            return;
        }

        if ($monitor->next_run_at->isFuture()) {
            $releaseWebpageMonitorClaim->execute($monitor->id, $this->claimToken);

            return;
        }

        $executedAt = Carbon::now();
        $scheduledFor = $monitor->next_run_at->copy();

        $runWebpageMonitor->execute($monitor);

        $updated = WebpageMonitor::query()
            ->whereKey($monitor->id)
            ->where('claim_token', $this->claimToken)
            ->update([
                'last_run_at' => $executedAt,
                'next_run_at' => $calculateNextRunAt->execute($scheduledFor, $monitor->interval_minutes, $executedAt),
                'claimed_at' => null,
                'claim_token' => null,
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('The monitor claim was lost before schedule advancement could be persisted.');
        }
    }

    /**
     * Release the matching claim after permanent queue failure so the overdue monitor can
     * be redispatched on the next scheduler pass.
     */
    public function failed(Throwable $exception): void
    {
        report($exception);

        app(ReleaseWebpageMonitorClaim::class)->execute($this->monitorId, $this->claimToken);
    }
}
