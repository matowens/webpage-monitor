<?php

namespace Rivetworks\WebpageMonitor\Data;

/**
 * Immutable outcome of extracting normalized text from the first selector match.
 */
final readonly class SelectedContentResult
{
    /**
     * @param  string  $selector  The selector that matched successfully.
     * @param  int  $matchCount  Total number of matched elements in the document.
     * @param  string  $selectedContent  Normalized text extracted from the first match.
     * @param  string  $contentHash  Lowercase SHA-256 hash of the normalized text.
     */
    public function __construct(
        public string $selector,
        public int $matchCount,
        public string $selectedContent,
        public string $contentHash,
    ) {}
}
