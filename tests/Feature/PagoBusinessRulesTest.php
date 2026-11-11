<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
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
    private User $duena;
    private array $inscripciones = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rol' => 'admin']);
        $this->duena = $this->admin;
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

        foreach ([$this->socioVigente, $this->socioExpirado, $this->socioNuevo] as $index => $socio) {
            $this->inscripciones[$socio->id] = $this->crearInscripcionAprobada($socio, $index + 1);
        }
    }

    /**
     * Un pago registra cobertura para la inscripción sin modificar el vencimiento global del socio.
     */
    public function test_pago_de_membresia_vigente_no_modifica_vencimiento_global(): void
    {
        $this->actingAs($this->recepcionista);

        $vencimientoOriginal = $this->socioVigente->fecha_vencimiento->copy();

        $this->registrarPagoDesdeFormulario($this->socioVigente, 7500, 'efectivo');

        $this->socioVigente->refresh();
        $this->assertEquals($vencimientoOriginal->format('Y-m-d'), $this->socioVigente->fecha_vencimiento->format('Y-m-d'));
        $this->assertDatabaseHas('pago_detalles', [
            'inscripcion_id' => $this->inscripciones[$this->socioVigente->id]->id,
            'subtotal' => 7500,
        ]);

        $this->assertDatabaseHas('pagos', [
            'socio_id' => $this->socioVigente->id,
            'importe' => 7500,
            'metodo_pago' => 'efectivo',
        ]);
    }

    /**
     * La cobertura económica queda asociada a la inscripción incluso si venció la membresía global antigua.
     */
    public function test_pago_no_extiende_membresia_global_expirada(): void
    {
        $this->actingAs($this->recepcionista);

        $vencimientoOriginal = $this->socioExpirado->fecha_vencimiento->format('Y-m-d');
        $this->registrarPagoDesdeFormulario($this->socioExpirado, 7500, 'transferencia');

        $this->socioExpirado->refresh();
        $this->assertEquals($vencimientoOriginal, $this->socioExpirado->fecha_vencimiento->format('Y-m-d'));
        $this->assertDatabaseHas('pago_detalles', [
            'inscripcion_id' => $this->inscripciones[$this->socioExpirado->id]->id,
            'estado_cobertura' => 'vigente',
        ]);
    }

    /**
     * El primer pago tampoco crea ni extiende un vencimiento global de membresía.
     */
    public function test_primer_pago_crea_cobertura_sin_vencimiento_global(): void
    {
        $this->actingAs($this->recepcionista);

        $this->registrarPagoDesdeFormulario($this->socioNuevo, 7000, 'debito');

        $this->socioNuevo->refresh();
        $this->assertNull($this->socioNuevo->fecha_vencimiento);
        $this->assertDatabaseHas('pago_detalles', [
            'inscripcion_id' => $this->inscripciones[$this->socioNuevo->id]->id,
            'subtotal' => 7000,
        ]);

        $this->assertDatabaseHas('pagos', [
            'socio_id' => $this->socioNuevo->id,
            'importe' => 7000,
            'metodo_pago' => 'debito',
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
            ->set('detalles.0.concepto', 'Cuota inválida')
            ->set('detalles.0.fecha_inicio', Carbon::today()->format('Y-m-d'))
            ->set('detalles.0.fecha_fin', Carbon::today()->addMonth()->format('Y-m-d'))
            ->set('detalles.0.subtotal', -100)
            ->set('metodo_pago', 'bitcoins')
            ->call('guardar')
            ->assertHasErrors([
                'socio_id' => 'required',
                'detalles.0.subtotal' => 'min',
                'metodo_pago' => 'in',
            ]);
    }

    private function crearInscripcionAprobada(Socio $socio, int $numero): Inscripcion
    {
        $disciplina = Disciplina::create([
            'nombre' => "Disciplina prueba {$numero}",
            'estado' => 'activa',
            'precio' => 7500,
        ]);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => "Grupo prueba {$numero}",
            'cupo_maximo' => 10,
            'estado' => 'activo',
        ]);

        return Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => Carbon::today()->toDateString(),
            'estado' => Inscripcion::ESTADO_APROBADA,
            'aprobado_por' => $this->duena->id,
            'aprobado_at' => now(),
        ]);
    }

    private function registrarPagoDesdeFormulario(Socio $socio, float $importe, string $medioPago): void
    {
        Livewire::test(\App\Livewire\Pagos\CrearPago::class, ['socio' => $socio])
            ->set('fecha_pago', Carbon::today()->format('Y-m-d'))
            ->set('metodo_pago', $medioPago)
            ->set('detalles.0.inscripcion_id', $this->inscripciones[$socio->id]->id)
            ->set('detalles.0.concepto', 'Cuota mensual de prueba')
            ->set('detalles.0.fecha_inicio', Carbon::today()->startOfMonth()->format('Y-m-d'))
            ->set('detalles.0.fecha_fin', Carbon::today()->endOfMonth()->format('Y-m-d'))
            ->set('detalles.0.subtotal', $importe)
            ->call('guardar')
            ->assertHasNoErrors();
    }
}
