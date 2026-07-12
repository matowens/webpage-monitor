<?php

use Illuminate\Support\Facades\Http;

it('renders a successful webpage check result', function () {
    Http::fake([
        'https://example.com/*' => Http::response('body', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('HTTP Status: 200')
        ->expectsOutputToContain('Duration: ')
        ->expectsOutput('Body Size: 4 bytes')
        ->assertExitCode(0);
});

it('preserves existing behavior when contains is omitted', function () {
    Http::fake([
        'https://example.com/*' => Http::response('Example Domain', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('HTTP Status: 200')
        ->expectsOutput('Body Size: 14 bytes')
        ->doesntExpectOutput('Expected Text:')
        ->doesntExpectOutput('Text Found:')
        ->assertExitCode(0);
});

it('reports when the expected text is found', function () {
    Http::fake([
        'https://example.com/*' => Http::response('Example Domain', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--contains' => 'Example Domain'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('Expected Text: Example Domain')
        ->expectsOutput('Text Found: yes')
        ->assertExitCode(0);
});

it('reports when the expected text is absent', function () {
    Http::fake([
        'https://example.com/*' => Http::response('Example Domain', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--contains' => 'Not Present'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('Expected Text: Not Present')
        ->expectsOutput('Text Found: no')
        ->assertExitCode(1);
});

it('matches expected text case sensitively', function () {
    Http::fake([
        'https://example.com/*' => Http::response('Example Domain', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--contains' => 'example domain'])
        ->expectsOutput('Expected Text: example domain')
        ->expectsOutput('Text Found: no')
        ->assertExitCode(1);
});

it('renders a failed webpage check result', function () {
    Http::fake([
        'https://example.com/*' => Http::failedConnection('Connection timed out'),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: no')
        ->expectsOutput('HTTP Status: n/a')
        ->expectsOutputToContain('Duration: ')
        ->expectsOutput('Body Size: 0 bytes')
        ->expectsOutputToContain('Failure: Connection timed out')
        ->assertExitCode(1);
});

it('prevents content matching after a transport failure', function () {
    Http::fake([
        'https://example.com/*' => Http::failedConnection('Connection timed out'),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--contains' => 'Example Domain'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: no')
        ->expectsOutput('HTTP Status: n/a')
        ->expectsOutput('Body Size: 0 bytes')
        ->expectsOutputToContain('Failure: Connection timed out')
        ->doesntExpectOutput('Expected Text:')
        ->doesntExpectOutput('Text Found:')
        ->assertExitCode(1);
});

it('finds expected text in a reachable non 2xx response', function () {
    Http::fake([
        'https://example.com/*' => Http::response('Missing Example Domain', 404),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--contains' => 'Example Domain'])
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('HTTP Status: 404')
        ->expectsOutput('Expected Text: Example Domain')
        ->expectsOutput('Text Found: yes')
        ->assertExitCode(0);
});

it('rejects invalid urls', function (string $url) {
    $this->artisan('webpage-monitor:check', ['url' => $url])
        ->expectsOutput('The URL must be a valid absolute HTTP or HTTPS URL.')
        ->assertExitCode(1);
})->with([
    'missing scheme' => 'example.com',
    'unsupported scheme' => 'ftp://example.com',
]);

it('rejects an empty expected text value', function () {
    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--contains' => ''])
        ->expectsOutput('The --contains value must be a non-empty string.')
        ->assertExitCode(1);
});

it('rejects a whitespace only expected text value', function () {
    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--contains' => '   '])
        ->expectsOutput('The --contains value must be a non-empty string.')
        ->assertExitCode(1);
});

it('rejects an empty selector value before fetching', function () {
    Http::fake();

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => ''])
        ->expectsOutput('The --selector value must be a non-empty string.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('rejects a whitespace only selector value before fetching', function () {
    Http::fake();

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => '   '])
        ->expectsOutput('The --selector value must be a non-empty string.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('rejects contains and selector together before fetching', function () {
    Http::fake();

    $this->artisan('webpage-monitor:check', [
        'url' => 'https://example.com/test',
        '--contains' => 'Example Domain',
        '--selector' => 'h1',
    ])
        ->expectsOutput('The --contains and --selector options cannot be used together.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('renders selector extraction output for one match', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => 'h1'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('HTTP Status: 200')
        ->expectsOutput('Selector: h1')
        ->expectsOutput('Matches: 1')
        ->expectsOutput('Selected Content: Example Domain')
        ->expectsOutput('Content Hash: 162b81548a8db0ab220f7137405fb003019c266883d4181869662ad7eafdbc4d')
        ->assertExitCode(0);
});

it('renders selector extraction output for multiple matches using the first match', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><body><h1>First</h1><h1>Second</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => 'h1'])
        ->expectsOutput('Selector: h1')
        ->expectsOutput('Matches: 2')
        ->expectsOutput('Selected Content: First')
        ->expectsOutput('Content Hash: a151ceb1711aad529a7704248f03333990022ebbfa07a7f04c004d70c167919f')
        ->assertExitCode(0);
});

it('extracts selector content from a reachable non 2xx response', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><body><h1>Missing Page</h1></body></html>', 404),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => 'h1'])
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('HTTP Status: 404')
        ->expectsOutput('Selector: h1')
        ->expectsOutput('Matches: 1')
        ->expectsOutput('Selected Content: Missing Page')
        ->assertExitCode(0);
});

it('prevents selector evaluation after a transport failure', function () {
    Http::fake([
        'https://example.com/*' => Http::failedConnection('Connection timed out'),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => 'h1'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: no')
        ->expectsOutput('HTTP Status: n/a')
        ->expectsOutput('Body Size: 0 bytes')
        ->expectsOutputToContain('Failure: Connection timed out')
        ->doesntExpectOutput('Selector:')
        ->doesntExpectOutput('Matches:')
        ->doesntExpectOutput('Selected Content:')
        ->doesntExpectOutput('Content Hash:')
        ->assertExitCode(1);
});

it('reports invalid selector syntax clearly', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => 'h1['])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('Selector: h1[')
        ->expectsOutput('Failure: The CSS selector syntax is invalid.')
        ->assertExitCode(1);
});

it('reports when no selector matches are found', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><body><h1>Example Domain</h1></body></html>', 200),
    ]);

    $this->artisan('webpage-monitor:check', ['url' => 'https://example.com/test', '--selector' => 'p'])
        ->expectsOutput('Requested URL: https://example.com/test')
        ->expectsOutput('Reachable: yes')
        ->expectsOutput('Selector: p')
        ->expectsOutput('Failure: No elements matched the supplied selector.')
        ->assertExitCode(1);
});
