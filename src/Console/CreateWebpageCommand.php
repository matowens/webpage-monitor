<?php

namespace Rivetworks\WebpageMonitor\Console;

use Illuminate\Console\Command;
use Rivetworks\WebpageMonitor\Actions\CreateWebpageMonitor;

/**
 * Creates a saved webpage monitor definition.
 */
class CreateWebpageCommand extends Command
{
    protected $signature = 'webpage-monitor:create {name} {url} {--contains=} {--selector=} {--every=}';

    protected $description = 'Create a saved webpage monitor.';

    /**
     * Validate command input and create one saved monitor definition.
     *
     * The command exits with failure for invalid URLs, mutually exclusive mode
     * options, or empty contains/selector targets.
     */
    public function handle(CreateWebpageMonitor $createWebpageMonitor): int
    {
        $name = (string) $this->argument('name');
        $url = (string) $this->argument('url');
        $expectedText = $this->option('contains');
        $selector = $this->option('selector');
        $every = $this->option('every');

        if (! $this->isValidAbsoluteHttpUrl($url)) {
            $this->error('The URL must be a valid absolute HTTP or HTTPS URL.');

            return self::FAILURE;
        }

        if ($this->containsOptionWasSupplied($expectedText) && $this->selectorOptionWasSupplied($selector)) {
            $this->error('The --contains and --selector options cannot be used together.');

            return self::FAILURE;
        }

        if ($this->containsOptionWasSupplied($expectedText) && $this->targetIsInvalid($expectedText)) {
            $this->error('The --contains value must be a non-empty string.');

            return self::FAILURE;
        }

        if ($this->selectorOptionWasSupplied($selector) && $this->targetIsInvalid($selector)) {
            $this->error('The --selector value must be a non-empty string.');

            return self::FAILURE;
        }

        if ($this->everyOptionWasSupplied($every) && $this->intervalIsInvalid($every)) {
            $this->error('The --every value must be a positive integer.');

            return self::FAILURE;
        }

        $monitor = $createWebpageMonitor->execute(
            name: $name,
            url: $url,
            contains: is_string($expectedText) ? $expectedText : null,
            selector: is_string($selector) ? $selector : null,
            everyMinutes: is_string($every) ? (int) $every : null,
        );

        $this->line('Monitor ID: '.$monitor->id);
        $this->line('Name: '.$monitor->name);
        $this->line('URL: '.$monitor->url);
        $this->line('Type: '.$monitor->type->value);

        if ($monitor->target !== null) {
            $this->line('Target: '.$monitor->target);
        }

        return self::SUCCESS;
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
     * Distinguish an omitted option from an explicitly supplied empty string.
     */
    private function containsOptionWasSupplied(mixed $expectedText): bool
    {
        return $expectedText !== null;
    }

    /**
     * Distinguish an omitted option from an explicitly supplied empty string.
     */
    private function selectorOptionWasSupplied(mixed $selector): bool
    {
        return $selector !== null;
    }

    /**
     * Distinguish an omitted interval option from an explicitly supplied empty value.
     */
    private function everyOptionWasSupplied(mixed $every): bool
    {
        return $every !== null;
    }

    /**
     * Reject non-strings and whitespace-only targets before monitor creation.
     */
    private function targetIsInvalid(mixed $target): bool
    {
        return ! is_string($target) || trim($target) === '';
    }

    /**
     * Accept only positive integer minute intervals for scheduled monitors.
     */
    private function intervalIsInvalid(mixed $every): bool
    {
        return ! is_string($every) || preg_match('/^[1-9][0-9]*$/', $every) !== 1;
    }
}
