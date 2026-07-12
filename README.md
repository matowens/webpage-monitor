# Webpage Monitor

Webpage Monitor is a Laravel package for checking public webpages, saving monitors, running scheduled checks, extracting selector content, detecting selector changes, and sending state-aware mail notifications.

It supports three monitor types:

- availability
- contains
- selector

This package is the extracted reusable monitoring capability behind the Rivetworks host application.

## Requirements

- PHP 8.3+
- Laravel 13
- `ext-dom`
- A database supported by Laravel

## Installation

Install the package from Composer:

```bash
composer require matowens/webpage-monitor
```

Laravel package discovery will register the service provider automatically.

## Configuration

Publish the package configuration if you want to override the defaults:

```bash
php artisan vendor:publish --tag=webpage-monitor-config
```

Current configuration covers:

- HTTP request timeout
- default user agent
- notification enablement
- mail recipient list
- baseline, recovery, and lifecycle notification toggles
- selector-change content truncation for displayed mail content

## Migrations

The package loads its migrations automatically during `php artisan migrate`.

If you prefer copied migration files in your application, publish them with:

```bash
php artisan vendor:publish --tag=webpage-monitor-migrations
```

Then run:

```bash
php artisan migrate
```

## Commands

### One-off check

```bash
php artisan webpage-monitor:check https://example.com
php artisan webpage-monitor:check https://example.com --contains="Example Domain"
php artisan webpage-monitor:check https://example.com --selector="h1"
```

This command performs a single fetch and reports:

- requested URL
- reachability
- HTTP status when available
- duration in milliseconds
- body size in bytes
- failure message for transport failures

Selector mode also reports:

- selector
- match count
- normalized selected content from the first match
- SHA-256 hash of the normalized content

### Saved monitors

Create a monitor:

```bash
php artisan webpage-monitor:create "Homepage" https://example.com
php artisan webpage-monitor:create "Homepage Text" https://example.com --contains="Example Domain"
php artisan webpage-monitor:create "Homepage Heading" https://example.com --selector="h1"
php artisan webpage-monitor:create "Scheduled Homepage" https://example.com --every=15
```

Run a saved monitor manually:

```bash
php artisan webpage-monitor:run 1
```

Run due monitors:

```bash
php artisan webpage-monitor:run-due
```

Enable, disable, or delete a monitor:

```bash
php artisan webpage-monitor:enable 1
php artisan webpage-monitor:disable 1
php artisan webpage-monitor:delete 1
```

## Scheduler Setup

Register the scheduler in your application so `webpage-monitor:run-due` runs every minute.

This package expects the host application to own scheduler registration. Rivetworks does this in `routes/console.php`.

## Queue Modes

### Minimal synchronous mode

Set:

```dotenv
QUEUE_CONNECTION=sync
```

In this mode the same scheduled-monitor job executes immediately inside the scheduler process. This is useful for simple environments without a persistent worker.

### Asynchronous worker mode

Set:

```dotenv
QUEUE_CONNECTION=database
```

Then run a queue worker, for example:

```bash
php artisan queue:work database --queue=default --sleep=1 --max-jobs=100 --max-time=3600
```

This mode is recommended for continuous scheduled monitoring.

### Worker restart after changes

Queue workers are long-running processes. After deployments or local package class changes, restart them so they reload the current Composer autoloader and package classes:

```bash
php artisan queue:restart
```

If your worker runs in a container or supervisor-managed process, make sure that process actually restarts after Laravel receives the restart signal.

## Notification Behavior

Mail is the only notification channel in v1.

Recipients are configured at the package level, not per monitor.

Supported notifications:

- baseline established
- became unavailable
- recovered from unavailable
- contains assertion failed
- contains assertion recovered
- selector content changed
- monitor disabled
- monitor deleted

Behavior notes:

- availability and contains baselines are established from the first completed check
- selector baseline is established only after the first successful selector extraction
- selector extraction failures do not establish a selector baseline
- selector extraction failures do not emit failure or recovery notifications in v1
- notifications are deduplicated from persisted state transitions, not from every run
- notification delivery failures do not roll back completed checks or schedule advancement

## Current Limitations

Version 1 does not include:

- browser rendering or JavaScript execution
- CAPTCHA or bot-protection bypassing
- proxy support
- retention or pruning commands
- monitor inspection commands
- per-monitor recipients
- Slack, SMS, Discord, webhook, or push notifications
- UI dashboards or public routes

## Testing

Run package tests from inside the package repository:

```bash
composer install
vendor/bin/pest
vendor/bin/pint --test
```

## New Project Notice

This is a focused v1 package extracted from a working application. Review the code, tests, configuration, and operational behavior carefully before using it in production. It is provided without warranty.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
