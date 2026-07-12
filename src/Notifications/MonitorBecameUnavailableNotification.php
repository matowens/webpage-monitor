<?php

namespace Rivetworks\WebpageMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Announces that a previously reachable monitor became unavailable.
 */
class MonitorBecameUnavailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MonitorStateSnapshot $state) {}

    /**
     * Deliver unavailable notifications through mail only in Checkpoint 8.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Render the current unavailable result using the persisted check snapshot.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Webpage Monitor unavailable: '.$this->state->monitorName)
            ->line('Monitor ID: '.$this->state->monitorId)
            ->line('Monitor Name: '.$this->state->monitorName)
            ->line('URL: '.$this->state->monitorUrl)
            ->line('Check Time: '.$this->state->checkedAt->toDateTimeString())
            ->line('Status: unavailable')
            ->line('Failure: '.($this->state->failureMessage ?? 'No failure message was recorded.'));
    }
}
