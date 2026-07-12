<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Resolves config-driven anonymous mail recipients for package notifications.
 */
class ResolveNotificationRecipients
{
    /**
     * Build one anonymous notifiable per configured mail recipient.
     *
     * @return list<AnonymousNotifiable>
     */
    public function execute(): array
    {
        return array_map(
            fn (string $recipient): AnonymousNotifiable => Notification::route('mail', $recipient),
            config('webpage-monitor.notifications.mail.recipients', []),
        );
    }
}
