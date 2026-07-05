<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class PagoBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Socio $socioVigente;
    private Socio $socioExpirado;
    private Socio $socioNuevo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rol' => 'admin']);
        $this->recepcionista = User::factory()->create(['rol' => 'recepcionista']);

        $membresia = Membresia::create([
            'nombre' => 'Pase Libre',
            'precio' => 7500,
            'estado' => 'activo',
        ]);

        // Caso A: Socio con membresía vigente (vence en 15 días)
        $this->socioVigente = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'Luis',
            'apellido' => 'Pérez',
            'dni' => '12345678',
            'sexo' => 'M',
            'correo' => 'luis@example.com',
            'celular' => '1122334455',
            'token' => '12345678',
            'fecha_alta' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addDays(15),
            'estado' => 'activo',
        ]);

        // Caso B: Socio con membresía vencida (venció hace 5 días)
        $this->socioExpirado = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'Ana',
            'apellido' => 'Ríos',
            'dni' => '87654321',
            'sexo' => 'F',
            'correo' => 'ana@example.com',
            'celular' => '1122334456',
            'token' => '87654321',
            'fecha_alta' => Carbon::today()->subMonths(2),
            'fecha_vencimiento' => Carbon::today()->subDays(5),
            'estado' => 'activo',
        ]);

        // Caso B2: Socio nuevo (primer pago, sin vencimiento previo)
        $this->socioNuevo = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'Carlos',
            'apellido' => 'Sosa',
            'dni' => '44556677',
            'sexo' => 'M',
            'correo' => 'carlos@example.com',
            'celular' => '1122334457',
            'token' => '44556677',
            'fecha_alta' => Carbon::today(),
            'fecha_vencimiento' => null,
            'estado' => 'activo',
        ]);
    }

    /**
     * Verifica la extensión para membresías vigentes (Caso A: Vencimiento actual + 1 mes).
     */
    public function test_extension_membresia_vigente(): void
    {
        $this->actingAs($this->recepcionista);

        $vencimientoOriginal = $this->socioVigente->fecha_vencimiento->copy();

        Livewire::test(\App\Livewire\Pagos\CrearPago::class, ['socio' => $this->socioVigente])
            ->set('fecha_pago', Carbon::today()->format('Y-m-d'))
            ->set('importe', 7500)
            ->set('metodo_pago', 'efectivo')
            ->call('guardar')
            ->assertHasNoErrors();

        // El socio debe haber extendido su vencimiento sumando 1 mes a su fecha_vencimiento previa
        $this->socioVigente->refresh();
        $this->assertEquals(
            $vencimientoOriginal->addMonth()->format('Y-m-d'),
            $this->socioVigente->fecha_vencimiento->format('Y-m-d')
        );

        $this->assertDatabaseHas('pagos', [
            'socio_id' => $this->socioVigente->id,
            'importe' => 7500,
            'metodo_pago' => 'efectivo',
        ]);
    }

    /**
     * Verifica la extensión para membresías expiradas (Caso B: Fecha de Pago + 1 mes).
     */
    public function test_extension_membresia_expirada(): void
    {
        $this->actingAs($this->recepcionista);

        $fechaPago = Carbon::today()->format('Y-m-d');

        Livewire::test(\App\Livewire\Pagos\CrearPago::class, ['socio' => $this->socioExpirado])
            ->set('fecha_pago', $fechaPago)
            ->set('importe', 7500)
            ->set('metodo_pago', 'transferencia')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->socioExpirado->refresh();
        $this->assertEquals(
            Carbon::parse($fechaPago)->addMonth()->format('Y-m-d'),
            $this->socioExpirado->fecha_vencimiento->format('Y-m-d')
        );
    }

    /**
     * Verifica la extensión para primer pago / sin vencimiento (Caso B: Fecha de Pago + 1 mes).
     */
    public function test_extension_primer_pago(): void
    {
        $this->actingAs($this->recepcionista);

        $fechaPago = Carbon::today()->format('Y-m-d');

        Livewire::test(\App\Livewire\Pagos\CrearPago::class, ['socio' => $this->socioNuevo])
            ->set('fecha_pago', $fechaPago)
            ->set('importe', 7000) // Se le hace descuento
            ->set('metodo_pago', 'tarjeta')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->socioNuevo->refresh();
        $this->assertEquals(
            Carbon::parse($fechaPago)->addMonth()->format('Y-m-d'),
            $this->socioNuevo->fecha_vencimiento->format('Y-m-d')
        );

        $this->assertDatabaseHas('pagos', [
            'socio_id' => $this->socioNuevo->id,
            'importe' => 7000,
            'metodo_pago' => 'tarjeta',
        ]);
    }

    /**
     * Valida que no se puedan ingresar importes negativos o métodos inválidos.
     */
    public function test_validacion_formulario_crear_pago(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\Pagos\CrearPago::class)
            ->set('socio_id', '')
            ->set('importe', -100)
            ->set('metodo_pago', 'bitcoins')
            ->call('guardar')
            ->assertHasErrors([
                'socio_id' => 'required',
                'importe' => 'min',
                'metodo_pago' => 'in',
            ]);
    }
}
