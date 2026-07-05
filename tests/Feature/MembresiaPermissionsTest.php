<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Membresia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class MembresiaPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Membresia $membresia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'rol' => 'admin',
        ]);

        $this->recepcionista = User::factory()->create([
            'rol' => 'recepcionista',
        ]);

        $this->membresia = Membresia::create([
            'nombre' => 'Pase Mensual',
            'precio' => 12000.00,
            'horas_mensuales' => null,
            'estado' => 'activo',
        ]);
    }

    /**
     * Test de accesos de membresía para Administrador.
     */
    public function test_administrador_puede_gestionar_membresias(): void
    {
        $this->actingAs($this->admin);

        // Puede ver listado, crear y editar
        $this->get('/membresias')->assertStatus(200);
        $this->get('/membresias/crear')->assertStatus(200);
        $this->get('/membresias/' . $this->membresia->id . '/editar')->assertStatus(200);
    }

    /**
     * Test de accesos de membresía para Recepcionista.
     */
    public function test_recepcionista_solo_puede_ver_membresias(): void
    {
        $this->actingAs($this->recepcionista);

        // Puede ver listado
        $this->get('/membresias')->assertStatus(200);

        // NO puede crear ni editar (recibe 403)
        $this->get('/membresias/crear')->assertStatus(403);
        $this->get('/membresias/' . $this->membresia->id . '/editar')->assertStatus(403);
    }

    /**
     * Test de lógica de creación de membresía.
     */
    public function test_creacion_y_edicion_de_membresia(): void
    {
        $this->actingAs($this->admin);

        // Creamos una membresía mediante Livewire
        Livewire::test(\App\Livewire\Membresias\CrearMembresia::class)
            ->set('nombre', 'Pase Premium')
            ->set('precio', '18000.50')
            ->set('horas_mensuales', 40)
            ->call('guardar')
            ->assertRedirect(route('membresias.index'));

        $this->assertDatabaseHas('membresias', [
            'nombre' => 'Pase Premium',
            'precio' => 18000.50,
            'horas_mensuales' => 40,
            'estado' => 'activo',
        ]);

        // Editamos la membresía
        $creada = Membresia::where('nombre', 'Pase Premium')->first();
        Livewire::test(\App\Livewire\Membresias\EditarMembresia::class, ['membresia' => $creada])
            ->set('precio', '20000.00')
            ->set('estado', 'inactivo')
            ->call('actualizar')
            ->assertRedirect(route('membresias.index'));

        $this->assertDatabaseHas('membresias', [
            'id' => $creada->id,
            'precio' => 20000.00,
            'estado' => 'inactivo',
        ]);
    }
}
