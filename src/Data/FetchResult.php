<?php

namespace Rivetworks\WebpageMonitor\Data;

/**
 * Immutable outcome of one webpage fetch attempt.
 *
 * A result is considered reachable when an HTTP response was received, even if the
 * status code indicates a client or server error.
 */
final readonly class FetchResult
{
    /**
     * @param  string  $requestedUrl  The absolute URL that was requested.
     * @param  bool  $reachable  True when the remote server returned any HTTP response.
     * @param  ?int  $statusCode  The HTTP status code when the server was reached.
     * @param  int  $durationMilliseconds  Total request duration measured by the fetcher.
     * @param  int  $bodyBytes  The response body size in bytes, or 0 when unreachable.
     * @param  string  $body  The raw response body, or an empty string when unreachable.
     * @param  ?string  $failureMessage  A concise transport failure message when unreachable.
     */
    public function __construct(
        public string $requestedUrl,
        public bool $reachable,
        public ?int $statusCode,
        public int $durationMilliseconds,
        public int $bodyBytes,
        public string $body,
        public ?string $failureMessage,
    ) {}
}
