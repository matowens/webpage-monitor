<?php

use Rivetworks\WebpageMonitor\WebpageMonitorServiceProvider;

it('loads the webpage monitor package configuration', function () {
    expect(app()->providerIsLoaded(WebpageMonitorServiceProvider::class))->toBeTrue()
        ->and(config('webpage-monitor'))->toBeArray()
        ->and(config('webpage-monitor.http.timeout'))->toBe(10)
        ->and(config('webpage-monitor.http.user_agent'))->toBe('Rivetworks Webpage Monitor/0.1');
});
