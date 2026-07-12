<?php

return [
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Rivetworks Webpage Monitor/0.1',
    ],
    'notifications' => [
        'enabled' => env('WEBPAGE_MONITOR_NOTIFICATIONS_ENABLED', true),
        'mail' => [
            'enabled' => env('WEBPAGE_MONITOR_MAIL_NOTIFICATIONS_ENABLED', true),
            'recipients' => array_values(array_filter(array_map(
                static fn (string $recipient): string => trim($recipient),
                explode(',', (string) env('WEBPAGE_MONITOR_NOTIFICATION_RECIPIENTS', ''))
            ))),
        ],
        'max_content_length' => (int) env('WEBPAGE_MONITOR_NOTIFICATION_MAX_CONTENT_LENGTH', 200),
        'baseline_enabled' => env('WEBPAGE_MONITOR_BASELINE_NOTIFICATIONS_ENABLED', true),
        'recovery_enabled' => env('WEBPAGE_MONITOR_RECOVERY_NOTIFICATIONS_ENABLED', true),
        'lifecycle_enabled' => env('WEBPAGE_MONITOR_LIFECYCLE_NOTIFICATIONS_ENABLED', true),
    ],
];
