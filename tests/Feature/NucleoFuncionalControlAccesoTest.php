<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use App\Services\ControlAccesoService;
use App\Services\CredencialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class NucleoFuncionalControlAccesoTest extends TestCase
{
    use RefreshDatabase;

    private User $duena;
    private User $recepcionista;
    private Membresia $membresia;
    private Socio $socio;
    private Disciplina $disciplina;
    private Grupo $grupo;
    private Inscripcion $inscripcion;
    private PagoDetalle $cobertura;
    private ControlAccesoService $control;
    private Carbon $momento;
    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        $this->momento = Carbon::parse('2026-11-09 18:30:00', config('app.timezone'));
        Carbon::setTestNow($this->momento);

        $this->duena = User::create([
            'name' => 'Dueña acceso',
            'email' => 'duena-acceso@test.com',
            'password' => 'password',
            'rol' => 'admin',
        ]);
        $this->recepcionista = User::create([
            'name' => 'Recepción acceso',
            'email' => 'recepcion-acceso@test.com',
            'password' => 'password',
            'rol' => 'recepcionista',
        ]);
        $this->membresia = Membresia::create([
            'nombre' => 'Membresía acceso',
            'precio' => 30000,
            'duracion_dias' => 30,
            'estado' => 'activo',
        ]);

        $this->disciplina = $this->crearDisciplina('Funcional');
        $this->grupo = $this->crearGrupo($this->disciplina, 'Funcional tarde');
        $this->agregarHorario($this->grupo, 'lunes', '18:00:00', '19:00:00');
        $this->socio = $this->crearSocio([
            'pin' => '123456',
            'qr_token' => str_repeat('a', 64),
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'decision_medica' => 'no_requerido',
        ]);
        $this->inscripcion = $this->crearInscripcion($this->socio, $this->grupo);
        $this->cobertura = $this->crearCobertura($this->inscripcion);
        $this->control = app(ControlAccesoService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pin_and_new_qr_share_read_only_access_evaluation(): void
    {
        foreach ([
            [ControlAccesoService::METODO_PIN, $this->socio->pin],
            [ControlAccesoService::METODO_QR, $this->socio->qr_token],
        ] as [$metodo, $identificador]) {
            $resultado = $this->control->evaluarAcceso($identificador, $metodo, null, $this->momento);

            $this->assertTrue($resultado['autorizado']);
            $this->assertSame($this->socio->id, $resultado['socio']->id);
            $this->assertSame($this->grupo->id, $resultado['actividad']['grupo_id']);
            $this->assertSame('lunes', $resultado['dia']);
            $this->assertSame(['dia' => 'lunes', 'hora_inicio' => '18:00', 'hora_fin' => '19:00'], $resultado['horario']);
        }

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_missing_revoked_and_legacy_credentials_are_rejected(): void
    {
        $missing = $this->control->evaluarAcceso('999999', ControlAccesoService::METODO_PIN, null, $this->momento);
        $this->assertSame('credencial_invalida', $missing['codigo']);

        $legacy = $this->control->evaluarAcceso($this->socio->token, ControlAccesoService::METODO_QR, null, $this->momento);
        $this->assertSame('credencial_legada', $legacy['codigo']);
        $this->assertFalse($legacy['autorizado']);

        $qrAnterior = $this->socio->qr_token;
        app(CredencialService::class)->revocarCredenciales($this->socio, $this->duena, 'Prueba de revocación');
        $revocada = $this->control->evaluarAcceso($qrAnterior, ControlAccesoService::METODO_QR, null, $this->momento);
        $this->assertSame('credencial_revocada', $revocada['codigo']);
    }

    public function test_inactive_member_and_pending_enrollment_are_denied(): void
    {
        $this->socio->update(['estado' => 'inactivo']);
        $inactivo = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('socio_inactivo', $inactivo['codigo']);

        $socioPendiente = $this->crearSocio([
            'pin' => '234567',
            'qr_token' => str_repeat('b', 64),
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'decision_medica' => 'no_requerido',
        ]);
        $pendiente = $this->crearInscripcion($socioPendiente, $this->grupo, Inscripcion::ESTADO_PENDIENTE);
        $this->crearCobertura($pendiente);

        $resultado = $this->control->evaluarAcceso($socioPendiente->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('inscripcion_pendiente', $resultado['codigo']);

        $socioSinInscripcion = $this->crearSocio([
            'pin' => '345678',
            'qr_token' => str_repeat('c', 64),
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'decision_medica' => 'no_requerido',
        ]);
        $sinInscripcion = $this->control->evaluarAcceso($socioSinInscripcion->pin, 'pin', null, $this->momento);
        $this->assertSame('inscripcion_inexistente', $sinInscripcion['codigo']);
    }

    public function test_approved_enrollment_and_current_discipline_coverage_authorize_access(): void
    {
        $resultado = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);

        $this->assertTrue($resultado['autorizado']);
        $this->assertSame($this->disciplina->id, $resultado['actividad']['disciplina_id']);
        $this->assertSame(PagoDetalle::COBERTURA_VIGENTE, $this->cobertura->estado_cobertura);
    }

    public function test_general_membership_is_not_required_when_enrollment_coverage_and_schedule_are_valid(): void
    {
        $this->socio->update([
            'membresia_id' => null,
            'fecha_vencimiento' => null,
        ]);

        $resultado = $this->control->evaluarAcceso(
            $this->socio->pin,
            ControlAccesoService::METODO_PIN,
            $this->grupo->id,
            $this->momento
        );

        $this->assertTrue($resultado['autorizado']);
    }

    public function test_expired_or_other_discipline_coverage_does_not_authorize_access(): void
    {
        $this->cobertura->update([
            'fecha_inicio_cobertura' => '2026-10-01',
            'fecha_fin_cobertura' => '2026-11-08',
        ]);
        $vencida = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('cuota_vencida', $vencida['codigo']);

        $otraDisciplina = $this->crearDisciplina('Kickboxing');
        $otroGrupo = $this->crearGrupo($otraDisciplina, 'Kickboxing tarde');
        $this->agregarHorario($otroGrupo, 'lunes', '18:00:00', '19:00:00');
        $otraInscripcion = $this->crearInscripcion($this->socio, $otroGrupo);
        $this->crearCobertura($otraInscripcion);
        $sinCoberturaFuncional = $this->control->evaluarAcceso(
            $this->socio->pin,
            'pin',
            $this->grupo->id,
            $this->momento
        );
        $this->assertSame('cuota_vencida', $sinCoberturaFuncional['codigo']);

        $socioSinCobertura = $this->crearSocio([
            'pin' => '456789',
            'qr_token' => str_repeat('d', 64),
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'decision_medica' => 'no_requerido',
        ]);
        $this->crearInscripcion($socioSinCobertura, $this->grupo);
        $sinPago = $this->control->evaluarAcceso($socioSinCobertura->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('sin_cobertura', $sinPago['codigo']);
    }

    public function test_pending_medical_decision_and_required_missing_or_expired_certificate_are_denied(): void
    {
        $this->socio->update(['decision_medica' => 'pendiente']);
        $pendiente = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('decision_medica_pendiente', $pendiente['codigo']);

        $this->socio->update(['decision_medica' => 'requerido']);
        $faltante = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('certificado_faltante', $faltante['codigo']);

        $this->crearCertificado('2026-10-01');
        $vencido = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('certificado_vencido', $vencido['codigo']);
    }

    public function test_certificate_is_valid_on_its_expiration_date_and_no_requirement_is_silent(): void
    {
        $this->socio->update(['decision_medica' => 'requerido']);
        $this->crearCertificado('2026-11-09');
        $vigente = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertTrue($vigente['autorizado']);
        $this->assertStringNotContainsString('médico', mb_strtolower($vigente['mensaje']));

        $this->socio->update(['decision_medica' => 'no_requerido']);
        $noRequerido = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertTrue($noRequerido['autorizado']);
        $this->assertSame('Acceso autorizado.', $noRequerido['mensaje']);
    }

    public function test_wrong_day_time_and_disabled_group_are_denied(): void
    {
        $martes = $this->momento->copy()->addDay();
        $diaIncorrecto = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $martes);
        $this->assertSame('fuera_de_horario', $diaIncorrecto['codigo']);

        $fueraDeHora = $this->control->evaluarAcceso(
            $this->socio->pin,
            'pin',
            $this->grupo->id,
            $this->momento->copy()->setTime(19, 0)
        );
        $this->assertSame('fuera_de_horario', $fueraDeHora['codigo']);

        $this->grupo->update(['estado' => 'inactivo']);
        $inactivo = $this->control->evaluarAcceso($this->socio->pin, 'pin', $this->grupo->id, $this->momento);
        $this->assertSame('grupo_inactivo', $inactivo['codigo']);
    }

    public function test_ambiguous_activity_requires_explicit_group_selection(): void
    {
        $segundaDisciplina = $this->crearDisciplina('Yoga');
        $segundoGrupo = $this->crearGrupo($segundaDisciplina, 'Yoga tarde');
        $this->agregarHorario($segundoGrupo, 'lunes', '18:00:00', '19:00:00');
        $segundaInscripcion = $this->crearInscripcion($this->socio, $segundoGrupo);
        $this->crearCobertura($segundaInscripcion);

        $resultado = $this->control->evaluarAcceso($this->socio->pin, 'pin', null, $this->momento);
        $this->assertSame('actividad_ambigua', $resultado['codigo']);
        $this->assertCount(2, $resultado['actividad']['opciones']);
    }

    public function test_duplicate_open_entry_is_denied_and_repeated_requests_create_one_entry(): void
    {
        $primera = $this->control->registrarIngreso($this->socio->pin, 'pin', $this->grupo->id, $this->recepcionista->id, $this->momento);
        $segunda = $this->control->registrarIngreso($this->socio->pin, 'pin', $this->grupo->id, $this->recepcionista->id, $this->momento);

        $this->assertTrue($primera['autorizado']);
        $this->assertSame('ingreso_duplicado', $segunda['codigo']);
        $this->assertSame(1, Asistencia::where('socio_id', $this->socio->id)->whereNull('hora_salida')->count());
    }

    public function test_access_evaluation_does_not_replace_credentials(): void
    {
        $pin = $this->socio->pin;
        $qr = $this->socio->qr_token;

        $this->control->evaluarAcceso($pin, 'pin', $this->grupo->id, $this->momento);
        $this->socio->update(['decision_medica' => 'requerido']);
        $this->crearCertificado('2026-12-01');
        $this->control->evaluarAcceso($pin, 'pin', $this->grupo->id, $this->momento);

        $this->assertSame($pin, $this->socio->fresh()->pin);
        $this->assertSame($qr, $this->socio->fresh()->qr_token);
    }

    public function test_exit_is_allowed_after_coverage_expires(): void
    {
        $entrada = $this->control->registrarIngreso($this->socio->pin, 'pin', $this->grupo->id, null, $this->momento);
        $this->assertTrue($entrada['autorizado']);

        $this->cobertura->update([
            'fecha_inicio_cobertura' => '2026-10-01',
            'fecha_fin_cobertura' => '2026-11-08',
        ]);
        $salida = $this->control->registrarSalidaPorCredencial(
            $this->socio->qr_token,
            'qr',
            null,
            $this->momento->copy()->addHour()
        );

        $this->assertTrue($salida['autorizado']);
        $this->assertNotNull($entrada['asistencia']->fresh()->hora_salida);
    }

    public function test_qr_get_is_read_only_and_legacy_qr_get_cannot_enter(): void
    {
        $this->get(route('terminal.scan', $this->socio->qr_token))->assertOk();
        $this->get(route('terminal.scan', $this->socio->token))->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_new_qr_post_records_entry_through_the_shared_engine(): void
    {
        $this->get(route('terminal.scan', $this->socio->qr_token))
            ->assertOk()
            ->assertViewIs('terminal.scan-mobile')
            ->assertSee('Confirmar ingreso')
            ->assertSee('name="_token"', false);

        $response = $this->post(route('terminal.scan.ingreso'), [
            'identificador' => $this->socio->qr_token,
            'grupo_id' => $this->grupo->id,
        ]);
        $response->assertOk()
            ->assertViewIs('terminal.scan-mobile')
            ->assertSee('¡Bienvenido, Socio 1!')
            ->assertSee('Funcional');

        $replay = $this->post(route('terminal.scan.ingreso'), [
            'identificador' => $this->socio->qr_token,
            'grupo_id' => $this->grupo->id,
        ]);
        $replay->assertForbidden()
            ->assertViewIs('terminal.scan-mobile')
            ->assertSee('Ya existe un ingreso abierto');

        $this->assertDatabaseHas('asistencias', [
            'socio_id' => $this->socio->id,
            'metodo_acceso' => 'qr_movil',
            'hora_salida' => null,
        ]);
        $this->assertSame(1, Asistencia::where('socio_id', $this->socio->id)->count());
    }

    public function test_terminal_accepts_only_six_digit_pin_and_shows_minimal_access_details(): void
    {
        Http::fake();

        $component = Livewire::test(\App\Livewire\TerminalAcceso::class)
            ->set('pin', '12345')
            ->call('procesar')
            ->assertHasErrors(['pin'])
            ->assertSet('pin', '');

        $component->set('pin', $this->socio->pin)
            ->call('procesar')
            ->assertSet('estadoAcceso', 'exito')
            ->assertSet('pin', '')
            ->assertSee('Funcional')
            ->assertSee('Lunes')
            ->assertDontSee('DNI')
            ->assertDontSee('Membresía')
            ->assertDontSee('Escanear QR');

        $this->assertSame(1, Asistencia::where('socio_id', $this->socio->id)->count());
    }

    public function test_reception_previews_then_confirms_pin_entry_and_can_select_a_group(): void
    {
        $this->actingAs($this->recepcionista);

        $component = Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', $this->socio->pin)
            ->call('consultar')
            ->assertSet('estadoAcceso', 'preparado')
            ->assertSet('puedeConfirmarIngreso', true);

        $this->assertDatabaseCount('asistencias', 0);

        $component->set('identificador', $this->socio->pin)
            ->call('confirmarIngreso')
            ->assertSet('estadoAcceso', 'exito')
            ->assertSet('identificador', '');
        $this->assertSame(1, Asistencia::where('socio_id', $this->socio->id)->count());

        $this->assertDatabaseHas('auditorias', [
            'user_id' => $this->recepcionista->id,
            'tipo_evento' => 'acceso',
            'accion' => 'acceso_autorizado',
        ]);
    }

    public function test_reception_must_select_an_activity_before_confirming_ambiguous_pin(): void
    {
        $otraDisciplina = $this->crearDisciplina('Yoga');
        $otroGrupo = $this->crearGrupo($otraDisciplina, 'Yoga tarde');
        $this->agregarHorario($otroGrupo, 'lunes', '18:00:00', '19:00:00');
        $inscripcion = $this->crearInscripcion($this->socio, $otroGrupo);
        $this->crearCobertura($inscripcion);
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', $this->socio->pin)
            ->call('consultar')
            ->assertSet('estadoAcceso', 'error')
            ->assertSet('requiereSeleccionGrupo', true)
            ->assertCount('opcionesActividad', 2);

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_only_owner_and_reception_can_access_reception_control(): void
    {
        $otro = User::create([
            'name' => 'Usuario sin permiso',
            'email' => 'sin-permiso@test.com',
            'password' => 'password',
            'rol' => 'instructor',
        ]);

        $this->actingAs($otro)
            ->get(route('control-acceso'))
            ->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_access_action_requires_operator_authorization_and_audits_without_credentials(): void
    {
        $payload = [
            'identificador' => $this->socio->pin,
            'metodo' => 'pin',
            'accion' => 'ingreso',
            'grupo_id' => $this->grupo->id,
        ];
        $this->postJson(route('api.control-acceso.scan'), $payload)->assertUnauthorized();

        $response = $this->actingAs($this->recepcionista)
            ->postJson(route('api.control-acceso.scan'), $payload)
            ->assertOk()
            ->assertJsonPath('data.autorizado', true);

        $auditoria = Auditoria::query()->where('accion', 'acceso_autorizado')->latest('id')->firstOrFail();
        $datos = json_encode($auditoria->datos_despues);
        $this->assertSame($this->recepcionista->id, $auditoria->user_id);
        $this->assertStringNotContainsString($this->socio->pin, $datos);
        $this->assertStringNotContainsString($this->socio->qr_token, $datos);
        $this->assertSame($this->socio->id, $response->json('data.socio.id'));

        $this->actingAs($this->recepcionista)
            ->postJson(route('api.control-acceso.scan'), array_merge($payload, ['identificador' => '999999']))
            ->assertForbidden()
            ->assertJsonPath('data.codigo', 'credencial_invalida');
        $denegada = Auditoria::query()->where('accion', 'acceso_denegado')->latest('id')->firstOrFail();
        $this->assertSame('credencial_invalida', $denegada->datos_despues['resultado']);
        $this->assertStringNotContainsString('999999', json_encode($denegada->datos_despues));
    }

    public function test_api_requires_explicit_method_and_action_instead_of_legacy_dni_toggle(): void
    {
        $token = $this->recepcionista->createToken('test')->plainTextToken;
        $this->withToken($token)
            ->postJson(route('api.asistencias.store'), [
                'socio_id' => $this->socio->id,
                'fecha' => $this->momento->toDateString(),
                'hora_ingreso' => $this->momento->format('H:i:s'),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('asistencias', 0);
    }

    private function crearSocio(array $atributos = []): Socio
    {
        $numero = ++$this->secuencia;

        return Socio::create(array_merge([
            'membresia_id' => $this->membresia->id,
            'nombre' => "Socio {$numero}",
            'apellido' => 'Acceso',
            'dni' => str_pad((string) $numero, 8, '0', STR_PAD_LEFT),
            'sexo' => 'M',
            'token' => 'legacy-' . $numero,
            'fecha_alta' => $this->momento->toDateString(),
            'estado' => 'activo',
            'decision_medica' => 'no_requerido',
            'estado_credencial' => CredencialService::ESTADO_SIN_EMITIR,
        ], $atributos));
    }

    private function crearDisciplina(string $nombre): Disciplina
    {
        return Disciplina::create(['nombre' => $nombre, 'estado' => 'activa']);
    }

    private function crearGrupo(Disciplina $disciplina, string $nombre): Grupo
    {
        return Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => $nombre,
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
    }

    private function agregarHorario(Grupo $grupo, string $dia, string $inicio, string $fin): void
    {
        GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);
    }

    private function crearInscripcion(
        Socio $socio,
        Grupo $grupo,
        string $estado = Inscripcion::ESTADO_APROBADA
    ): Inscripcion {
        return Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => $this->momento->toDateString(),
            'estado' => $estado,
            'aprobado_por' => $estado === Inscripcion::ESTADO_APROBADA ? $this->duena->id : null,
            'aprobado_at' => $estado === Inscripcion::ESTADO_APROBADA ? $this->momento : null,
        ]);
    }

    private function crearCobertura(Inscripcion $inscripcion): PagoDetalle
    {
        $pago = Pago::create([
            'socio_id' => $inscripcion->socio_id,
            'operador_id' => $this->duena->id,
            'fecha_pago' => $this->momento->toDateString(),
            'importe' => 30000,
            'metodo_pago' => 'efectivo',
            'estado' => Pago::ESTADO_ACTIVO,
        ]);

        return PagoDetalle::create([
            'pago_id' => $pago->id,
            'inscripcion_id' => $inscripcion->id,
            'concepto' => 'Cuota mensual',
            'fecha_inicio_cobertura' => '2026-11-01',
            'fecha_fin_cobertura' => '2026-11-30',
            'subtotal' => 30000,
            'estado_cobertura' => PagoDetalle::COBERTURA_VIGENTE,
        ]);
    }

    private function crearCertificado(string $vencimiento): void
    {
        $this->socio->aptosFisicos()->create([
            'archivo' => null,
            'fecha_emision' => '2026-10-01',
            'fecha_vencimiento' => $vencimiento,
            'estado' => 'vigente',
        ]);
    }
}
