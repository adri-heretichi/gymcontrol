<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Livewire\Livewire;

class OperadorPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'rol' => 'admin',
        ]);

        $this->recepcionista = User::factory()->create([
            'rol' => 'recepcionista',
        ]);
    }

    /**
     * Test de que el Recepcionista no puede acceder a ninguna ruta de operadores.
     */
    public function test_recepcionista_no_puede_acceder_a_operadores(): void
    {
        $this->actingAs($this->recepcionista);

        $this->get('/operadores')->assertStatus(403);
        $this->get('/operadores/crear')->assertStatus(403);
        $this->get('/operadores/' . $this->admin->id . '/editar')->assertStatus(403);
    }

    /**
     * Test de que el Administrador puede acceder a la gestión de operadores.
     */
    public function test_administrador_puede_acceder_a_operadores(): void
    {
        $this->actingAs($this->admin);

        $this->get('/operadores')->assertStatus(200);
        $this->get('/operadores/crear')->assertStatus(200);
        $this->get('/operadores/' . $this->recepcionista->id . '/editar')->assertStatus(200);
    }

    /**
     * Test de creación de operador por parte del admin.
     */
    public function test_administrador_puede_crear_operador(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(\App\Livewire\Operadores\CrearOperador::class)
            ->set('name', 'Pedro Recepcionista')
            ->set('email', 'pedro@gymcontrol.com')
            ->set('password', 'secret123')
            ->set('rol', 'recepcionista')
            ->call('guardar')
            ->assertRedirect(route('operadores.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Pedro Recepcionista',
            'email' => 'pedro@gymcontrol.com',
            'rol' => 'recepcionista',
        ]);

        $pedro = User::where('email', 'pedro@gymcontrol.com')->first();
        $this->assertTrue(Hash::check('secret123', $pedro->password));
    }

    /**
     * Test de edición y cambio opcional de contraseña por parte del admin.
     */
    public function test_administrador_puede_editar_operador_y_cambiar_password(): void
    {
        $this->actingAs($this->admin);

        $operador = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@gymcontrol.com',
            'rol' => 'recepcionista',
            'password' => Hash::make('password123'),
        ]);

        // Caso 1: Editar sin cambiar contraseña (campo password vacío)
        Livewire::test(\App\Livewire\Operadores\EditarOperador::class, ['user' => $operador])
            ->set('name', 'Updated Name')
            ->set('email', 'updated@gymcontrol.com')
            ->set('rol', 'admin')
            ->set('password', '')
            ->call('actualizar')
            ->assertRedirect(route('operadores.index'));

        $operador->refresh();
        $this->assertEquals('Updated Name', $operador->name);
        $this->assertEquals('updated@gymcontrol.com', $operador->email);
        $this->assertEquals('admin', $operador->rol);
        // Verificamos que la contraseña sigue siendo la original
        $this->assertTrue(Hash::check('password123', $operador->password));

        // Caso 2: Editar cambiando contraseña
        Livewire::test(\App\Livewire\Operadores\EditarOperador::class, ['user' => $operador])
            ->set('password', 'newsecret123')
            ->call('actualizar')
            ->assertRedirect(route('operadores.index'));

        $operador->refresh();
        $this->assertTrue(Hash::check('newsecret123', $operador->password));
    }
}
