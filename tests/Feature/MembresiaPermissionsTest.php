<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembresiaPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_membresia_crud_no_se_expone_como_modulo_web(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        $this->actingAs($admin);

        $this->get('/membresias')->assertNotFound();
        $this->get('/membresias/crear')->assertNotFound();
        $this->get('/membresias/1/editar')->assertNotFound();
    }
}
