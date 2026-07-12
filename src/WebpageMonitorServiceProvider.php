<?php

namespace Rivetworks\WebpageMonitor;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Rivetworks\WebpageMonitor\Console\CheckWebpageCommand;
use Rivetworks\WebpageMonitor\Console\CreateWebpageCommand;
use Rivetworks\WebpageMonitor\Console\DeleteWebpageMonitorCommand;
use Rivetworks\WebpageMonitor\Console\DisableWebpageMonitorCommand;
use Rivetworks\WebpageMonitor\Console\EnableWebpageMonitorCommand;
use Rivetworks\WebpageMonitor\Console\RunDueWebpageMonitorsCommand;
use Rivetworks\WebpageMonitor\Console\RunWebpageMonitorCommand;
use Rivetworks\WebpageMonitor\Events\MonitorAssertionFailed;
use Rivetworks\WebpageMonitor\Events\MonitorAssertionRecovered;
use Rivetworks\WebpageMonitor\Events\MonitorBaselineEstablished;
use Rivetworks\WebpageMonitor\Events\MonitorBecameUnavailable;
use Rivetworks\WebpageMonitor\Events\MonitorContentChanged;
use Rivetworks\WebpageMonitor\Events\MonitorDeleted;
use Rivetworks\WebpageMonitor\Events\MonitorDisabled;
use Rivetworks\WebpageMonitor\Events\MonitorRecovered;
use Rivetworks\WebpageMonitor\Listeners\SendMonitorStateNotification;

/**
 * Boots the Webpage Monitor package configuration, migrations, and console commands.
 */
class WebpageMonitorServiceProvider extends ServiceProvider
{
    /**
     * Register package services and merge package configuration defaults.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webpage-monitor.php', 'webpage-monitor');
    }

    /**
     * Register package console commands when the application is running via Artisan.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerEventListeners();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webpage-monitor.php' => config_path('webpage-monitor.php'),
            ], 'webpage-monitor-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'webpage-monitor-migrations');

            $this->commands([
                CheckWebpageCommand::class,
                CreateWebpageCommand::class,
                DeleteWebpageMonitorCommand::class,
                DisableWebpageMonitorCommand::class,
                EnableWebpageMonitorCommand::class,
                RunDueWebpageMonitorsCommand::class,
                RunWebpageMonitorCommand::class,
            ]);
        }
    }

    /**
     * Register package-owned event listeners without requiring the host app to edit its event service provider.
     */
    private function registerEventListeners(): void
    {
        foreach ([
            MonitorAssertionFailed::class,
            MonitorAssertionRecovered::class,
            MonitorBaselineEstablished::class,
            MonitorBecameUnavailable::class,
            MonitorContentChanged::class,
            MonitorDeleted::class,
            MonitorDisabled::class,
            MonitorRecovered::class,
        ] as $eventClass) {
            Event::listen($eventClass, SendMonitorStateNotification::class);
        }
    }
}
