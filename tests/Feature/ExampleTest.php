<?php

namespace Tests\Feature;

class ExampleTest extends FeatureTestCase
{
    public function test_welcome_page_renders(): void
    {
        $this->get('/')->assertOk();
    }
}
