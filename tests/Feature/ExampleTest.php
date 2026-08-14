<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_status_page_is_available(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Project Monitoring System')
            ->assertSee('Fondasi aplikasi siap.');
    }
}
