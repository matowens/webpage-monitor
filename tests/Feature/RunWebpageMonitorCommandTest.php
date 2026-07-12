<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;
use Rivetworks\WebpageMonitor\Notifications\MonitorAssertionFailedNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorBaselineEstablishedNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorBecameUnavailableNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorContentChangedNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorRecoveredNotification;

it('stores an availability result', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->availability()->create([
        'name' => 'Availability Monitor',
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Monitor ID: '.$monitor->id)
        ->expectsOutput('Monitor Name: Availability Monitor')
        ->expectsOutput('Change State: not_applicable')
        ->expectsOutputToContain('Check ID: ')
        ->assertExitCode(0);

    $check = WebpageMonitorCheck::query()->sole();

    expect($check->reachable)->toBeTrue()
        ->and($check->change_state)->toBe(ChangeState::NotApplicable)
        ->and($check->failure_message)->toBeNull();

    Notification::assertSentOnDemand(MonitorBaselineEstablishedNotification::class, function (MonitorBaselineEstablishedNotification $notification) use ($monitor) {
        return $notification->state->monitorId === $monitor->id
            && $notification->state->reachable;
    });
});

it('stores a contains success result', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->contains('Example Domain')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('Example Domain', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Expected Text: Example Domain')
        ->expectsOutput('Text Found: yes')
        ->expectsOutput('Change State: not_applicable')
        ->assertExitCode(0);

    $check = WebpageMonitorCheck::query()->sole();

    expect($check->assertion_passed)->toBeTrue()
        ->and($check->change_state)->toBe(ChangeState::NotApplicable);

    Notification::assertSentOnDemand(MonitorBaselineEstablishedNotification::class, function (MonitorBaselineEstablishedNotification $notification) {
        return $notification->state->assertionPassed === true;
    });
});

it('stores a contains failure as not applicable and exits with failure', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->contains('Example Domain')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('Different Text', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Expected Text: Example Domain')
        ->expectsOutput('Text Found: no')
        ->expectsOutput('Change State: not_applicable')
        ->assertExitCode(1);

    $check = WebpageMonitorCheck::query()->sole();

    expect($check->assertion_passed)->toBeFalse()
        ->and($check->change_state)->toBe(ChangeState::NotApplicable);

    Notification::assertSentOnDemand(MonitorBaselineEstablishedNotification::class, function (MonitorBaselineEstablishedNotification $notification) {
        return $notification->state->assertionPassed === false;
    });
});

it('classifies the first selector result as baseline', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('h1')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Selector: h1')
        ->expectsOutput('Matches: 1')
        ->expectsOutput('Selected Content: Example Domain')
        ->expectsOutput('Change State: baseline')
        ->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->sole()->change_state)->toBe(ChangeState::Baseline);

    Notification::assertSentOnDemand(MonitorBaselineEstablishedNotification::class, function (MonitorBaselineEstablishedNotification $notification) {
        return $notification->state->contentHash === hash('sha256', 'Example Domain');
    });
});

it('classifies a repeated selector hash as unchanged', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('h1')->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'http_status' => 200,
        'body_bytes' => 100,
        'selected_content' => 'Example Domain',
        'content_hash' => hash('sha256', 'Example Domain'),
        'change_state' => ChangeState::Baseline,
        'checked_at' => Carbon::now()->subMinute(),
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Change State: unchanged')
        ->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->latest('id')->first()?->change_state)->toBe(ChangeState::Unchanged);

    Notification::assertNothingSent();
});

it('classifies a different selector hash as changed', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('h1')->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'http_status' => 200,
        'body_bytes' => 100,
        'selected_content' => 'Example Domain',
        'content_hash' => hash('sha256', 'Example Domain'),
        'change_state' => ChangeState::Baseline,
        'checked_at' => Carbon::now()->subMinute(),
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Different Text</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Change State: changed')
        ->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->latest('id')->first()?->change_state)->toBe(ChangeState::Changed);

    Notification::assertSentOnDemand(MonitorContentChangedNotification::class, function (MonitorContentChangedNotification $notification) {
        return $notification->previousSelectedContent === 'Example Domain'
            && $notification->currentSelectedContent === 'Different Text';
    });
});

it('records a failed selector extraction as failed', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('p')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Selector: p')
        ->expectsOutput('Change State: failed')
        ->expectsOutput('Failure: No elements matched the supplied selector.')
        ->assertExitCode(1);

    expect(WebpageMonitorCheck::query()->sole()->change_state)->toBe(ChangeState::Failed);

    Notification::assertNothingSent();
});

