<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Rivetworks\WebpageMonitor\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
