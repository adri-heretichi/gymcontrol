<?php

namespace Tests\Feature;

use App\Livewire\Socios\ListarSocios;
use App\Livewire\Socios\VerSocio;
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
use App\Services\CredencialService;
use App\Services\ControlAccesoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NucleoFuncionalCredencialesTest extends TestCase
{
    use RefreshDatabase;

    protected User    $duena;
    protected User    $recepcionista;
    protected Socio   $socio;
    protected CredencialService $service;
    protected Membresia $membresia;
    private int $fixtureCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CredencialService::class);

        $this->duena = User::create([
            'name' => 'Dueña de prueba',
            'rol' => 'admin',
            'email' => 'duena@test.com',
            'password' => 'password',
        ]);

        $this->recepcionista = User::create([
            'name' => 'Recepcionista de prueba',
            'rol' => 'recepcionista',
            'email' => 'recep@test.com',
            'password' => 'password',
        ]);

        $this->membresia = Membresia::create([
            'nombre' => 'Membresía de prueba',
            'precio' => 30000,
            'duracion_dias' => 30,
            'estado' => 'activo',
        ]);

        $this->socio = $this->crearSocio();
        $this->crearInscripcionConCoberturaVigente($this->socio);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    protected function crearSocio(array $atributos = []): Socio
    {
        $numero = ++$this->fixtureCounter;

        return Socio::create(array_merge([
            'membresia_id' => $this->membresia->id,
            'nombre' => "Socio {$numero}",
            'apellido' => 'Prueba',
            'dni' => str_pad((string) $numero, 8, '0', STR_PAD_LEFT),
            'sexo' => 'M',
            'correo' => "socio{$numero}@test.com",
            'celular' => '1122334455',
            'token' => 'token-' . $numero,
            'fecha_alta' => today()->toDateString(),
            'estado' => 'activo',
            'decision_medica' => 'no_requerido',
            'estado_credencial' => CredencialService::ESTADO_SIN_EMITIR,
            'pin' => null,
            'qr_token' => null,
        ], $atributos));
    }

    protected function crearInscripcion(Socio $socio, string $estado = Inscripcion::ESTADO_APROBADA): Inscripcion
    {
        $numero = ++$this->fixtureCounter;
        $disciplina = Disciplina::create([
            'nombre' => "Disciplina {$numero}",
            'estado' => 'activa',
        ]);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => "Grupo {$numero}",
            'cupo_maximo' => 10,
            'estado' => 'activo',
        ]);
        $dia = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo',
        ][(int) today()->format('N')];
        GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => $dia,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
        ]);

        return Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => today()->toDateString(),
            'estado' => $estado,
            'aprobado_por' => $estado === Inscripcion::ESTADO_APROBADA ? $this->duena->id : null,
            'aprobado_at' => $estado === Inscripcion::ESTADO_APROBADA ? now() : null,
        ]);
    }

    protected function crearCoberturaVigente(
        Inscripcion $inscripcion,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): PagoDetalle {
        $pago = Pago::create([
            'socio_id' => $inscripcion->socio_id,
            'operador_id' => $this->duena->id,
            'fecha_pago' => today()->toDateString(),
            'importe' => 30000,
            'metodo_pago' => 'efectivo',
            'estado' => Pago::ESTADO_ACTIVO,
        ]);

        return PagoDetalle::create([
            'pago_id' => $pago->id,
            'inscripcion_id' => $inscripcion->id,
            'concepto' => 'Cuota mensual de prueba',
            'fecha_inicio_cobertura' => $fechaInicio ?? today()->startOfMonth()->toDateString(),
            'fecha_fin_cobertura' => $fechaFin ?? today()->endOfMonth()->toDateString(),
            'subtotal' => 30000,
            'estado_cobertura' => PagoDetalle::COBERTURA_VIGENTE,
        ]);
    }

    protected function crearInscripcionConCoberturaVigente(Socio $socio): Inscripcion
    {
        $inscripcion = $this->crearInscripcion($socio);
        $this->crearCoberturaVigente($inscripcion);
        return $inscripcion;
    }

    // ─── Test 1: Emisión exitosa con todos los requisitos cumplidos ─────────────

    public function test_emision_exitosa_con_todos_los_requisitos(): void
    {
        $resultado = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->socio->refresh();

        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertNotNull($this->socio->pin);
        $this->assertNotNull($this->socio->qr_token);
        $this->assertArrayHasKey('pin', $resultado);
        $this->assertArrayHasKey('qr_token', $resultado);
    }

    // ─── Test 2: Rechazo sin inscripción aprobada ───────────────────────────────

    public function test_rechazo_sin_inscripcion_aprobada(): void
    {
        $socioSinInscripcion = $this->crearSocio([
            'estado'            => 'activo',
            'decision_medica'   => 'no_requerido',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('inscripción aprobada');

        $this->service->emitirPrimeraVez($socioSinInscripcion, $this->duena);
    }

    // ─── Test 3: Rechazo sin cobertura vigente ──────────────────────────────────

    public function test_rechazo_sin_cobertura_vigente(): void
    {
        $socioSinCobertura = $this->crearSocio([
            'estado'            => 'activo',
            'decision_medica'   => 'no_requerido',
        ]);

        $this->crearInscripcion($socioSinCobertura);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cobertura económica vigente');

        $this->service->emitirPrimeraVez($socioSinCobertura, $this->duena);
    }

    // ─── Test 4: Rechazo con decisión médica pendiente ──────────────────────────

    public function test_rechazo_con_decision_medica_pendiente(): void
    {
        $socio = $this->crearSocio([
            'estado'            => 'activo',
            'decision_medica'   => 'pendiente',
        ]);

        $this->crearInscripcionConCoberturaVigente($socio);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('decisión médica');

        $this->service->emitirPrimeraVez($socio, $this->duena);
    }

    public function test_rechazo_con_certificado_requerido_faltante(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);
        $this->crearInscripcionConCoberturaVigente($socio);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('certificado médico vigente');

        $this->service->emitirPrimeraVez($socio, $this->duena);
    }

    public function test_missing_certificate_disables_button_and_direct_action_keeps_credentials_unissued(): void
    {
        $this->socio->update(['decision_medica' => 'requerido']);
        $this->actingAs($this->duena);
        $componente = Livewire::test(\App\Livewire\Socios\VerSocio::class, ['socio' => $this->socio])
            ->assertSee('El socio requiere un certificado médico vigente.');
        $this->assertMatchesRegularExpression('/<button[^>]*wire:click="emitirCredenciales"[^>]*\sdisabled(?:\s|>)/s', $componente->html());
        $componente->call('emitirCredenciales')->assertHasErrors(['credenciales']);
        $socio = $this->socio->fresh();
        $this->assertSame(CredencialService::ESTADO_SIN_EMITIR, $socio->estado_credencial);
        $this->assertNull($socio->pin);
        $this->assertNull($socio->qr_token);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'credencial_emitida', 'auditable_id' => $socio->id]);
    }

    public function test_unpaid_or_inactive_member_cannot_issue_credentials_from_the_profile(): void
    {
        $this->actingAs($this->duena);
        foreach (['sin_pago', 'inactivo'] as $caso) {
            $socio = $this->crearSocio(['estado' => $caso === 'inactivo' ? 'inactivo' : 'activo', 'decision_medica' => 'no_requerido']);
            if ($caso === 'inactivo') {
                $this->crearInscripcionConCoberturaVigente($socio);
            } else {
                $inscripcion = $this->socio->inscripciones()->firstOrFail();
                \App\Models\Inscripcion::create([
                    'socio_id' => $socio->id, 'grupo_id' => $inscripcion->grupo_id,
                    'fecha_inscripcion' => today(), 'estado' => \App\Models\Inscripcion::ESTADO_APROBADA,
                ]);
            }
            $componente = Livewire::test(\App\Livewire\Socios\VerSocio::class, ['socio' => $socio]);
            $this->assertMatchesRegularExpression('/<button[^>]*wire:click="emitirCredenciales"[^>]*\sdisabled(?:\s|>)/s', $componente->html());
            $componente->call('emitirCredenciales')->assertHasErrors(['credenciales']);
            $this->assertSame(CredencialService::ESTADO_SIN_EMITIR, $socio->fresh()->estado_credencial);
            $this->assertNull($socio->fresh()->pin);
            $this->assertNull($socio->fresh()->qr_token);
        }
    }

    public function test_decision_no_requerido_definida_por_duena_permite_emitir(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'pendiente']);
        $this->crearInscripcionConCoberturaVigente($socio);

        app(\App\Services\CertificadoMedicoService::class)->definirDecision(
            $socio,
            \App\Services\CertificadoMedicoService::ESTADO_NO_REQUERIDO,
            $this->duena
        );

        $credenciales = $this->service->emitirPrimeraVez($socio, $this->duena);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $credenciales['pin']);
        $this->assertSame(CredencialService::ESTADO_ACTIVO, $socio->fresh()->estado_credencial);
    }

    // ─── Test 5: No re-emitir si ya tiene credenciales emitidas ─────────────────

    public function test_no_reemitir_si_ya_tiene_credenciales(): void
    {
        $this->socio->update([
            'estado_credencial' => CredencialService::ESTADO_EMITIDA,
            'pin'               => '123456',
            'qr_token'          => str_repeat('a', 64),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ya tiene credenciales emitidas');

        $this->service->emitirPrimeraVez($this->socio, $this->duena);
    }

    // ─── Test 6: PIN tiene exactamente 6 dígitos ────────────────────────────────

    public function test_pin_tiene_exactamente_6_digitos(): void
    {
        $resultado = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $pin = $resultado['pin'];
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin, "El PIN debe ser exactamente 6 dígitos numéricos.");

        $segundoSocio = $this->crearSocio();
        $this->crearInscripcionConCoberturaVigente($segundoSocio);
        $segundoResultado = $this->service->emitirPrimeraVez($segundoSocio, $this->duena);
        $this->assertNotSame($pin, $segundoResultado['pin']);
        $this->assertNotSame($resultado['qr_token'], $segundoResultado['qr_token']);
    }

    // ─── Test 7: QR token es único y criptográficamente seguro (64 chars) ───────

    public function test_qr_token_tiene_64_caracteres_hexadecimales(): void
    {
        $token1 = $this->service->generarQrToken();
        $token2 = $this->service->generarQrToken();

        $this->assertSame(64, strlen($token1));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token1);
        $this->assertNotEquals($token1, $token2, 'Cada QR token debe ser único.');
    }

    // ─── Test 8: Recepcionista no puede emitir (Gate/Policy) ────────────────────

    public function test_recepcionista_no_puede_emitir_via_gate(): void
    {
        $this->actingAs($this->recepcionista);
        $this->assertFalse(
            $this->recepcionista->can('emitirCredenciales', $this->socio),
            'El recepcionista no debe poder emitir credenciales.'
        );

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->service->emitirPrimeraVez($this->socio, $this->recepcionista);
    }

    public function test_recepcionista_no_puede_emitir_desde_livewire(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\Socios\VerSocio::class, ['socio' => $this->socio])
            ->call('emitirCredenciales')
            ->assertForbidden();

        $this->assertSame(CredencialService::ESTADO_SIN_EMITIR, $this->socio->fresh()->estado_credencial);
    }

    public function test_duena_emite_desde_livewire_y_qr_usa_el_token_persistente(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(\App\Livewire\Socios\VerSocio::class, ['socio' => $this->socio])
            ->call('emitirCredenciales')
            ->assertHasNoErrors();

        $this->socio->refresh();
        $this->assertSame(CredencialService::ESTADO_ACTIVO, $this->socio->estado_credencial);

        $this->get(route('socios.qr', $this->socio))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_pin_conserva_ceros_iniciales_y_solo_la_duena_ve_el_pin(): void
    {
        $this->socio->update([
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'pin' => '000123',
            'qr_token' => str_repeat('d', 64),
            'credenciales_emitidas_at' => now(),
        ]);
        $this->socio->refresh();
        $this->assertSame('000123', $this->socio->pin);

        $this->actingAs($this->duena);
        $grupo = $this->socio->inscripciones()->first()->grupo;
        Livewire::test(VerSocio::class, ['socio' => $this->socio])
            ->assertSee('000123')
            ->assertSee('Emitida:')
            ->assertSee($grupo->nombre)
            ->assertSee($grupo->disciplina->nombre);

        $this->actingAs($this->recepcionista);
        Livewire::test(VerSocio::class, ['socio' => $this->socio])
            ->assertDontSee('000123')
            ->assertSee('QR de acceso');
        $this->get(route('socios.qr', $this->socio))->assertOk();
    }

    public function test_listado_general_no_expone_pin_ni_token_qr(): void
    {
        $pin = '000124';
        $qrToken = str_repeat('e', 64);
        $this->socio->update([
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'pin' => $pin,
            'qr_token' => $qrToken,
        ]);

        $this->actingAs($this->duena);
        Livewire::test(ListarSocios::class)
            ->assertDontSee($pin)
            ->assertDontSee($qrToken);

        $this->assertStringNotContainsString($pin, json_encode($this->socio->fresh()));
        $this->assertStringNotContainsString($qrToken, json_encode($this->socio->fresh()));
    }

    public function test_usuario_sin_permiso_no_accede_al_qr_ni_a_la_tarjeta_por_url_directa(): void
    {
        $visitante = User::create([
            'name' => 'Usuario sin rol operativo',
            'rol' => 'visitante',
            'email' => 'visitante@test.com',
            'password' => 'password',
        ]);
        $this->socio->update([
            'estado_credencial' => CredencialService::ESTADO_ACTIVO,
            'pin' => '000125',
            'qr_token' => str_repeat('f', 64),
        ]);

        $this->actingAs($visitante);
        $this->get(route('socios.qr', $this->socio))->assertForbidden();
        $this->get(route('socios.tarjeta-pdf', $this->socio))->assertForbidden();
        Livewire::test(VerSocio::class, ['socio' => $this->socio])->assertForbidden();
    }

    // ─── Test 9: Vencimiento de cuota NO revoca credenciales automáticamente ────

    public function test_vencimiento_cuota_no_revoca_credenciales(): void
    {
        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->socio->refresh();
        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);

        $inscripcion = $this->socio->inscripciones()->first();
        PagoDetalle::where('inscripcion_id', $inscripcion->id)
            ->update([
                'estado_cobertura'    => PagoDetalle::COBERTURA_SUSPENDIDA,
                'fecha_fin_cobertura' => now()->subDays(5),
            ]);

        $this->socio->refresh();
        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertSame($credenciales['pin'], $this->socio->pin);
        $this->assertSame($credenciales['qr_token'], $this->socio->qr_token);

        $resultado = app(ControlAccesoService::class)->evaluarAcceso(
            $credenciales['pin'],
            ControlAccesoService::METODO_PIN,
            $inscripcion->grupo_id,
            Carbon::today()->setTime(8, 30)
        );
        $this->assertFalse($resultado['autorizado']);
        $this->assertSame('cuota_vencida', $resultado['codigo']);
    }

    // ─── Test 10: Cambio médico a requerido NO revoca credenciales ──────────────

    public function test_cambio_medico_a_requerido_no_revoca_credenciales(): void
    {
        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->socio->refresh();
        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);

        app(\App\Services\CertificadoMedicoService::class)->definirDecision(
            $this->socio,
            \App\Services\CertificadoMedicoService::ESTADO_REQUERIDO,
            $this->duena
        );
        $this->socio->refresh();

        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertSame($credenciales['pin'], $this->socio->pin);
        $this->assertSame($credenciales['qr_token'], $this->socio->qr_token);

        $resultado = app(ControlAccesoService::class)->evaluarAcceso(
            $credenciales['pin'],
            ControlAccesoService::METODO_PIN,
            $this->socio->inscripciones()->first()->grupo_id,
            Carbon::today()->setTime(8, 30)
        );
        $this->assertFalse($resultado['autorizado']);
        $this->assertSame('certificado_faltante', $resultado['codigo']);
    }

    public function test_cambio_de_grupo_no_regenera_credenciales(): void
    {
        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $inscripcion = $this->socio->inscripciones()->first();
        $grupoNuevo = Grupo::create([
            'disciplina_id' => $inscripcion->grupo->disciplina_id,
            'nombre' => 'Grupo reasignado',
            'cupo_maximo' => 10,
            'estado' => 'activo',
        ]);

        $inscripcion->update(['grupo_id' => $grupoNuevo->id]);
        $this->socio->refresh();

        $this->assertSame(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertSame($credenciales['pin'], $this->socio->pin);
        $this->assertSame($credenciales['qr_token'], $this->socio->qr_token);
    }

    public function test_certificado_vencido_no_elimina_ni_regenera_credenciales(): void
    {
        $this->socio->update(['decision_medica' => 'requerido']);
        app(\App\Services\CertificadoMedicoService::class)->registrarCertificado(
            socio: $this->socio,
            fechaEmision: today()->subMonth()->toDateString(),
            fechaVencimiento: today()->toDateString(),
            operador: $this->duena
        );
        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        Carbon::setTestNow(today()->addDay()->startOfDay());
        $this->socio->refresh();

        $this->assertSame(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertSame($credenciales['pin'], $this->socio->pin);
        $this->assertSame($credenciales['qr_token'], $this->socio->qr_token);
    }

    public function test_renovar_cuota_conserva_las_credenciales_emitidas(): void
    {
        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $inscripcion = $this->socio->inscripciones()->first();
        PagoDetalle::where('inscripcion_id', $inscripcion->id)->update([
            'estado_cobertura' => PagoDetalle::COBERTURA_SUSPENDIDA,
            'fecha_fin_cobertura' => today()->subDay()->toDateString(),
        ]);
        $this->crearCoberturaVigente(
            $inscripcion,
            today()->toDateString(),
            today()->addMonth()->toDateString()
        );

        $this->socio->refresh();

        $this->assertSame($credenciales['pin'], $this->socio->pin);
        $this->assertSame($credenciales['qr_token'], $this->socio->qr_token);
        $this->assertSame(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
    }

    public function test_emision_no_depende_de_membresias_antiguas(): void
    {
        $this->membresia->update(['nombre' => 'Básica']);

        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $credenciales['pin']);
        $this->assertNotEmpty($credenciales['qr_token']);
    }

    public function test_emision_no_depende_del_horario_actual_de_clase(): void
    {
        Carbon::setTestNow(today()->setTime(3, 0));

        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $credenciales['pin']);
        $this->assertNotEmpty($credenciales['qr_token']);
    }

    public function test_emision_requiere_grupo_y_disciplina_habilitados(): void
    {
        $inscripcion = $this->socio->inscripciones()->first();
        $inscripcion->grupo->update(['estado' => 'inactivo']);

        $resultado = $this->service->evaluarRequisitosEmision($this->socio);
        $this->assertFalse($resultado['habilitado']);
        $this->assertSame('grupo_no_habilitado', $resultado['codigo']);

        $inscripcion->grupo->update(['estado' => 'activo']);
        $inscripcion->grupo->disciplina->update(['estado' => 'inactiva']);

        $resultado = $this->service->evaluarRequisitosEmision($this->socio);
        $this->assertFalse($resultado['habilitado']);
        $this->assertSame('grupo_no_habilitado', $resultado['codigo']);
    }

    // ─── Test 11: Reposición autorizada por dueña (con auditoría) ───────────────

    public function test_reposicion_autorizada_por_duena_con_auditoria(): void
    {
        // Emitir primero
        $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->socio->refresh();
        $pinOriginal = $this->socio->pin;
        $qrOriginal = $this->socio->qr_token;

        // Reponer
        $resultado = $this->service->reponerCredenciales(
            $this->socio,
            $this->duena,
            "Socio perdió tarjeta: {$pinOriginal} {$qrOriginal}"
        );
        $this->socio->refresh();

        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertNotEquals($pinOriginal, $this->socio->pin, 'El PIN debe cambiar al reponer.');
        $this->assertNotEquals($qrOriginal, $this->socio->qr_token);

        $this->assertDatabaseHas('auditorias', [
            'accion'   => 'credencial_repuesta',
            'user_id'  => $this->duena->id,
        ]);
        $auditoria = Auditoria::where('accion', 'credencial_repuesta')->firstOrFail();
        $datosAuditoria = json_encode([$auditoria->datos_antes, $auditoria->datos_despues]);
        $this->assertStringNotContainsString($pinOriginal, $datosAuditoria);
        $this->assertStringNotContainsString($qrOriginal, $datosAuditoria);
    }

    // ─── Test 12: Revocación documentada ────────────────────────────────────────

    public function test_revocacion_documentada(): void
    {
        $credenciales = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->service->revocarCredenciales(
            $this->socio,
            $this->duena,
            "Revocación confirmada {$credenciales['pin']} {$credenciales['qr_token']}"
        );
        $this->socio->refresh();

        $this->assertEquals(CredencialService::ESTADO_REVOCADA, $this->socio->estado_credencial);
        $this->assertNull($this->socio->pin);
        $this->assertNull($this->socio->qr_token);
        $this->assertStringNotContainsString($credenciales['pin'], $this->socio->motivo_revocacion);
        $this->assertStringNotContainsString($credenciales['qr_token'], $this->socio->motivo_revocacion);
        $this->assertNotNull($this->socio->credenciales_revocadas_at);

        $auditoriaRevocacion = Auditoria::where('accion', 'credencial_revocada')->firstOrFail();
        $datosRevocacion = json_encode([$auditoriaRevocacion->datos_antes, $auditoriaRevocacion->datos_despues]);
        $this->assertStringNotContainsString($credenciales['pin'], $datosRevocacion);
        $this->assertStringNotContainsString($credenciales['qr_token'], $datosRevocacion);

        foreach ([
            [$credenciales['pin'], ControlAccesoService::METODO_PIN],
            [$credenciales['qr_token'], ControlAccesoService::METODO_QR],
        ] as [$identificador, $metodo]) {
            $resultado = app(ControlAccesoService::class)
                ->evaluarAcceso($identificador, $metodo);
            $this->assertFalse($resultado['autorizado']);
            $this->assertSame('credencial_revocada', $resultado['codigo']);
        }

        $this->crearCoberturaVigente($this->socio->inscripciones()->first());
        $this->assertSame(CredencialService::ESTADO_REVOCADO, $this->socio->fresh()->estado_credencial);
        $this->assertDatabaseMissing('asistencias', ['socio_id' => $this->socio->id]);
    }

    // ─── Test 13: Auditoría de emisión registrada ────────────────────────────────

    public function test_auditoria_de_emision_registrada(): void
    {
        $observacionClinica = 'dato-clinico-no-auditable-7782';
        $this->socio->update(['decision_medica_obs' => $observacionClinica]);
        $secretos = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->assertDatabaseHas('auditorias', [
            'accion'  => 'credencial_emitida',
            'user_id' => $this->duena->id,
        ]);

        $auditoria = Auditoria::where('accion', 'credencial_emitida')->firstOrFail();
        $datos = json_encode([$auditoria->datos_antes, $auditoria->datos_despues]);
        $this->assertStringNotContainsString($secretos['pin'], $datos);
        $this->assertStringNotContainsString($secretos['qr_token'], $datos);
        $this->assertStringNotContainsString($observacionClinica, json_encode(Auditoria::all()));
    }

    // ─── Test 14: No re-emitir QR/PIN al aprobar inscripción ────────────────────

    public function test_no_reemitir_al_aprobar_inscripcion(): void
    {
        // Socio con credenciales ya emitidas
        $this->socio->update([
            'estado_credencial' => CredencialService::ESTADO_EMITIDA,
            'pin'               => '654321',
            'qr_token'          => str_repeat('b', 64),
        ]);

        // Crear nueva inscripción pendiente y aprobarla
        $nuevaInscripcion = $this->crearInscripcion($this->socio, Inscripcion::ESTADO_PENDIENTE);

        // Aprobar inscripción directamente
        $nuevaInscripcion->update(['estado' => Inscripcion::ESTADO_APROBADA]);
        $this->socio->refresh();

        // PIN y QR no deben haber cambiado
        $this->assertEquals('654321', $this->socio->pin);
        $this->assertEquals(str_repeat('b', 64), $this->socio->qr_token);
    }

    // ─── Test 15: No re-emitir al registrar pago ────────────────────────────────

    public function test_no_reemitir_al_registrar_pago(): void
    {
        $this->socio->update([
            'estado_credencial' => CredencialService::ESTADO_EMITIDA,
            'pin'               => '789012',
            'qr_token'          => str_repeat('c', 64),
        ]);

        $inscripcion = $this->socio->inscripciones()->first();

        $this->crearCoberturaVigente(
            $inscripcion,
            today()->startOfMonth()->toDateString(),
            today()->addMonth()->endOfMonth()->toDateString()
        );

        $this->socio->refresh();

        // PIN y QR no deben haber cambiado por el nuevo pago
        $this->assertEquals('789012', $this->socio->pin);
        $this->assertEquals(str_repeat('c', 64), $this->socio->qr_token);
    }

    // ─── Test 16: evaluarRequisitosEmision devuelve habilitado true ──────────────

    public function test_evaluacion_devuelve_habilitado_true_cuando_cumple_todo(): void
    {
        $resultado = $this->service->evaluarRequisitosEmision($this->socio);

        $this->assertTrue($resultado['habilitado']);
        $this->assertNull($resultado['motivo']);
    }

    // ─── Test 17: evaluarRequisitosEmision devuelve motivo cuando falla ──────────

    public function test_evaluacion_devuelve_motivo_cuando_no_cumple(): void
    {
        $socioNuevo = $this->crearSocio([
            'decision_medica'   => 'pendiente',
        ]);

        $resultado = $this->service->evaluarRequisitosEmision($socioNuevo);

        $this->assertFalse($resultado['habilitado']);
        $this->assertNotEmpty($resultado['motivo']);
    }

    // ─── Test 18: Revocación sin motivo lanza excepción ─────────────────────────

    public function test_revocacion_sin_motivo_lanza_excepcion(): void
    {
        $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('motivo');

        $this->service->revocarCredenciales($this->socio, $this->duena, '   ');
    }

    // ─── Test 19: Reposición sin motivo lanza excepción ─────────────────────────

    public function test_reposicion_sin_motivo_lanza_excepcion(): void
    {
        $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('motivo');

        $this->service->reponerCredenciales($this->socio, $this->duena, '');
    }

    // ─── Test 20: Auditoria de revocación registrada ─────────────────────────────

    public function test_auditoria_de_revocacion_registrada(): void
    {
        $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->service->revocarCredenciales($this->socio, $this->duena, 'Prueba revocación');

        $this->assertDatabaseHas('auditorias', [
            'accion'  => 'credencial_revocada',
            'user_id' => $this->duena->id,
        ]);
    }

    // ─── Test 21: PIN generado no es secuencial ascendente ───────────────────────

    public function test_pin_no_es_secuencial(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $pin = $this->service->generarPin($this->socio);
            $this->assertNotEquals('123456', $pin);
            $this->assertNotEquals('234567', $pin);
            $this->assertNotEquals('987654', $pin);
            $this->assertNotEquals('876543', $pin);
        }
    }

    // ─── Test 22: Dueña puede emitir (Gate/Policy) ──────────────────────────────

    public function test_duena_puede_emitir_via_gate(): void
    {
        $this->actingAs($this->duena);
        $this->assertTrue(
            $this->duena->can('emitirCredenciales', $this->socio),
            'La dueña debe poder emitir credenciales.'
        );
    }

    // ─── Test 23: credenciales_emitidas_at se establece al emitir ───────────────

    public function test_credenciales_emitidas_at_se_establece_al_emitir(): void
    {
        $this->assertNull($this->socio->credenciales_emitidas_at);

        $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->socio->refresh();

        $this->assertNotNull($this->socio->credenciales_emitidas_at);
    }

    // ─── Test 24: Reposición luego de revocación funciona ───────────────────────

    public function test_reposicion_luego_de_revocacion(): void
    {
        $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->service->revocarCredenciales($this->socio, $this->duena, 'Pérdida');

        $this->socio->refresh();
        $this->assertEquals(CredencialService::ESTADO_REVOCADA, $this->socio->estado_credencial);

        // Reposición después de revocación (es como una nueva emisión desde revocado)
        $resultado = $this->service->reponerCredenciales($this->socio, $this->duena, 'Recuperación autorizada');
        $this->socio->refresh();

        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertNotNull($this->socio->pin);
    }

    public function test_primera_emision_rechaza_socio_inactivo(): void
    {
        $socioInactivo = $this->crearSocio(['estado' => 'inactivo']);
        $this->crearInscripcionConCoberturaVigente($socioInactivo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no está activo');

        $this->service->emitirPrimeraVez($socioInactivo, $this->duena);
    }

    public function test_primera_emision_requiere_certificado_vigente_si_la_duena_lo_exige(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);
        $this->crearInscripcionConCoberturaVigente($socio);

        $this->assertFalse($this->service->evaluarRequisitosEmision($socio)['habilitado']);

        app(\App\Services\CertificadoMedicoService::class)->registrarCertificado(
            socio: $socio,
            fechaEmision: today()->subMonth()->toDateString(),
            fechaVencimiento: today()->toDateString(),
            operador: $this->duena
        );

        $this->assertTrue($this->service->evaluarRequisitosEmision($socio)['habilitado']);
        $credenciales = $this->service->emitirPrimeraVez($socio, $this->duena);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $credenciales['pin']);
    }
}
