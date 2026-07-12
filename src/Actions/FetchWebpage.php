<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Rivetworks\WebpageMonitor\Data\FetchResult;

/**
 * Performs one HTTP GET request and returns a typed fetch result.
 */
class FetchWebpage
{
    /**
     * Fetch a single absolute HTTP or HTTPS URL using package HTTP settings.
     *
     * HTTP 4xx and 5xx responses remain reachable results. Only transport-level
     * failures that raise a ConnectionException are converted into unreachable results.
     * The raw response body is preserved in the fetch result for downstream checks.
     */
    public function execute(string $url): FetchResult
    {
        $startedAt = hrtime(true);

        try {
            $response = Http::timeout((int) config('webpage-monitor.http.timeout'))
                ->withUserAgent((string) config('webpage-monitor.http.user_agent'))
                ->get($url);

            return new FetchResult(
                requestedUrl: $url,
                reachable: true,
                statusCode: $response->status(),
                durationMilliseconds: $this->elapsedMilliseconds($startedAt),
                bodyBytes: strlen($response->body()),
                body: $response->body(),
                failureMessage: null,
            );
        } catch (ConnectionException $exception) {
            return new FetchResult(
                requestedUrl: $url,
                reachable: false,
                statusCode: null,
                durationMilliseconds: $this->elapsedMilliseconds($startedAt),
                bodyBytes: 0,
                body: '',
                failureMessage: $exception->getMessage(),
            );
        }
    }

    /**
     * Convert the high-resolution timer delta into non-negative milliseconds.
     */
    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) floor((hrtime(true) - $startedAt) / 1_000_000));
    }
}