it('records a transport failure as failed', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->availability()->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::failedConnection('Connection timed out'),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Reachable: no')
        ->expectsOutput('Change State: failed')
        ->expectsOutputToContain('Failure: Connection timed out')
        ->assertExitCode(1);

    expect(WebpageMonitorCheck::query()->sole()->change_state)->toBe(ChangeState::Failed);

    Notification::assertSentOnDemand(MonitorBaselineEstablishedNotification::class, function (MonitorBaselineEstablishedNotification $notification) {
        return $notification->state->reachable === false;
    });
});

it('does not use failed selector checks as future baselines', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('h1')->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'http_status' => 200,
        'body_bytes' => 100,
        'selected_content' => 'Example Domain',
        'content_hash' => hash('sha256', 'Example Domain'),
        'change_state' => ChangeState::Baseline,
        'checked_at' => Carbon::now()->subMinutes(2),
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'http_status' => 200,
        'body_bytes' => 100,
        'failure_message' => 'No elements matched the supplied selector.',
        'selected_content' => null,
        'content_hash' => null,
        'change_state' => ChangeState::Failed,
        'checked_at' => Carbon::now()->subMinute(),
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('Change State: unchanged')
        ->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->latest('id')->first()?->change_state)->toBe(ChangeState::Unchanged);

    Notification::assertNothingSent();
});

it('keeps reachable non 2xx responses eligible for contains evaluation', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->contains('Example Domain')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('Example Domain', 404),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('HTTP Status: 404')
        ->expectsOutput('Text Found: yes')
        ->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->sole()->assertion_passed)->toBeTrue();

    Notification::assertSentOnDemand(MonitorBaselineEstablishedNotification::class, fn (MonitorBaselineEstablishedNotification $notification) => $notification->state->httpStatus === 404);
});

it('keeps reachable non 2xx responses eligible for selector evaluation', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('h1')->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Missing Page</h1></body></html>', 404),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('HTTP Status: 404')
        ->expectsOutput('Selected Content: Missing Page')
        ->assertExitCode(0);

    expect(WebpageMonitorCheck::query()->sole()->change_state)->toBe(ChangeState::Baseline);

    Notification::assertSentOnDemand(MonitorBaselineEstablishedNotification::class, fn (MonitorBaselineEstablishedNotification $notification) => $notification->state->httpStatus === 404);
});

it('fails when the requested monitor is missing', function () {
    $this->artisan('webpage-monitor:run', ['monitor' => 999])
        ->expectsOutput('The requested monitor could not be found.')
        ->assertExitCode(1);
});

it('fails without fetching when the requested monitor is inactive', function () {
    $monitor = WebpageMonitor::factory()->inactive()->create([
        'url' => 'https://example.com',
    ]);

    Http::fake();

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])
        ->expectsOutput('The requested monitor is inactive.')
        ->assertExitCode(1);

    Http::assertNothingSent();
    expect(WebpageMonitorCheck::query()->count())->toBe(0);
});

it('compares availability transitions against the immediately preceding check and never the current check', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->availability()->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => false,
        'http_status' => null,
        'failure_message' => 'Timeout',
        'change_state' => ChangeState::Failed,
        'checked_at' => Carbon::parse('2026-07-12 09:58:00'),
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'http_status' => 200,
        'failure_message' => null,
        'change_state' => ChangeState::NotApplicable,
        'checked_at' => Carbon::parse('2026-07-12 09:59:00'),
    ]);

    Http::fake([
        'https://example.com' => Http::failedConnection('Connection timed out'),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])->assertExitCode(1);

    Notification::assertSentOnDemand(MonitorBecameUnavailableNotification::class, 1);
});

it('compares contains transitions against the immediately preceding reachable contains check', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->contains('Example Domain')->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'assertion_passed' => false,
        'change_state' => ChangeState::NotApplicable,
        'checked_at' => Carbon::parse('2026-07-12 09:58:00'),
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'assertion_passed' => true,
        'change_state' => ChangeState::NotApplicable,
        'checked_at' => Carbon::parse('2026-07-12 09:59:00'),
    ]);

    Http::fake([
        'https://example.com' => Http::response('Different Text', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])->assertExitCode(1);

    Notification::assertSentOnDemand(MonitorAssertionFailedNotification::class, 1);
});

