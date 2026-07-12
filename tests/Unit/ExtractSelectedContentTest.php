<?php

use Rivetworks\WebpageMonitor\Actions\ExtractSelectedContent;
use Rivetworks\WebpageMonitor\Exceptions\SelectorExtractionException;

it('extracts content when a selector matches one element', function () {
    $result = new ExtractSelectedContent()->execute('<html><body><h1>Example Domain</h1></body></html>', 'h1');

    expect($result->selector)->toBe('h1')
        ->and($result->matchCount)->toBe(1)
        ->and($result->selectedContent)->toBe('Example Domain')
        ->and($result->contentHash)->toBe('162b81548a8db0ab220f7137405fb003019c266883d4181869662ad7eafdbc4d');
});

it('extracts the first match when multiple elements match', function () {
    $result = new ExtractSelectedContent()->execute('<html><body><h1>First</h1><h1>Second</h1></body></html>', 'h1');

    expect($result->matchCount)->toBe(2)
        ->and($result->selectedContent)->toBe('First')
        ->and($result->contentHash)->toBe('a151ceb1711aad529a7704248f03333990022ebbfa07a7f04c004d70c167919f');
});

it('throws when no selector matches are found', function () {
    expect(fn () => new ExtractSelectedContent()->execute('<html><body><h1>Example</h1></body></html>', 'p'))
        ->toThrow(SelectorExtractionException::class, 'No elements matched the supplied selector.');
});

it('throws when selector syntax is invalid', function () {
    expect(fn () => new ExtractSelectedContent()->execute('<html><body><h1>Example</h1></body></html>', 'h1['))
        ->toThrow(SelectorExtractionException::class, 'The CSS selector syntax is invalid.');
});

it('extracts nested html text content', function () {
    $result = new ExtractSelectedContent()->execute('<html><body><h1>Hello <span>World</span></h1></body></html>', 'h1');

    expect($result->selectedContent)->toBe('Hello World');
});

it('normalizes consecutive whitespace before hashing', function () {
    $result = new ExtractSelectedContent()->execute("<html><body><h1>  Hello \n\t World   Again </h1></body></html>", 'h1');

    expect($result->selectedContent)->toBe('Hello World Again')
        ->and($result->contentHash)->toBe(hash('sha256', 'Hello World Again'));
});

it('hashes the empty string when normalized content is empty', function () {
    $result = new ExtractSelectedContent()->execute('<html><body><div><span>   </span></div></body></html>', 'div');

    expect($result->selectedContent)->toBe('')
        ->and($result->contentHash)->toBe(hash('sha256', ''));
});

it('parses recoverable malformed html when possible', function () {
    $result = new ExtractSelectedContent()->execute('<html><body><div><h1>Example Domain', 'h1');

    expect($result->matchCount)->toBe(1)
        ->and($result->selectedContent)->toBe('Example Domain');
});
