<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Asistencia;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NucleoFuncionalDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $duena;
    private User $recepcionista;
    private Membresia $membresia;
    private Disciplina $disciplina;
    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duena = User::factory()->create(['rol' => 'admin']);
        $this->recepcionista = User::factory()->create(['rol' => 'recepcionista']);
        $this->membresia = Membresia::create([
            'nombre' => 'Plan de prueba',
            'precio' => 1000,
            'estado' => 'activo',
        ]);
        $this->disciplina = Disciplina::create(['nombre' => 'Funcional', 'estado' => 'activa']);
        $this->grupo = Grupo::create([
            'disciplina_id' => $this->disciplina->id,
            'nombre' => 'Funcional tarde',
            'profesor' => 'Entrenadora',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
    }

    public function test_owner_dashboard_uses_real_data_and_counts_payment_once(): void
    {
        $vigente = $this->crearSocio('40111111', 'Vigente');
        $vencido = $this->crearSocio('40222222', 'Vencido');
        $pendiente = $this->crearSocio('40333333', 'Pendiente');
        $this->crearInscripcion($vigente, Inscripcion::ESTADO_APROBADA);
        $this->crearInscripcion($vencido, Inscripcion::ESTADO_APROBADA);
        $this->crearInscripcion($pendiente, Inscripcion::ESTADO_PENDIENTE);

        $pago = Pago::create([
            'socio_id' => $vigente->id,
            'operador_id' => $this->duena->id,
            'fecha_pago' => today()->toDateString(),
            'importe' => 1500,
            'metodo_pago' => 'efectivo',
            'estado' => Pago::ESTADO_ACTIVO,
        ]);
        $vigenteInscripcion = $vigente->inscripciones()->firstOrFail();
        $pago->detalles()->create([
            'inscripcion_id' => $vigenteInscripcion->id,
            'concepto' => 'Cuota Funcional',
            'fecha_inicio_cobertura' => today()->toDateString(),
            'fecha_fin_cobertura' => today()->addMonth()->toDateString(),
            'subtotal' => 700,
            'estado_cobertura' => PagoDetalle::COBERTURA_VIGENTE,
        ]);
        $pago->detalles()->create([
            'inscripcion_id' => $vigenteInscripcion->id,
            'concepto' => 'Otro concepto del mismo comprobante',
            'fecha_inicio_cobertura' => today()->toDateString(),
            'fecha_fin_cobertura' => today()->addMonth()->toDateString(),
            'subtotal' => 800,
            'estado_cobertura' => PagoDetalle::COBERTURA_VIGENTE,
        ]);

        $pagoVencido = Pago::create([
            'socio_id' => $vencido->id,
            'operador_id' => $this->duena->id,
            'fecha_pago' => today()->subMonth()->toDateString(),
            'importe' => 2000,
            'metodo_pago' => 'debito',
            'estado' => Pago::ESTADO_ACTIVO,
        ]);
        $pagoVencido->detalles()->create([
            'inscripcion_id' => $vencido->inscripciones()->firstOrFail()->id,
            'concepto' => 'Cuota vencida',
            'fecha_inicio_cobertura' => today()->subMonths(2)->toDateString(),
            'fecha_fin_cobertura' => today()->subDay()->toDateString(),
            'subtotal' => 2000,
            'estado_cobertura' => PagoDetalle::COBERTURA_VIGENTE,
        ]);

        Asistencia::create([
            'socio_id' => $vigente->id,
            'fecha' => today()->toDateString(),
            'hora_ingreso' => '09:00:00',
            'metodo_acceso' => 'pin_recepcion',
        ]);

        Livewire::actingAs($this->duena)
            ->test(Dashboard::class)
            ->assertSet('totalSocios', 3)
            ->assertSet('sociosActivos', 3)
            ->assertSet('inscripcionesPendientes', 1)
            ->assertSet('inscripcionesAprobadas', 2)
            ->assertSet('cuotasVigentes', 1)
            ->assertSet('cuotasVencidas', 1)
            ->assertSet('sociosEnSala', 1)
            ->assertSet('ingresosHoy', 1)
            ->assertSet('recaudacionHoy', 1500.0)
            ->assertSet('ingresosMes', 1500.0)
            ->assertSee('Cupos por grupo')
            ->assertSee('Recaudación del mes');
    }

    public function test_reception_dashboard_is_operational_and_does_not_expose_owner_financials_or_credentials(): void
    {
        $socio = $this->crearSocio('40444444', 'Privado');
        $socio->update([
            'token' => 'INTERNAL-TOKEN-DO-NOT-RENDER',
            'pin' => '654321',
            'qr_token' => str_repeat('b', 64),
        ]);

        Livewire::actingAs($this->recepcionista)
            ->test(Dashboard::class)
            ->assertSet('esDuena', false)
            ->assertSet('ingresosMes', 0.0)
            ->assertSee('Buscar socio')
            ->assertSee('Registrar ingreso o salida')
            ->assertDontSee('Recaudación del mes')
            ->assertDontSee('INTERNAL-TOKEN-DO-NOT-RENDER')
            ->assertDontSee('654321')
            ->assertDontSee(str_repeat('b', 64));
    }

    public function test_reception_cannot_change_dashboard_role_to_reveal_owner_metrics(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->recepcionista)
            ->test(Dashboard::class)
            ->set('esDuena', true);
    }

    public function test_users_without_owner_or_reception_role_cannot_open_dashboard_or_operational_api(): void
    {
        $user = User::factory()->create(['rol' => 'coach']);

        Livewire::actingAs($user)->test(Dashboard::class)->assertForbidden();

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.socios.index'))
            ->assertForbidden();
    }

    private function crearSocio(string $dni, string $apellido): Socio
    {
        static $token = 100;

        return Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Socio',
            'apellido' => $apellido,
            'dni' => $dni,
            'sexo' => 'M',
            'token' => (string) $token++,
            'fecha_alta' => today()->toDateString(),
            'estado' => 'activo',
            'estado_credencial' => 'sin_emitir',
            'decision_medica' => 'pendiente',
        ]);
    }

    private function crearInscripcion(Socio $socio, string $estado): Inscripcion
    {
        return Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupo->id,
            'fecha_inscripcion' => today()->toDateString(),
            'estado' => $estado,
            'aprobado_por' => $estado === Inscripcion::ESTADO_APROBADA ? $this->duena->id : null,
            'aprobado_at' => $estado === Inscripcion::ESTADO_APROBADA ? now() : null,
        ]);
    }
}
