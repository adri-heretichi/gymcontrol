<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Socio $socio;
    private Pago $pago;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'rol' => 'admin',
        ]);

        $this->recepcionista = User::factory()->create([
            'rol' => 'recepcionista',
        ]);

        $membresia = Membresia::create([
            'nombre' => 'Pase Libre',
            'precio' => 6000,
            'estado' => 'activo',
        ]);

        $this->socio = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'María',
            'apellido' => 'Gómez',
            'dni' => '87654321',
            'sexo' => 'F',
            'correo' => 'maria@example.com',
            'celular' => '1122334455',
            'token' => '87654321',
            'fecha_alta' => today(),
            'fecha_vencimiento' => today()->addMonth(),
            'estado' => 'activo',
        ]);

        $this->pago = Pago::create([
            'socio_id' => $this->socio->id,
            'fecha_pago' => today(),
            'importe' => 6000,
            'metodo_pago' => 'efectivo',
        ]);
    }

    /**
     * Test de que el Recepcionista puede ver y registrar pagos.
     */
    public function test_recepcionista_permisos_pago(): void
    {
        $this->actingAs($this->recepcionista);

        // Puede ver listado general
        $this->get('/pagos')->assertStatus(200);

        // Puede acceder al formulario de carga para un socio
        $this->get('/socios/' . $this->socio->id . '/pagos/crear')->assertStatus(200);

        // Puede acceder al formulario de carga general
        $this->get('/pagos/crear')->assertStatus(200);
    }

    /**
     * Test de que el Administrador puede ver y registrar pagos.
     */
    public function test_administrador_permisos_pago(): void
    {
        $this->actingAs($this->admin);

        // Puede ver y crear
        $this->get('/pagos')->assertStatus(200);
        $this->get('/socios/' . $this->socio->id . '/pagos/crear')->assertStatus(200);
        $this->get('/pagos/crear')->assertStatus(200);
    }
}
