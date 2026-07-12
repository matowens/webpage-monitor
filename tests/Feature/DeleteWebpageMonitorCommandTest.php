<?php

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;
use Rivetworks\WebpageMonitor\Notifications\MonitorDeletedNotification;

it('deletes an existing monitor and queues a deletion notification from immutable snapshot data', function () {
    config()->set('webpage-monitor.notifications.mail.recipients', ['ops@example.com']);

    Notification::fake();

    $monitor = WebpageMonitor::factory()->selector('h1')->create([
        'name' => 'Selector Monitor',
        'url' => 'https://example.com',
    ]);

    WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create();

    $this->artisan('webpage-monitor:delete', ['monitor' => $monitor->id])
        ->expectsOutput('Monitor deleted: Selector Monitor')
        ->assertExitCode(0);

    expect(WebpageMonitor::query()->find($monitor->id))->toBeNull()
        ->and(WebpageMonitorCheck::query()->count())->toBe(0);

    Notification::assertSentOnDemand(MonitorDeletedNotification::class, function (MonitorDeletedNotification $notification, array $channels, object $notifiable) use ($monitor) {
        $mailMessage = $notification->toMail(new AnonymousNotifiable);

        return $notification->monitor->monitorId === $monitor->id
            && $notification->monitor->monitorName === 'Selector Monitor'
            && $notification->monitor->monitorUrl === 'https://example.com'
            && $mailMessage->subject === 'Webpage Monitor deleted: Selector Monitor'
            && $notifiable->routeNotificationFor('mail') === 'ops@example.com';
    });
});

it('fails cleanly when deleting a missing monitor', function () {
    Notification::fake();

    $this->artisan('webpage-monitor:delete', ['monitor' => 999999])
        ->expectsOutput('The requested monitor could not be found.')
        ->assertExitCode(1);

    Notification::assertNothingSent();
});
