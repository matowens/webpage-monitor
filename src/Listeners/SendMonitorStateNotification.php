<?php

namespace Rivetworks\WebpageMonitor\Listeners;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use Rivetworks\WebpageMonitor\Actions\ResolveNotificationRecipients;
use Rivetworks\WebpageMonitor\Events\MonitorAssertionFailed;
use Rivetworks\WebpageMonitor\Events\MonitorAssertionRecovered;
use Rivetworks\WebpageMonitor\Events\MonitorBaselineEstablished;
use Rivetworks\WebpageMonitor\Events\MonitorBecameUnavailable;
use Rivetworks\WebpageMonitor\Events\MonitorContentChanged;
use Rivetworks\WebpageMonitor\Events\MonitorDeleted;
use Rivetworks\WebpageMonitor\Events\MonitorDisabled;
use Rivetworks\WebpageMonitor\Events\MonitorRecovered;
use Rivetworks\WebpageMonitor\Notifications\MonitorAssertionFailedNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorBaselineEstablishedNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorBecameUnavailableNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorContentChangedNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorDeletedNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorDisabledNotification;
use Rivetworks\WebpageMonitor\Notifications\MonitorRecoveredNotification;
use Throwable;

/**
 * Maps package domain events into queued Laravel notifications while isolating delivery failures from monitor execution.
 */
class SendMonitorStateNotification
{
    public function __construct(public ResolveNotificationRecipients $resolveNotificationRecipients) {}

    /**
     * Determine whether a notification is enabled, then queue one notification per configured recipient.
     */
    public function handle(object $event): void
    {
        if (! config('webpage-monitor.notifications.enabled', true)
            || ! config('webpage-monitor.notifications.mail.enabled', true)
        ) {
            return;
        }

        $notification = $this->resolveNotification($event);

        if ($notification === null) {
            return;
        }

        foreach ($this->resolveNotificationRecipients->execute() as $recipient) {
            try {
                Notification::send($recipient, $notification);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Resolve the queued notification instance for the current event when its category is enabled.
     */
    private function resolveNotification(object $event): object|null
    {
        return match (true) {
            $event instanceof MonitorBaselineEstablished && config('webpage-monitor.notifications.baseline_enabled', true)
                => new MonitorBaselineEstablishedNotification($event->state, $this->maxContentLength()),

            $event instanceof MonitorBecameUnavailable
                => new MonitorBecameUnavailableNotification($event->state),

            $event instanceof MonitorRecovered && config('webpage-monitor.notifications.recovery_enabled', true)
                => new MonitorRecoveredNotification($event->state),

            $event instanceof MonitorAssertionFailed
                => new MonitorAssertionFailedNotification($event->state),

            $event instanceof MonitorAssertionRecovered && config('webpage-monitor.notifications.recovery_enabled', true)
                => new MonitorRecoveredNotification($event->state, 'contains'),

            $event instanceof MonitorContentChanged
                => new MonitorContentChangedNotification(
                    state: $event->state,
                    previousSelectedContent: Str::limit($event->previousSelectedContent, $this->maxContentLength(), preserveWords: false),
                    currentSelectedContent: Str::limit($event->state->selectedContent ?? '', $this->maxContentLength(), preserveWords: false),
                    previousContentHash: $event->previousContentHash,
                    currentContentHash: $event->state->contentHash ?? '',
                ),

            $event instanceof MonitorDisabled && config('webpage-monitor.notifications.lifecycle_enabled', true)
                => new MonitorDisabledNotification(
                    monitorId: $event->monitor->id,
                    monitorName: $event->monitor->name,
                    monitorUrl: $event->monitor->url,
                    monitorType: $event->monitor->type->value,
                    disabledAt: $event->disabledAt,
                ),

            $event instanceof MonitorDeleted && config('webpage-monitor.notifications.lifecycle_enabled', true)
                => new MonitorDeletedNotification($event->monitor),

            default => null,
        };
    }

    /**
     * Read the display truncation limit once for all content-rich notifications.
     */
    private function maxContentLength(): int
    {
        return (int) config('webpage-monitor.notifications.max_content_length', 200);
    }
}
