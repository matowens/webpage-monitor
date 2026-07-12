<?php

namespace Rivetworks\WebpageMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Announces that a selector monitor extracted a different normalized value than its prior successful value.
 */
class MonitorContentChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MonitorStateSnapshot $state,
        public string $previousSelectedContent,
        public string $currentSelectedContent,
        public string $previousContentHash,
        public string $currentContentHash,
    ) {}

    /**
     * Deliver selector-change notifications through mail only in Checkpoint 8.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Render a bounded previous-versus-current selector change summary.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Webpage Monitor content changed: '.$this->state->monitorName)
            ->line('Monitor ID: '.$this->state->monitorId)
            ->line('Monitor Name: '.$this->state->monitorName)
            ->line('URL: '.$this->state->monitorUrl)
            ->line('Check Time: '.$this->state->checkedAt->toDateTimeString())
            ->line('Selector: '.$this->state->target)
            ->line('Previous Value: '.$this->previousSelectedContent)
            ->line('Current Value: '.$this->currentSelectedContent)
            ->line('Previous Hash: '.$this->previousContentHash)
            ->line('Current Hash: '.$this->currentContentHash);
    }
}
