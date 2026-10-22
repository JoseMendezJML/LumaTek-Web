<?php

namespace Tests\Feature;

use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use UsesInMemoryDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
