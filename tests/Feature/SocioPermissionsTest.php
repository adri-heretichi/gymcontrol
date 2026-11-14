<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocioPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Membresia $membresia;
    private Socio $socio;

    protected function setUp(): void
    {
        parent::setUp();

        // Creamos usuarios con roles diferenciados
        $this->admin = User::factory()->create([
            'rol' => 'admin',
        ]);

        $this->recepcionista = User::factory()->create([
            'rol' => 'recepcionista',
        ]);

        // Creamos membresía y socio requeridos para las pruebas
        $this->membresia = Membresia::create([
            'nombre' => 'Pase Libre',
            'precio' => 5000,
            'estado' => 'activo',
        ]);

        $this->socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'dni' => '12345678',
            'sexo' => 'M',
            'correo' => 'juan@example.com',
            'celular' => '1122334455',
            'token' => '12345678',
            'fecha_alta' => today(),
            'fecha_vencimiento' => today()->addMonth(),
            'estado' => 'activo',
        ]);
    }

    /**
     * Test de acceso total para el rol Administrador.
     */
    public function test_administrador_tiene_acceso_total(): void
    {
        $this->actingAs($this->admin);

        // Verificamos accesos permitidos
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/socios')->assertStatus(200);
        $this->get('/dashboard')->assertSee('Auditoría');
        $this->get('/socios/' . $this->socio->id)->assertStatus(200);
        $this->get('/socios/crear')->assertStatus(200)->assertSee('Membresías');
        $this->get('/socios/' . $this->socio->id . '/editar')->assertStatus(200);
    }

    /**
     * Test de permisos del rol Recepcionista (Fase 2: puede registrar socios pero no editarlos).
     */
    public function test_recepcionista_tiene_acceso_limitado_opcion_b(): void
    {
        $this->actingAs($this->recepcionista);

        // Accesos permitidos (consulta y alta de socios)
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/socios')->assertStatus(200);
        $this->get('/socios/' . $this->socio->id)->assertStatus(200);
        $this->get('/socios/crear')->assertStatus(200);

        // Accesos denegados (edición reservada a la dueña, debe retornar 403)
        $this->get('/socios/' . $this->socio->id . '/editar')->assertStatus(403);
    }

    public function test_se_puede_dar_de_alta_un_socio_sin_membresia_general(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\Socios\CrearSocio::class)
            ->set('nombre', 'Lucía')
            ->set('apellido', 'Prueba')
            ->set('dni', '40123456')
            ->set('sexo', 'F')
            ->set('fecha_alta', today()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('socios.show', Socio::where('dni', '40123456')->value('id') ?? 1));

        $this->assertDatabaseHas('socios', [
            'dni' => '40123456',
            'membresia_id' => null,
            'fecha_vencimiento' => null,
        ]);
    }

    public function test_editar_socio_no_modifica_datos_legacy_de_membresia_y_vencimiento(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(\App\Livewire\Socios\EditarSocio::class, ['socio' => $this->socio])
            ->set('nombre', 'Juan Actualizado')
            ->call('actualizar')
            ->assertHasNoErrors()
            ->assertRedirect(route('socios.show', $this->socio->id));

        $this->assertDatabaseHas('socios', [
            'id' => $this->socio->id,
            'nombre' => 'Juan Actualizado',
            'membresia_id' => $this->membresia->id,
        ]);
        $this->assertSame(
            today()->addMonth()->toDateString(),
            Socio::findOrFail($this->socio->id)->fecha_vencimiento->toDateString()
        );
    }
}
