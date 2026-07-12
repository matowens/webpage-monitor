<?php

namespace Rivetworks\WebpageMonitor\Actions;

use DOMDocument;
use DOMXPath;
use Rivetworks\WebpageMonitor\Data\SelectedContentResult;
use Rivetworks\WebpageMonitor\Exceptions\SelectorExtractionException;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\CssSelector\Exception\ParseException;

/**
 * Extracts normalized text from the first HTML element matched by a CSS selector.
 */
class ExtractSelectedContent
{
    /**
     * Extract selector content from static HTML and hash the normalized result.
     *
     * The first matching element is used for extraction, while the full match count
     * is still reported to the caller.
     *
     * @throws SelectorExtractionException
     */
    public function execute(string $html, string $selector): SelectedContentResult
    {
        try {
            $xpathExpression = (new CssSelectorConverter(html: true))->toXPath($selector);
        } catch (ParseException $exception) {
            throw new SelectorExtractionException('The CSS selector syntax is invalid.', previous: $exception);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML($html);

            if (! $loaded) {
                throw new SelectorExtractionException('The HTML document could not be parsed for selector evaluation.');
            }

            $matches = (new DOMXPath($document))->query($xpathExpression);

            if ($matches === false) {
                throw new SelectorExtractionException('The selector could not be evaluated against the HTML document.');
            }

            $matchCount = $matches->length;

            if ($matchCount === 0) {
                throw new SelectorExtractionException('No elements matched the supplied selector.');
            }

            $selectedContent = $this->normalizeWhitespace($matches->item(0)?->textContent ?? '');

            return new SelectedContentResult(
                selector: $selector,
                matchCount: $matchCount,
                selectedContent: $selectedContent,
                contentHash: hash('sha256', $selectedContent),
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    /**
     * Collapse runs of whitespace into single spaces and trim the result.
     */
    private function normalizeWhitespace(string $content): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $content));
    }
}
