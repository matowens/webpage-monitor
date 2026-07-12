<?php

use Illuminate\Support\Facades\Http;
use Rivetworks\WebpageMonitor\Actions\FetchWebpage;

it('fetches a successful webpage response', function () {
    Http::fake([
        'https://example.com/*' => Http::response('body', 200),
    ]);

    $result = app(FetchWebpage::class)->execute('https://example.com/test');

    expect($result->requestedUrl)->toBe('https://example.com/test')
        ->and($result->reachable)->toBeTrue()
        ->and($result->statusCode)->toBe(200)
        ->and($result->bodyBytes)->toBe(4)
        ->and($result->body)->toBe('body')
        ->and($result->failureMessage)->toBeNull()
        ->and($result->durationMilliseconds)->toBeGreaterThanOrEqual(0);
});

it('treats non 2xx responses as reachable', function () {
    Http::fake([
        'https://example.com/*' => Http::response('missing', 404),
    ]);

    $result = app(FetchWebpage::class)->execute('https://example.com/missing');

    expect($result->requestedUrl)->toBe('https://example.com/missing')
        ->and($result->reachable)->toBeTrue()
        ->and($result->statusCode)->toBe(404)
        ->and($result->bodyBytes)->toBe(7)
        ->and($result->body)->toBe('missing')
        ->and($result->failureMessage)->toBeNull()
        ->and($result->durationMilliseconds)->toBeGreaterThanOrEqual(0);
});

it('reports connection failures as unreachable', function () {
    Http::fake([
        'https://example.com/*' => Http::failedConnection('Connection timed out'),
    ]);

    $result = app(FetchWebpage::class)->execute('https://example.com/timeout');

    expect($result->requestedUrl)->toBe('https://example.com/timeout')
        ->and($result->reachable)->toBeFalse()
        ->and($result->statusCode)->toBeNull()
        ->and($result->bodyBytes)->toBe(0)
        ->and($result->body)->toBe('')
        ->and($result->failureMessage)->toContain('Connection timed out')
        ->and($result->durationMilliseconds)->toBeGreaterThanOrEqual(0);
});
