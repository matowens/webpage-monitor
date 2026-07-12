<?php

namespace Rivetworks\WebpageMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Announces that a monitor recovered from an unavailable or failing state.
 */
class MonitorRecoveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MonitorStateSnapshot $state,
        public string $context = 'availability',
    ) {}

    /**
     * Deliver recovery notifications through mail only in Checkpoint 8.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Render a concise recovery summary for the relevant monitor context.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Webpage Monitor recovered: '.$this->state->monitorName)
            ->line('Monitor ID: '.$this->state->monitorId)
            ->line('Monitor Name: '.$this->state->monitorName)
            ->line('URL: '.$this->state->monitorUrl)
            ->line('Check Time: '.$this->state->checkedAt->toDateTimeString());

        if ($this->context === 'contains') {
            return $message
                ->line('Expected Text: '.$this->state->target)
                ->line('Status: contains assertion is passing again');
        }

        return $message->line('Status: monitor is reachable again');
    }
}
