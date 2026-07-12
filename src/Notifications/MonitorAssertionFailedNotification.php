<?php

namespace Rivetworks\WebpageMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Announces that a contains monitor has started failing its expected-text assertion.
 */
class MonitorAssertionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MonitorStateSnapshot $state) {}

    /**
     * Deliver assertion-failure notifications through mail only in Checkpoint 8.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Render the expected-text failure summary from the persisted contains result.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Webpage Monitor assertion failed: '.$this->state->monitorName)
            ->line('Monitor ID: '.$this->state->monitorId)
            ->line('Monitor Name: '.$this->state->monitorName)
            ->line('URL: '.$this->state->monitorUrl)
            ->line('Check Time: '.$this->state->checkedAt->toDateTimeString())
            ->line('Expected Text: '.$this->state->target)
            ->line('Status: contains assertion is failing');
    }
}
