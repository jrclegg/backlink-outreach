<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Process;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Short fake pages would otherwise launch a real headless browser.
        Process::fake(['*render-page.mjs*' => Process::result(exitCode: 1)]);
    }
}
