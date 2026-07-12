<?php

namespace Rivetworks\WebpageMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rivetworks\WebpageMonitor\Data\DeletedMonitorSnapshot;

/**
 * Announces that a saved monitor has been deleted using only immutable pre-deletion snapshot data.
 */
class MonitorDeletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DeletedMonitorSnapshot $monitor) {}

    /**
     * Deliver lifecycle deletion notifications through mail only in Checkpoint 8.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Render the deletion summary without ever attempting to reload the deleted model.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Webpage Monitor deleted: '.$this->monitor->monitorName)
            ->line('Monitor ID: '.$this->monitor->monitorId)
            ->line('Monitor Name: '.$this->monitor->monitorName)
            ->line('URL: '.$this->monitor->monitorUrl)
            ->line('Type: '.$this->monitor->monitorType->value)
            ->line('Deleted At: '.$this->monitor->deletedAt->toDateTimeString());
    }
}
