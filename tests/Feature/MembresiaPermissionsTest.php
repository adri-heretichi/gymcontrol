<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembresiaPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_membresias_fenix_se_muestran_y_el_crud_legacy_no_se_expone(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        $this->actingAs($admin);

        $this->get('/membresias')
            ->assertOk()
            ->assertSee('Membresías');
        $this->get('/membresias/crear')->assertNotFound();
        $this->get('/membresias/1/editar')->assertNotFound();
    }
}
