<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Rivetworks\WebpageMonitor\Actions\RunWebpageMonitor;
use Rivetworks\WebpageMonitor\Data\FetchResult;
use Rivetworks\WebpageMonitor\Data\MonitorRunResult;
use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Events\MonitorBaselineEstablished;
use Rivetworks\WebpageMonitor\Jobs\RunScheduledWebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;
use Rivetworks\WebpageMonitor\Notifications\MonitorBaselineEstablishedNotification;

it('runs a claimed scheduled monitor and advances its schedule', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    app()->call([$job, 'handle']);

    $monitor->refresh();
    $check = WebpageMonitorCheck::query()->sole();

    expect($check->change_state)->toBe(ChangeState::NotApplicable)
        ->and($monitor->last_run_at?->toDateTimeString())->toBe('2026-07-12 10:00:00')
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 10:14:00')
        ->and($monitor->claimed_at)->toBeNull()
        ->and($monitor->claim_token)->toBeNull();
});

it('treats a contains assertion miss as a completed scheduled attempt', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $monitor = WebpageMonitor::factory()->contains('Expected Text')->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('Different Text', 200),
    ]);

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    app()->call([$job, 'handle']);

    $monitor->refresh();
    $check = WebpageMonitorCheck::query()->sole();

    expect($check->assertion_passed)->toBeFalse()
        ->and($check->change_state)->toBe(ChangeState::NotApplicable)
        ->and($monitor->last_run_at?->toDateTimeString())->toBe('2026-07-12 10:00:00')
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 10:14:00')
        ->and($monitor->claim_token)->toBeNull();
});

it('treats a selector extraction failure as a completed scheduled attempt', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $monitor = WebpageMonitor::factory()->selector('p')->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    app()->call([$job, 'handle']);

    $monitor->refresh();
    $check = WebpageMonitorCheck::query()->sole();

    expect($check->change_state)->toBe(ChangeState::Failed)
        ->and($monitor->last_run_at?->toDateTimeString())->toBe('2026-07-12 10:00:00')
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 10:14:00')
        ->and($monitor->claim_token)->toBeNull();
});

it('treats a transport failure as a completed scheduled attempt', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::failedConnection('Connection timed out'),
    ]);

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    app()->call([$job, 'handle']);

    $monitor->refresh();
    $check = WebpageMonitorCheck::query()->sole();

    expect($check->change_state)->toBe(ChangeState::Failed)
        ->and($monitor->last_run_at?->toDateTimeString())->toBe('2026-07-12 10:00:00')
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 10:14:00')
        ->and($monitor->claim_token)->toBeNull();
});

it('exits cleanly when the monitor is missing', function () {
    $job = new RunScheduledWebpageMonitor(999999, 'missing-claim');

    app()->call([$job, 'handle']);

    expect(WebpageMonitorCheck::query()->count())->toBe(0);
});

it('releases the claim and exits cleanly when the monitor becomes inactive', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $monitor = WebpageMonitor::factory()->availability()->inactive()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create();

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    app()->call([$job, 'handle']);

    $monitor->refresh();

    expect($monitor->claim_token)->toBeNull()
        ->and($monitor->claimed_at)->toBeNull()
        ->and(WebpageMonitorCheck::query()->count())->toBe(0);
});

it('keeps the claim during retryable unexpected exceptions and does not advance the schedule', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create();

    app()->bind(RunWebpageMonitor::class, fn () => new class extends RunWebpageMonitor
    {
        public function __construct() {}

        public function execute(WebpageMonitor $monitor): MonitorRunResult
        {
            throw new \RuntimeException('unexpected execution failure');
        }
    });

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    expect(fn () => app()->call([$job, 'handle']))->toThrow(\RuntimeException::class);

    $monitor->refresh();

    expect($monitor->claim_token)->toBe('claim-token')
        ->and($monitor->last_run_at)->toBeNull()
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 09:59:00')
        ->and(WebpageMonitorCheck::query()->count())->toBe(0);
});

it('releases the claim after permanent failure and leaves the schedule overdue for redispatch', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create();

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    $job->failed(new \RuntimeException('permanent queue failure'));

    $monitor->refresh();

    expect($monitor->claim_token)->toBeNull()
        ->and($monitor->claimed_at)->toBeNull()
        ->and($monitor->last_run_at)->toBeNull()
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 09:59:00');
});

it('allows an overdue monitor to be redispatched after permanent failure releases its claim', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    Queue::fake();

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create();

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');
    $job->failed(new \RuntimeException('permanent queue failure'));

    $this->artisan('webpage-monitor:run-due')->assertExitCode(0);

    Queue::assertPushed(RunScheduledWebpageMonitor::class, function (RunScheduledWebpageMonitor $job) use ($monitor) {
        return $job->monitorId === $monitor->id
            && $job->claimToken !== 'claim-token';
    });
});

it('does not exhaust meaningful execution attempts when overlap releases delay the job', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    config()->set('queue.default', 'database');

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 1,
        nextRunAt: Carbon::now()->subSecond(),
    )->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run-due')->assertExitCode(0);

    $monitor->refresh();

    $queuedJob = new RunScheduledWebpageMonitor($monitor->id, (string) $monitor->claim_token);
    $middleware = $queuedJob->middleware()[0];
    $lock = Cache::lock($middleware->getLockKey($queuedJob), 120);

    expect($lock->get())->toBeTrue()
        ->and($queuedJob->tries())->toBe(6)
        ->and($queuedJob->maxExceptions)->toBe(3);

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
    ])->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
    ])->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    $lock->release();

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
    ])->assertExitCode(0);

    $monitor->refresh();

    expect(WebpageMonitorCheck::query()->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($monitor->claim_token)->toBeNull()
        ->and($monitor->last_run_at?->toDateTimeString())->toBe('2026-07-12 10:02:02');
});

it('emits only one applicable event and queues only one applicable notification for one scheduled execution', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $baselineEvents = 0;

    Event::listen(MonitorBaselineEstablished::class, function () use (&$baselineEvents) {
        $baselineEvents++;
    });

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create([
        'name' => 'Availability Monitor',
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    app()->call([$job, 'handle']);

    expect($baselineEvents)->toBe(1);

    Notification::assertSentTimes(MonitorBaselineEstablishedNotification::class, 1);
});

it('does not advance schedule or claim handling when notification delivery throws', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::partialMock()
        ->shouldReceive('send')
        ->andThrow(new RuntimeException('mail transport failed'));

    $monitor = WebpageMonitor::factory()->availability()->scheduled(
        intervalMinutes: 15,
        nextRunAt: Carbon::now()->subMinute(),
    )->claimed(Carbon::now()->subSeconds(30), 'claim-token')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $job = new RunScheduledWebpageMonitor($monitor->id, 'claim-token');

    app()->call([$job, 'handle']);

    $monitor->refresh();

    expect(WebpageMonitorCheck::query()->count())->toBe(1)
        ->and($monitor->claim_token)->toBeNull()
        ->and($monitor->last_run_at?->toDateTimeString())->toBe('2026-07-12 10:00:00')
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 10:14:00');
});
