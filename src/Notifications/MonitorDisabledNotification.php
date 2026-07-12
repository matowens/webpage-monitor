<?php

namespace Rivetworks\WebpageMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Announces that a saved monitor has been disabled.
 */
class MonitorDisabledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $monitorId,
        public string $monitorName,
        public string $monitorUrl,
        public string $monitorType,
        public Carbon $disabledAt,
    ) {}

    /**
     * Deliver lifecycle disable notifications through mail only in Checkpoint 8.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Render the disabled lifecycle summary using stable scalar data.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Webpage Monitor disabled: '.$this->monitorName)
            ->line('Monitor ID: '.$this->monitorId)
            ->line('Monitor Name: '.$this->monitorName)
            ->line('URL: '.$this->monitorUrl)
            ->line('Type: '.$this->monitorType)
            ->line('Disabled At: '.$this->disabledAt->toDateTimeString());
    }
}
