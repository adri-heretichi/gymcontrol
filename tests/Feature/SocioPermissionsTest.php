<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->get('/socios/' . $this->socio->id)->assertStatus(200);
        $this->get('/socios/crear')->assertStatus(200);
        $this->get('/socios/' . $this->socio->id . '/editar')->assertStatus(200);
    }

    /**
     * Test de restricciones del rol Recepcionista.
     */
    public function test_recepcionista_tiene_acceso_limitado_opcion_b(): void
    {
        $this->actingAs($this->recepcionista);

        // Accesos permitidos
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/socios')->assertStatus(200);
        $this->get('/socios/' . $this->socio->id)->assertStatus(200);

        // Accesos denegados (Bajo la Opción B, deben retornar 403)
        $this->get('/socios/crear')->assertStatus(403);
        $this->get('/socios/' . $this->socio->id . '/editar')->assertStatus(403);
    }
}
