<?php

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Rivetworks\WebpageMonitor\Jobs\RunScheduledWebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

it('claims and dispatches an active due monitor without fetching immediately', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    Queue::fake();
    Http::fake();

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->create([
        'url' => 'https://example.com',
    ]);

    $this->artisan('webpage-monitor:run-due')
        ->expectsOutput('Due monitors: 1')
        ->expectsOutput('Dispatched: 1')
        ->expectsOutput('Skipped/Already Claimed: 0')
        ->expectsOutput('Exceptions: 0')
        ->assertExitCode(0);

    Queue::assertPushed(RunScheduledWebpageMonitor::class, function (RunScheduledWebpageMonitor $job) use ($monitor) {
        return $job->monitorId === $monitor->id
            && $job->claimToken !== '';
    });

    $monitor->refresh();

    expect($monitor->claimed_at?->toDateTimeString())->toBe('2026-07-12 10:00:00')
        ->and($monitor->claim_token)->not->toBeNull();

    Http::assertNothingSent();
});

it('does not dispatch future monitors', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    Queue::fake();
    Http::fake();

    WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->addMinute(),
    )->create();

    $this->artisan('webpage-monitor:run-due')
        ->expectsOutput('Due monitors: 0')
        ->expectsOutput('Dispatched: 0')
        ->expectsOutput('Skipped/Already Claimed: 0')
        ->expectsOutput('Exceptions: 0')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('does not dispatch manual only or inactive monitors', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    Queue::fake();
    Http::fake();

    WebpageMonitor::factory()->availability()->create();

    WebpageMonitor::factory()->availability()->inactive()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->create();

    $this->artisan('webpage-monitor:run-due')
        ->expectsOutput('Due monitors: 0')
        ->expectsOutput('Dispatched: 0')
        ->expectsOutput('Skipped/Already Claimed: 0')
        ->expectsOutput('Exceptions: 0')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('dispatches due monitors in deterministic order', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    Queue::fake();

    $first = WebpageMonitor::factory()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinutes(2),
    )->create();

    $second = WebpageMonitor::factory()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->create();

    $this->artisan('webpage-monitor:run-due')->assertExitCode(0);

    $monitorIds = Queue::pushed(RunScheduledWebpageMonitor::class)
        ->map(fn (RunScheduledWebpageMonitor $job) => $job->monitorId)
        ->all();

    expect($monitorIds)->toBe([$first->id, $second->id]);
});

it('skips already claimed monitors whose claims are still active', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    Queue::fake();

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subMinutes(9), 'active-claim')->create();

    $this->artisan('webpage-monitor:run-due')
        ->expectsOutput('Due monitors: 1')
        ->expectsOutput('Dispatched: 0')
        ->expectsOutput('Skipped/Already Claimed: 1')
        ->expectsOutput('Exceptions: 0')
        ->assertExitCode(0);

    $monitor->refresh();

    expect($monitor->claim_token)->toBe('active-claim');

    Queue::assertNothingPushed();
});

it('reclaims an abandoned claim after ten minutes', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    Queue::fake();

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subMinutes(10), 'stale-claim')->create();

    $this->artisan('webpage-monitor:run-due')
        ->expectsOutput('Due monitors: 1')
        ->expectsOutput('Dispatched: 1')
        ->expectsOutput('Skipped/Already Claimed: 0')
        ->expectsOutput('Exceptions: 0')
        ->assertExitCode(0);

    $monitor->refresh();

    expect($monitor->claim_token)->not->toBe('stale-claim')
        ->and($monitor->claimed_at?->toDateTimeString())->toBe('2026-07-12 10:00:00');
});

it('releases the claim when dispatch throws and continues later monitors', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $firstMonitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinutes(2),
    )->create();

    $secondMonitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->create();

    $dispatcher = new class($firstMonitor->id) implements Dispatcher
    {
        public array $dispatchedMonitorIds = [];

        public function __construct(private int $failingMonitorId) {}

        public function dispatch($command)
        {
            if ($command->monitorId === $this->failingMonitorId) {
                throw new \RuntimeException('sensitive dispatch details');
            }

            $this->dispatchedMonitorIds[] = $command->monitorId;

            return $command;
        }

        public function dispatchSync($command, $handler = null) {}

        public function dispatchNow($command, $handler = null) {}

        public function dispatchAfterResponse($command, $handler = null) {}

        public function chain($jobs = null)
        {
            return $this;
        }

        public function hasCommandHandler($command): bool
        {
            return false;
        }

        public function getCommandHandler($command) {}

        public function pipeThrough(array $pipes)
        {
            return $this;
        }

        public function map(array $map)
        {
            return $this;
        }
    };

    app()->instance(Dispatcher::class, $dispatcher);

    $this->artisan('webpage-monitor:run-due')
        ->expectsOutput('Due monitors: 2')
        ->expectsOutput('Dispatched: 1')
        ->expectsOutput('Skipped/Already Claimed: 0')
        ->expectsOutput('Exceptions: 1')
        ->expectsOutput('Monitor '.$firstMonitor->id.': unexpected exception prevented dispatch.')
        ->assertExitCode(0);

    $firstMonitor->refresh();
    $secondMonitor->refresh();

    expect($firstMonitor->claim_token)->toBeNull()
        ->and($firstMonitor->claimed_at)->toBeNull()
        ->and($secondMonitor->claim_token)->not->toBeNull()
        ->and($dispatcher->dispatchedMonitorIds)->toBe([$secondMonitor->id]);
});

it('executes due monitors immediately with the sync queue connection', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    config()->set('queue.default', 'sync');

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run-due')
        ->expectsOutput('Due monitors: 1')
        ->expectsOutput('Dispatched: 1')
        ->expectsOutput('Skipped/Already Claimed: 0')
        ->expectsOutput('Exceptions: 0')
        ->assertExitCode(0);

    $monitor->refresh();

    expect($monitor->last_run_at?->toDateTimeString())->toBe('2026-07-12 10:00:00')
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 10:14:00')
        ->and($monitor->claimed_at)->toBeNull()
        ->and($monitor->claim_token)->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('webpage_monitor_checks')->count())->toBe(1);
});
