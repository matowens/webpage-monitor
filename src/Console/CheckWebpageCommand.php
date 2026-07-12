<?php

namespace Rivetworks\WebpageMonitor\Console;

use Illuminate\Console\Command;
use Rivetworks\WebpageMonitor\Actions\ExtractSelectedContent;
use Rivetworks\WebpageMonitor\Actions\FetchWebpage;
use Rivetworks\WebpageMonitor\Data\FetchResult;
use Rivetworks\WebpageMonitor\Data\SelectedContentResult;
use Rivetworks\WebpageMonitor\Exceptions\SelectorExtractionException;
use Rivetworks\WebpageMonitor\Matching\ExactTextMatcher;

/**
 * Runs a one-off webpage check and renders a readable fetch summary.
 */
class CheckWebpageCommand extends Command
{
    protected $signature = 'webpage-monitor:check {url} {--contains=} {--selector=}';

    protected $description = 'Fetch a webpage once and report basic reachability details.';

    /**
     * Execute the console command.
     *
     * Returns success for any completed HTTP response, including 4xx and 5xx results.
     * When --contains is supplied, the command also fails if the expected text is
     * empty, whitespace-only, or absent from the reachable response body. When
     * --selector is supplied, the command fails if selector validation or extraction
     * cannot be completed.
     */
    public function handle(
        FetchWebpage $fetchWebpage,
        ExactTextMatcher $exactTextMatcher,
        ExtractSelectedContent $extractSelectedContent,
    ): int
    {
        $url = (string) $this->argument('url');
        $expectedText = $this->option('contains');
        $selector = $this->option('selector');

        if (! $this->isValidAbsoluteHttpUrl($url)) {
            $this->error('The URL must be a valid absolute HTTP or HTTPS URL.');

            return self::FAILURE;
        }

        if ($this->containsOptionWasSupplied($expectedText) && $this->selectorOptionWasSupplied($selector)) {
            $this->error('The --contains and --selector options cannot be used together.');

            return self::FAILURE;
        }

        if ($this->containsOptionWasSupplied($expectedText) && $this->expectedTextIsInvalid($expectedText)) {
            $this->error('The --contains value must be a non-empty string.');

            return self::FAILURE;
        }

        if ($this->selectorOptionWasSupplied($selector) && $this->selectorIsInvalid($selector)) {
            $this->error('The --selector value must be a non-empty string.');

            return self::FAILURE;
        }

        $result = $fetchWebpage->execute($url);

        if (! $result->reachable) {
            $this->renderResult($result);

            return self::FAILURE;
        }

        if ($this->selectorOptionWasSupplied($selector)) {
            try {
                $selectedContentResult = $extractSelectedContent->execute($result->body, $selector);
            } catch (SelectorExtractionException $exception) {
                $this->renderResult($result);
                $this->line('Selector: '.$selector);
                $this->line('Failure: '.$exception->getMessage());

                return self::FAILURE;
            }

            $this->renderResult($result, selectedContentResult: $selectedContentResult);

            return self::SUCCESS;
        }

        if (! $this->containsOptionWasSupplied($expectedText)) {
            $this->renderResult($result);

            return self::SUCCESS;
        }

        $textWasFound = $exactTextMatcher->matches($result->body, $expectedText);

        $this->renderResult($result, expectedText: $expectedText, textWasFound: $textWasFound);

        return $textWasFound ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Determine whether the given value is an absolute HTTP or HTTPS URL.
     */
    private function isValidAbsoluteHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Determine whether the contains option was explicitly supplied with a value.
     */
    private function containsOptionWasSupplied(mixed $expectedText): bool
    {
        return $expectedText !== null;
    }

    /**
     * Determine whether the supplied expected text is empty after whitespace-only checking.
     */
    private function expectedTextIsInvalid(mixed $expectedText): bool
    {
        return ! is_string($expectedText) || trim($expectedText) === '';
    }

    /**
     * Determine whether the selector option was explicitly supplied with a value.
     */
    private function selectorOptionWasSupplied(mixed $selector): bool
    {
        return $selector !== null;
    }

    /**
     * Determine whether the supplied selector is empty after whitespace-only checking.
     */
    private function selectorIsInvalid(mixed $selector): bool
    {
        return ! is_string($selector) || trim($selector) === '';
    }

    /**
     * Render the fetch result in a stable human-readable format.
     *
     * Contains and selector details are rendered only for the mode that was actually
     * requested.
     */
    private function renderResult(
        FetchResult $result,
        ?string $expectedText = null,
        ?bool $textWasFound = null,
        ?SelectedContentResult $selectedContentResult = null,
    ): void
    {
        $this->line('Requested URL: '.$result->requestedUrl);
        $this->line('Reachable: '.($result->reachable ? 'yes' : 'no'));
        $this->line('HTTP Status: '.($result->statusCode ?? 'n/a'));
        $this->line('Duration: '.$result->durationMilliseconds.' ms');
        $this->line('Body Size: '.$result->bodyBytes.' bytes');

        if ($expectedText !== null) {
            $this->line('Expected Text: '.$expectedText);
            $this->line('Text Found: '.($textWasFound ? 'yes' : 'no'));
        }

        if ($selectedContentResult !== null) {
            $this->line('Selector: '.$selectedContentResult->selector);
            $this->line('Matches: '.$selectedContentResult->matchCount);
            $this->line('Selected Content: '.$selectedContentResult->selectedContent);
            $this->line('Content Hash: '.$selectedContentResult->contentHash);
        }

        if ($result->failureMessage !== null) {
            $this->line('Failure: '.$result->failureMessage);
        }
    }
}
