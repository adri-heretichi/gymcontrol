<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Auditoria;
use App\Models\ConfiguracionGimnasio;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\AsistenciaClaseService;
use App\Services\ControlAccesoService;
use App\Services\CredencialService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NucleoFuncionalAsistenciaClasesTest extends TestCase
{
    use RefreshDatabase;

    private User $duena;
    private User $recepcionista;
    private Socio $socio;
    private Grupo $funcional;
    private Grupo $kickboxing;
    private Carbon $ahora;
    private ControlAccesoService $control;
    private AsistenciaClaseService $clases;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        $this->ahora = Carbon::parse('2026-11-09 18:30:00', config('app.timezone'));
        Carbon::setTestNow($this->ahora);
        $this->duena = User::create([
            'name' => 'Dueña asistencias',
            'email' => 'duena-asistencias@test.com',
            'password' => 'password',
            'rol' => 'admin',
        ]);
        $this->recepcionista = User::create([
            'name' => 'Recepción asistencias',
            'email' => 'recepcion-asistencias@test.com',
            'password' => 'password',
            'rol' => 'recepcionista',
        ]);
        $membresia = Membresia::create([
            'nombre' => 'Membresía asistencias',
            'precio' => 1000,
            'duracion_dias' => 30,
            'estado' => 'activo',
        ]);
        $socio = $this->crearSocio($membresia, '123456', str_repeat('a', 64));
        $disciplinaFuncional = Disciplina::create(['nombre' => 'Funcional', 'estado' => 'activa']);
        $disciplinaKickboxing = Disciplina::create(['nombre' => 'Kickboxing', 'estado' => 'activa']);
        $this->funcional = $this->crearGrupo($disciplinaFuncional, 'Funcional lunes');
        $this->kickboxing = $this->crearGrupo($disciplinaKickboxing, 'Kickboxing lunes');
        $this->horario($this->funcional, 'lunes', '18:00:00', '19:00:00');
        $this->horario($this->kickboxing, 'lunes', '19:00:00', '20:00:00');
        $this->aprobarYAbonar($socio, $this->funcional, 'aprobada');
        $this->aprobarYAbonar($socio, $this->kickboxing, 'aprobada');
        $this->socio = $socio;
        $this->control = app(ControlAccesoService::class);
        $this->clases = app(AsistenciaClaseService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_physical_entry_is_recorded_once_with_operator_and_origin(): void
    {
        $result = $this->control->registrarIngreso(
            $this->socio->pin,
            ControlAccesoService::METODO_PIN,
            $this->funcional->id,
            $this->recepcionista->id,
            $this->ahora,
            'pin_recepcion',
            'recepcion'
        );

        $this->assertTrue($result['autorizado']);
        $this->assertSame('pin_recepcion', $result['asistencia']->metodo_acceso);
        $this->assertSame('recepcion', $result['asistencia']->dispositivo_origen);
        $this->assertSame($this->recepcionista->id, $result['asistencia']->operador_ingreso_id);
        $this->assertNull($result['asistencia']->fecha_salida);
        $this->assertSame(1, Asistencia::where('socio_id', $this->socio->id)->count());
    }

    public function test_denied_entry_creates_no_physical_movement(): void
    {
        $this->socio->update(['estado' => 'inactivo']);
        $result = $this->control->registrarIngreso(
            $this->socio->pin,
            ControlAccesoService::METODO_PIN,
            $this->funcional->id,
            null,
            $this->ahora
        );

        $this->assertFalse($result['autorizado']);
        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_explicit_exit_calculates_cross_day_duration_and_keeps_entry_method(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '23:00:00',
            'metodo_acceso' => 'qr_movil',
        ]);
        $result = $this->control->registrarSalida(
            $this->socio,
            $this->recepcionista->id,
            Carbon::parse('2026-11-10 01:10:00', config('app.timezone')),
            'pin_recepcion'
        );

        $this->assertTrue($result['autorizado']);
        $this->assertSame('2026-11-10', $entry->fresh()->fecha_salida->toDateString());
        $this->assertSame('qr_movil', $entry->fresh()->metodo_acceso);
        $this->assertSame('pin_recepcion', $entry->fresh()->metodo_salida);
        $this->assertSame(130, $entry->fresh()->tiempo_permanencia);
        $this->assertSame($this->recepcionista->id, $entry->fresh()->operador_salida_id);
    }

    public function test_exit_without_open_movement_never_creates_an_entry_and_is_not_blocked_by_expired_requirements(): void
    {
        $this->expirarCobertura();
        $result = $this->control->registrarSalida($this->socio, $this->recepcionista->id, $this->ahora);
        $this->assertFalse($result['autorizado']);
        $this->assertSame('sin_ingreso_abierto', $result['codigo']);
        $this->assertDatabaseCount('asistencias', 0);

        $this->socio->update(['decision_medica' => 'requerido']);
        $this->socio->aptosFisicos()->create([
            'archivo' => null,
            'fecha_emision' => '2026-01-01',
            'fecha_vencimiento' => '2026-11-08',
            'estado' => 'vencido',
        ]);
        $open = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '17:00:00',
        ]);
        $result = $this->control->registrarSalida(
            $this->socio,
            $this->recepcionista->id,
            $this->ahora
        );
        $this->assertTrue($result['autorizado']);
        $this->assertNotNull($open->fresh()->hora_salida);
        $this->assertSame(90, $open->fresh()->tiempo_permanencia);
    }

    public function test_duplicate_open_physical_entries_are_rejected(): void
    {
        $first = $this->control->registrarIngreso(
            $this->socio->pin,
            'pin',
            $this->funcional->id,
            null,
            Carbon::parse('2026-11-09 18:00:00', config('app.timezone'))
        );
        $second = $this->control->registrarIngreso(
            $this->socio->pin,
            'pin',
            $this->funcional->id,
            null,
            $this->ahora
        );

        $this->assertTrue($first['autorizado']);
        $this->assertSame('ingreso_duplicado', $second['codigo']);
        $this->assertSame(1, Asistencia::where('socio_id', $this->socio->id)->count());
    }

    public function test_two_classes_are_individually_recorded_during_one_physical_stay(): void
    {
        $entry = $this->control->registrarIngreso(
            $this->socio->pin,
            'pin',
            $this->funcional->id,
            null,
            Carbon::parse('2026-11-09 18:00:00', config('app.timezone'))
        )['asistencia'];
        $first = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $entry
        );
        $this->assertTrue($first['autorizado']);
        $this->assertSame(AsistenciaClaseService::ESTADO_PENDIENTE, $first['asistencia_clase']->estado);

        $segundaClase = Carbon::parse('2026-11-09 19:30:00', config('app.timezone'));
        Carbon::setTestNow($segundaClase);
        $second = $this->clases->registrarAsistencia(
            $this->socio,
            $this->kickboxing,
            $this->recepcionista,
            $segundaClase,
            $entry
        );
        $this->assertTrue($second['autorizado']);
        $this->assertSame(2, AsistenciaClase::where('socio_id', $this->socio->id)->count());
        $this->assertSame(1, Asistencia::where('socio_id', $this->socio->id)->count());

        $exit = $this->control->registrarSalida(
            $this->socio,
            $this->recepcionista->id,
            Carbon::parse('2026-11-09 20:10:00', config('app.timezone'))
        );
        $this->assertTrue($exit['autorizado']);
        $this->assertSame(130, $entry->fresh()->tiempo_permanencia);
        $this->assertSame(
            [$this->funcional->id, $this->kickboxing->id],
            AsistenciaClase::where('socio_id', $this->socio->id)->orderBy('id')->pluck('grupo_id')->all()
        );
    }

    public function test_class_registration_requires_approved_enrollment_coverage_and_exact_discipline(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $anotherSocio = $this->crearSocio($this->socio->membresia, '234567', str_repeat('b', 64));
        $pending = $this->aprobarYAbonar($anotherSocio, $this->funcional, 'pendiente');
        $result = $this->clases->registrarAsistencia(
            $anotherSocio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $entry
        );
        $this->assertSame('inscripcion_no_aprobada', $result['codigo']);

        $kickboxingEnrollment = $this->socio->inscripciones()->where('grupo_id', $this->kickboxing->id)->firstOrFail();
        $kickboxingEnrollment->pagoDetalles()->delete();
        $wrongDisciplineCoverage = $this->clases->registrarAsistencia(
            $this->socio,
            $this->kickboxing,
            $this->recepcionista,
            Carbon::parse('2026-11-09 19:30:00', config('app.timezone')),
            $entry
        );
        $this->assertSame('sin_cobertura', $wrongDisciplineCoverage['codigo']);
        $this->assertDatabaseCount('asistencias_clases', 0);
        $this->assertSame(Inscripcion::ESTADO_PENDIENTE, $pending->estado);
    }

    public function test_duplicate_class_attendance_is_rejected_without_creating_a_second_physical_entry(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $first = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $entry
        );
        $second = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora->copy()->addMinute(),
            $entry
        );
        $this->assertTrue($first['autorizado']);
        $this->assertSame('asistencia_duplicada', $second['codigo']);
        $this->assertSame(1, AsistenciaClase::count());
        $this->assertSame(1, Asistencia::count());
    }

    public function test_class_requires_resolved_medical_clearance_open_stay_and_current_class_time(): void
    {
        $this->socio->update(['decision_medica' => 'pendiente']);
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $medical = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $entry
        );
        $this->assertSame('requisito_medico_pendiente', $medical['codigo']);
        $this->assertSame('El acceso a esta clase no está habilitado.', $medical['mensaje']);
        $audit = Auditoria::where('accion', 'asistencia_clase_denegada')->firstOrFail();
        $this->assertStringNotContainsString('medico', json_encode($audit->datos_despues));

        $this->socio->update(['decision_medica' => 'no_requerido']);
        $outsideSchedule = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            Carbon::parse('2026-11-09 19:01:00', config('app.timezone')),
            $entry
        );
        $this->assertSame('fuera_de_horario', $outsideSchedule['codigo']);

        $this->funcional->update(['estado' => 'inactivo']);
        $disabledGroup = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $entry
        );
        $this->assertSame('grupo_inactivo', $disabledGroup['codigo']);
        $this->assertSame(0, AsistenciaClase::count());

        $this->funcional->update(['estado' => 'activo']);
        $entry->update(['hora_salida' => '18:20:00', 'fecha_salida' => '2026-11-09']);
        $noOpenStay = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $entry
        );
        $this->assertSame('sin_permanencia_abierta', $noOpenStay['codigo']);
    }

    public function test_punctual_late_and_unconfigured_tardiness_states_are_distinguished(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $this->clases->guardarTolerancia(10, $this->duena);

        $punctualSocio = $this->crearSocio($this->socio->membresia, '345678', str_repeat('c', 64));
        $this->aprobarYAbonar($punctualSocio, $this->funcional, 'aprobada');
        $punctualEntry = Asistencia::create([
            'socio_id' => $punctualSocio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $punctual = $this->clases->registrarAsistencia(
            $punctualSocio,
            $this->funcional,
            $this->recepcionista,
            Carbon::parse('2026-11-09 18:08:00', config('app.timezone')),
            $punctualEntry
        );
        $this->assertSame(AsistenciaClaseService::ESTADO_PUNTUAL, $punctual['asistencia_clase']->estado);

        $late = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            Carbon::parse('2026-11-09 18:15:00', config('app.timezone')),
            $entry
        );
        $this->assertSame(AsistenciaClaseService::ESTADO_TARDE, $late['asistencia_clase']->estado);

        $this->clases->guardarTolerancia(null, $this->duena);
        $unconfiguredSocio = $this->crearSocio($this->socio->membresia, '456789', str_repeat('d', 64));
        $this->aprobarYAbonar($unconfiguredSocio, $this->funcional, 'aprobada');
        $unconfiguredEntry = Asistencia::create([
            'socio_id' => $unconfiguredSocio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $unknown = $this->clases->registrarAsistencia(
            $unconfiguredSocio,
            $this->funcional,
            $this->recepcionista,
            Carbon::parse('2026-11-09 18:15:00', config('app.timezone')),
            $unconfiguredEntry
        );
        $this->assertSame(AsistenciaClaseService::ESTADO_PENDIENTE, $unknown['asistencia_clase']->estado);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'tolerancia_tardanza_actualizada',
            'user_id' => $this->duena->id,
        ]);
    }

    public function test_absence_cannot_be_confirmed_before_class_end_and_only_for_today(): void
    {
        Carbon::setTestNow($this->ahora);
        $this->expectException(ValidationException::class);
        try {
            $this->clases->confirmarAusencias(
                $this->funcional,
                $this->ahora->toDateString(),
                '18:00:00',
                $this->duena,
                $this->ahora
            );
        } finally {
            $this->assertDatabaseCount('asistencias_clases', 0);
        }
    }

    public function test_finished_class_absence_is_confirmed_only_for_eligible_enrollments(): void
    {
        $later = Carbon::parse('2026-11-09 19:01:00', config('app.timezone'));
        $count = $this->clases->confirmarAusencias(
            $this->funcional,
            $later->toDateString(),
            '18:00:00',
            $this->duena,
            $later
        );
        $this->assertSame(1, $count);
        $absence = AsistenciaClase::firstOrFail();
        $this->assertSame(AsistenciaClaseService::ESTADO_AUSENCIA_CONFIRMADA, $absence->estado);
        $this->assertNull($absence->hora_marcado);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'ausencia_clase_confirmada',
            'auditable_id' => $absence->id,
        ]);
        $this->assertSame(0, $this->clases->confirmarAusencias(
            $this->funcional,
            $later->toDateString(),
            '18:00:00',
            $this->duena,
            $later
        ));
    }

    public function test_open_stays_have_no_final_duration_and_owner_corrections_are_audited(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $this->assertNull($entry->tiempo_permanencia);

        $corrected = $this->control->corregirPermanencia(
            $entry,
            '2026-11-09',
            '17:55:00',
            '2026-11-09',
            '20:10:00',
            $this->duena
        );
        $this->assertSame(135, $corrected->tiempo_permanencia);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'permanencia_corregida',
            'auditable_type' => Asistencia::class,
            'auditable_id' => $entry->id,
        ]);
        $this->assertSame(1, Asistencia::whereKey($entry->id)->count());
    }

    public function test_reception_cannot_correct_or_change_tardiness_configuration(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        try {
            $this->control->corregirPermanencia(
                $entry,
                '2026-11-09',
                '18:00:00',
                null,
                null,
                $this->recepcionista
            );
            $this->fail('Reception must not correct physical attendance.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('asistencias', ['id' => $entry->id, 'hora_ingreso' => '18:00:00']);
        }

        try {
            $this->clases->guardarTolerancia(10, $this->recepcionista);
            $this->fail('Reception must not change tardiness configuration.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('configuraciones_gimnasio', 0);
        }
    }

    public function test_class_corrections_require_owner_and_keep_before_after_audit_snapshots(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $mark = $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $entry
        )['asistencia_clase'];

        try {
            $this->clases->corregirAsistencia(
                $mark,
                AsistenciaClaseService::ESTADO_TARDE,
                '18:30:00',
                $this->recepcionista
            );
            $this->fail('Reception must not correct a class attendance.');
        } catch (AuthorizationException) {
            $this->assertSame(1, AsistenciaClase::count());
        }

        $corrected = $this->clases->corregirAsistencia(
            $mark,
            AsistenciaClaseService::ESTADO_TARDE,
            '18:30:00',
            $this->duena
        );
        $this->assertSame(AsistenciaClaseService::ESTADO_TARDE, $corrected->estado);
        $audit = Auditoria::where('accion', 'asistencia_clase_corregida')->firstOrFail();
        $this->assertSame(AsistenciaClaseService::ESTADO_PENDIENTE, $audit->datos_antes['estado']);
        $this->assertSame(AsistenciaClaseService::ESTADO_TARDE, $audit->datos_despues['estado']);
        $this->assertSame(1, AsistenciaClase::count());
    }

    public function test_historical_rows_remain_visible_and_class_records_are_linked_to_one_physical_stay(): void
    {
        $historical = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2025-01-01',
            'hora_ingreso' => '08:00:00',
            'hora_salida' => '09:00:00',
            'tiempo_permanencia' => 60,
        ]);
        $new = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $this->clases->registrarAsistencia(
            $this->socio,
            $this->funcional,
            $this->recepcionista,
            $this->ahora,
            $new
        );

        $this->assertSame('2025-01-01', $historical->fresh()->fecha->toDateString());
        $this->assertSame($new->id, AsistenciaClase::firstOrFail()->asistencia_id);
        $this->assertSame(2, Asistencia::count());
    }

    public function test_gate_protects_administrative_attendance_component_actions(): void
    {
        $this->actingAs($this->recepcionista);
        \Livewire\Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->call('guardarTolerancia')
            ->assertForbidden();
    }

    public function test_api_permanence_correction_uses_the_audited_owner_service(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $token = $this->duena->createToken('attendance-correction')->plainTextToken;
        $this->withToken($token)
            ->putJson(route('api.asistencias.update', $entry->id), [
                'fecha' => '2026-11-09',
                'hora_ingreso' => '18:00:00',
                'fecha_salida' => '2026-11-09',
                'hora_salida' => '20:10:00',
            ])
            ->assertOk()
            ->assertJsonPath('data.tiempo_permanencia', 130);

        $this->assertDatabaseHas('auditorias', [
            'accion' => 'permanencia_corregida',
            'auditable_type' => Asistencia::class,
            'auditable_id' => $entry->id,
            'user_id' => $this->duena->id,
        ]);
    }

    public function test_reception_can_request_but_not_apply_a_physical_correction(): void
    {
        $entry = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => '2026-11-09',
            'hora_ingreso' => '18:00:00',
        ]);
        $this->actingAs($this->recepcionista);
        \Illuminate\Support\Facades\Http::fake();
        \Livewire\Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->call('prepararSolicitudCorreccion', 'permanencia', $entry->id)
            ->set('descripcionCorreccion', 'La hora asentada debe revisarse con el registro del gimnasio.')
            ->call('enviarSolicitudCorreccion')
            ->assertHasNoErrors();

        $solicitud = Solicitud::where('solicitante_id', $this->recepcionista->id)->firstOrFail();
        $this->assertSame(Solicitud::ESTADO_PENDIENTE, $solicitud->estado);
        $this->assertSame('18:00:00', $entry->fresh()->hora_ingreso);
    }

    private function crearSocio(Membresia $membresia, string $pin, string $qr): Socio
    {
        static $sequence = 0;
        $sequence++;

        return Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => "Socio {$sequence}",
            'apellido' => 'Asistencias',
            'dni' => str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'sexo' => 'M',
            'token' => 'legacy-' . $sequence,
            'pin' => $pin,
            'qr_token' => $qr,
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'fecha_alta' => '2026-01-01',
            'estado' => 'activo',
            'decision_medica' => 'no_requerido',
        ]);
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

    private function horario(Grupo $grupo, string $dia, string $inicio, string $fin): void
    {
        GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);
    }

    private function aprobarYAbonar(Socio $socio, Grupo $grupo, string $estado): Inscripcion
    {
        $inscripcion = Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => '2026-01-01',
            'estado' => $estado,
            'aprobado_por' => $estado === Inscripcion::ESTADO_APROBADA ? $this->duena->id : null,
            'aprobado_at' => $estado === Inscripcion::ESTADO_APROBADA ? $this->ahora : null,
        ]);
        $pago = Pago::create([
            'socio_id' => $socio->id,
            'operador_id' => $this->duena->id,
            'fecha_pago' => '2026-11-01',
            'importe' => 1000,
            'metodo_pago' => 'efectivo',
            'estado' => Pago::ESTADO_ACTIVO,
        ]);
        PagoDetalle::create([
            'pago_id' => $pago->id,
            'inscripcion_id' => $inscripcion->id,
            'concepto' => 'Cuota',
            'fecha_inicio_cobertura' => '2026-11-01',
            'fecha_fin_cobertura' => '2026-11-30',
            'subtotal' => 1000,
            'estado_cobertura' => PagoDetalle::COBERTURA_VIGENTE,
        ]);

        return $inscripcion;
    }

    private function expirarCobertura(): void
    {
        PagoDetalle::query()->whereIn('inscripcion_id', $this->socio->inscripciones()->pluck('id'))
            ->update(['fecha_fin_cobertura' => '2026-11-08']);
    }
}
