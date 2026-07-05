<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_disabled(): void
    {
        $response = $this->get('/register');

        // La ruta debe devolver 404 porque fue comentada en routes/auth.php
        $response->assertStatus(404);
    }
}
