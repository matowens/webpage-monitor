<?php

namespace Rivetworks\WebpageMonitor\Matching;

/**
 * Performs exact case-sensitive substring checks against raw response content.
 */
class ExactTextMatcher
{
    /**
     * Determine whether the raw response body contains the expected text exactly.
     */
    public function matches(string $body, string $expectedText): bool
    {
        return str_contains($body, $expectedText);
    }
}
