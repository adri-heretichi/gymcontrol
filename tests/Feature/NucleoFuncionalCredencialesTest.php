<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use App\Services\CredencialService;
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

    // ─── Test 9: Vencimiento de cuota NO revoca credenciales automáticamente ────

    public function test_vencimiento_cuota_no_revoca_credenciales(): void
    {
        // Emitir primero
        $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->socio->refresh();
        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);

        // Expirar cobertura manualmente
        PagoDetalle::where('inscripcion_id', $this->socio->inscripciones()->first()->id)
            ->update([
                'estado_cobertura'    => PagoDetalle::COBERTURA_SUSPENDIDA,
                'fecha_fin_cobertura' => now()->subDays(5),
            ]);

        // Las credenciales deben seguir emitidas (no hay revocación automática)
        $this->socio->refresh();
        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertNotNull($this->socio->pin);
        $this->assertNotNull($this->socio->qr_token);
    }

    // ─── Test 10: Cambio médico a requerido NO revoca credenciales ──────────────

    public function test_cambio_medico_a_requerido_no_revoca_credenciales(): void
    {
        $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->socio->refresh();
        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);

        // Cambiar decisión médica
        $this->socio->update(['decision_medica' => 'requerido']);
        $this->socio->refresh();

        // Credenciales siguen emitidas
        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertNotNull($this->socio->pin);
    }

    // ─── Test 11: Reposición autorizada por dueña (con auditoría) ───────────────

    public function test_reposicion_autorizada_por_duena_con_auditoria(): void
    {
        // Emitir primero
        $this->service->emitirPrimeraVez($this->socio, $this->duena);
        $this->socio->refresh();
        $pinOriginal = $this->socio->pin;

        // Reponer
        $resultado = $this->service->reponerCredenciales(
            $this->socio,
            $this->duena,
            'Socio perdió tarjeta'
        );
        $this->socio->refresh();

        $this->assertEquals(CredencialService::ESTADO_EMITIDA, $this->socio->estado_credencial);
        $this->assertNotEquals($pinOriginal, $this->socio->pin, 'El PIN debe cambiar al reponer.');

        // Verificar auditoría
        $this->assertDatabaseHas('auditorias', [
            'accion'   => 'credencial_repuesta',
            'user_id'  => $this->duena->id,
        ]);
    }

    // ─── Test 12: Revocación documentada ────────────────────────────────────────

    public function test_revocacion_documentada(): void
    {
        $tokenHeredado = $this->socio->token;
        $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->service->revocarCredenciales($this->socio, $this->duena, 'Conducta inapropiada');
        $this->socio->refresh();

        $this->assertEquals(CredencialService::ESTADO_REVOCADA, $this->socio->estado_credencial);
        $this->assertNull($this->socio->pin);
        $this->assertNull($this->socio->qr_token);
        $this->assertEquals('Conducta inapropiada', $this->socio->motivo_revocacion);
        $this->assertNotNull($this->socio->credenciales_revocadas_at);

        $this->get(route('terminal.scan', $tokenHeredado))
            ->assertForbidden()
            ->assertSee('La credencial no es válida para el acceso actual.');
        $this->assertDatabaseMissing('asistencias', ['socio_id' => $this->socio->id]);
    }

    // ─── Test 13: Auditoría de emisión registrada ────────────────────────────────

    public function test_auditoria_de_emision_registrada(): void
    {
        $secretos = $this->service->emitirPrimeraVez($this->socio, $this->duena);

        $this->assertDatabaseHas('auditorias', [
            'accion'  => 'credencial_emitida',
            'user_id' => $this->duena->id,
        ]);

        $auditoria = Auditoria::where('accion', 'credencial_emitida')->firstOrFail();
        $datos = json_encode([$auditoria->datos_antes, $auditoria->datos_despues]);
        $this->assertStringNotContainsString($secretos['pin'], $datos);
        $this->assertStringNotContainsString($secretos['qr_token'], $datos);
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
    }
}
