<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // pages on layouts/modern load Vite assets; PHPUnit doesn't need them built (the browser tests do)
        $this->withoutVite();
    }
}