it('compares selector transitions against the immediately preceding successful selector check', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('h1')->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'selected_content' => 'Alpha',
        'content_hash' => hash('sha256', 'Alpha'),
        'change_state' => ChangeState::Baseline,
        'checked_at' => Carbon::parse('2026-07-12 09:58:00'),
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'selected_content' => 'Beta',
        'content_hash' => hash('sha256', 'Beta'),
        'change_state' => ChangeState::Changed,
        'checked_at' => Carbon::parse('2026-07-12 09:59:00'),
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html><body><h1>Beta</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])->assertExitCode(0);

    Notification::assertNothingSent();
});

it('sends recovery when a monitor becomes reachable again', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->availability()->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => false,
        'http_status' => null,
        'failure_message' => 'Timeout',
        'change_state' => ChangeState::Failed,
        'checked_at' => Carbon::parse('2026-07-12 09:59:00'),
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])->assertExitCode(0);

    Notification::assertSentOnDemand(MonitorRecoveredNotification::class, function (MonitorRecoveredNotification $notification) {
        return $notification->context === 'availability';
    });
});

it('sends recovery when a contains assertion starts passing again', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->contains('Example Domain')->create([
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'reachable' => true,
        'assertion_passed' => false,
        'change_state' => ChangeState::NotApplicable,
        'checked_at' => Carbon::parse('2026-07-12 09:59:00'),
    ]);

    Http::fake([
        'https://example.com' => Http::response('Example Domain', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])->assertExitCode(0);

    Notification::assertSentOnDemand(MonitorRecoveredNotification::class, function (MonitorRecoveredNotification $notification) {
        return $notification->context === 'contains';
    });
});

it('suppresses notifications when globally disabled or recipients are empty', function () {
    Notification::fake();

    config()->set('webpage-monitor.notifications.enabled', false);
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    $monitor = WebpageMonitor::factory()->availability()->create([
        'url' => 'https://example.com',
    ]);

    Http::fake([
        'https://example.com' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $monitor->id])->assertExitCode(0);

    Notification::assertNothingSent();

    Notification::fake();

    config()->set('webpage-monitor.notifications.enabled', true);
    config()->set('webpage-monitor.notifications.mail.recipients', []);

    $secondMonitor = WebpageMonitor::factory()->availability()->create([
        'url' => 'https://example.com/2',
    ]);

    Http::fake([
        'https://example.com/2' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $secondMonitor->id])->assertExitCode(0);

    Notification::assertNothingSent();
});

it('suppresses baseline, recovery, lifecycle, or mail notifications when their category is disabled', function () {
    $availabilityMonitor = WebpageMonitor::factory()->availability()->create(['url' => 'https://example.com/a']);
    $recoveryMonitor = WebpageMonitor::factory()->availability()->create(['url' => 'https://example.com/b']);
    $lifecycleMonitor = WebpageMonitor::factory()->create(['url' => 'https://example.com/c']);

    Notification::fake();

    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);
    config()->set('webpage-monitor.notifications.baseline_enabled', false);

    Http::fake([
        'https://example.com/a' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $availabilityMonitor->id])->assertExitCode(0);

    Notification::assertNothingSent();

    Notification::fake();

    config()->set('webpage-monitor.notifications.baseline_enabled', true);
    config()->set('webpage-monitor.notifications.recovery_enabled', false);

    WebpageMonitorCheck::factory()->for($recoveryMonitor, 'monitor')->create([
        'reachable' => false,
        'http_status' => null,
        'failure_message' => 'Timeout',
        'change_state' => ChangeState::Failed,
        'checked_at' => Carbon::parse('2026-07-12 09:59:00'),
    ]);

    Http::fake([
        'https://example.com/b' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $recoveryMonitor->id])->assertExitCode(0);

    Notification::assertNothingSent();

    Notification::fake();

    config()->set('webpage-monitor.notifications.recovery_enabled', true);
    config()->set('webpage-monitor.notifications.lifecycle_enabled', false);

    $this->artisan('webpage-monitor:disable', ['monitor' => $lifecycleMonitor->id])->assertExitCode(0);

    Notification::assertNothingSent();

    Notification::fake();

    config()->set('webpage-monitor.notifications.lifecycle_enabled', true);
    config()->set('webpage-monitor.notifications.mail.enabled', false);

    $mailDisabledMonitor = WebpageMonitor::factory()->availability()->create(['url' => 'https://example.com/d']);

    Http::fake([
        'https://example.com/d' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:run', ['monitor' => $mailDisabledMonitor->id])->assertExitCode(0);

    Notification::assertNothingSent();
});
