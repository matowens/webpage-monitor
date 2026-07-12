<?php

namespace Rivetworks\WebpageMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;
use Rivetworks\WebpageMonitor\Enums\MonitorType;

/**
 * Announces the first known state that will serve as the monitor's notification baseline.
 */
class MonitorBaselineEstablishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MonitorStateSnapshot $state,
        public int $maxContentLength,
    ) {}

    /**
     * Deliver baseline notifications through mail only in Checkpoint 8.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Render the monitor-specific baseline message without reloading any models from the queue.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Webpage Monitor baseline established: '.$this->state->monitorName)
            ->line('Monitor ID: '.$this->state->monitorId)
            ->line('Monitor Name: '.$this->state->monitorName)
            ->line('URL: '.$this->state->monitorUrl)
            ->line('Type: '.$this->state->monitorType->value)
            ->line('Check Time: '.$this->state->checkedAt->toDateTimeString());

        return match ($this->state->monitorType) {
            MonitorType::Availability => $message
                ->line('Initial availability state: '.($this->state->reachable ? 'reachable' : 'unavailable')),
            MonitorType::Contains => $message
                ->line('Expected Text: '.$this->state->target)
                ->line('Initial contains state: '.($this->state->assertionPassed ? 'passing' : 'failing')),
            MonitorType::Selector => $message
                ->line('Selector: '.$this->state->target)
                ->line('Selected Content: '.Str::limit($this->state->selectedContent ?? '', $this->maxContentLength, preserveWords: false))
                ->line('Content Hash: '.$this->state->contentHash),
        };
    }
}
