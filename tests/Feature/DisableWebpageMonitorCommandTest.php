<?php

use Illuminate\Support\Facades\Notification;
use Rivetworks\WebpageMonitor\Notifications\MonitorDisabledNotification;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

it('disables an active monitor and sends one disabled notification', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->create([
        'name' => 'Availability Monitor',
    ]);

    $this->artisan('webpage-monitor:disable', ['monitor' => $monitor->id])
        ->expectsOutput('Monitor disabled: Availability Monitor')
        ->assertExitCode(0);

    expect($monitor->fresh()->is_active)->toBeFalse();

    Notification::assertSentOnDemand(MonitorDisabledNotification::class, function (MonitorDisabledNotification $notification, array $channels, object $notifiable) use ($monitor) {
        return $notification->monitorId === $monitor->id
            && $notification->monitorName === 'Availability Monitor'
            && $notifiable->routeNotificationFor('mail') === 'ops@example.com';
    });
});

it('does not notify when disabling an already inactive monitor', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->inactive()->create();

    $this->artisan('webpage-monitor:disable', ['monitor' => $monitor->id])
        ->expectsOutput('The requested monitor is already inactive.')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('enables an inactive monitor without sending a notification', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->inactive()->create([
        'name' => 'Availability Monitor',
    ]);

    $this->artisan('webpage-monitor:enable', ['monitor' => $monitor->id])
        ->expectsOutput('Monitor enabled: Availability Monitor')
        ->assertExitCode(0);

    expect($monitor->fresh()->is_active)->toBeTrue();

    Notification::assertNothingSent();
});

it('fails cleanly when disabling a missing monitor', function () {
    Notification::fake();

    $this->artisan('webpage-monitor:disable', ['monitor' => 999999])
        ->expectsOutput('The requested monitor could not be found.')
        ->assertExitCode(1);

    Notification::assertNothingSent();
});
