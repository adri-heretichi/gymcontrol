<?php

namespace Tests\Feature;

use App\Models\AptoFisico;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\CertificadoMedicoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NucleoFuncionalCertificadosMedicosTest – Fase 6.
 *
 * Cubre las 24 pruebas obligatorias especificadas en el requerimiento de la Fase 6:
 * 1.  Alta de socio con decisión médica inicial 'pendiente'.
 * 2.  Dueña marca 'requerido'.
 * 3.  Dueña marca 'no_requerido'.
 * 4.  Recepcionista no puede cambiar la decisión médica.
 * 5.  Usuario no autenticado no puede modificarla.
 * 6.  Petición HTTP manipulada es rechazada (403/Policy).
 * 7.  Decisión pendiente no cumple el requisito (Caso A).
 * 8.  No requerido cumple el requisito (Caso B).
 * 9.  Requerido sin certificado no cumple (Caso C).
 * 10. Requerido con certificado vigente cumple (Caso D).
 * 11. Requerido con certificado vencido no cumple (Caso E).
 * 12. Cambio posterior de 'no_requerido' a 'requerido'.
 * 13. Cambio posterior de 'requerido' a 'no_requerido'.
 * 14. Conservación del PIN ante cambio de decisión médica.
 * 15. Conservación del QR token ante cambio de decisión médica.
 * 16. Registro administrativo de documentación por recepción.
 * 17. Conservación del historial de certificados presentados.
 * 18. Auditoría obligatoria de decisiones y cargas médicas.
 * 19. Archivos médicos protegidos en almacenamiento privado.
 * 20. No emisión automática de credenciales al registrar documentos.
 * 21. No aprobación automática de inscripciones ante certificado o decisión favorable.
 * 22. No modificación automática de pagos o cuotas.
 * 23. Compatibilidad con datos y certificados existentes.
 * 24. Autorizaciones correctas en Livewire y controladores.
 */
class NucleoFuncionalCertificadosMedicosTest extends TestCase
{
    use RefreshDatabase;

    private CertificadoMedicoService $service;
    private User $duena;
    private User $recepcionista;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->service = app(CertificadoMedicoService::class);

        $this->duena = User::create([
            'name'     => 'Dueña Gimnasio',
            'email'    => 'duena@fenix.test',
            'password' => bcrypt('password'),
            'rol'      => 'admin',
        ]);

        $this->recepcionista = User::create([
            'name'     => 'Recepcionista Fénix',
            'email'    => 'recepcion@fenix.test',
            'password' => bcrypt('password'),
            'rol'      => 'recepcionista',
        ]);

    }

    private function crearSocio(array $atributos = []): Socio
    {
        static $n = 0;
        $n++;

        return Socio::create(array_merge([
            'nombre'          => "Socio{$n}",
            'apellido'        => 'Prueba',
            'dni'             => str_pad((string)$n, 8, '0', STR_PAD_LEFT),
            'sexo'            => 'M',
            'correo'          => "socio{$n}@fenix.test",
            'celular'         => '1122334455',
            'token'           => \Illuminate\Support\Str::uuid()->toString(),
            'fecha_alta'      => now()->toDateString(),
            'estado'          => 'activo',
            'decision_medica' => 'pendiente',
        ], $atributos));
    }

    // ────────────────────────────────────────────────────────────────────────────
    // PRUEBAS OBLIGATORIAS (1 a 24)
    // ────────────────────────────────────────────────────────────────────────────

    /** 01 – Alta de socio con decisión pendiente */
    public function test_01_alta_socio_con_decision_pendiente(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\Socios\CrearSocio::class)
            ->set('nombre', 'Carlos')
            ->set('apellido', 'Gómez')
            ->set('dni', '30111222')
            ->set('sexo', 'M')
            ->set('correo', 'carlos@fenix.test')
            ->set('celular', '1144332211')
            ->set('fecha_alta', now()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors();

        $socio = Socio::where('dni', '30111222')->first();
        $this->assertNotNull($socio);
        $this->assertEquals('pendiente', $socio->decision_medica);
        $this->assertNull($socio->decision_medica_at);
        $this->assertNull($socio->decision_medica_por);
        $this->assertEquals('sin_emitir', $socio->estado_credencial);
    }

    /** 02 – Dueña marca requerido */
    public function test_02_duena_marca_requerido(): void
    {
        $socio = $this->crearSocio();

        $this->actingAs($this->duena);
        $this->assertTrue($this->duena->can('gestionarDecisionMedica', $socio));

        $socioActualizado = $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_REQUERIDO,
            duena: $this->duena,
            observacion: 'Requiere apto por disciplina de alto impacto'
        );

        $this->assertEquals(CertificadoMedicoService::ESTADO_REQUERIDO, $socioActualizado->decision_medica);
        $this->assertEquals($this->duena->id, $socioActualizado->decision_medica_por);
        $this->assertNotNull($socioActualizado->decision_medica_at);
        $this->assertEquals('Requiere apto por disciplina de alto impacto', $socioActualizado->decision_medica_obs);
    }

    /** 03 – Dueña marca no requerido */
    public function test_03_duena_marca_no_requerido(): void
    {
        $socio = $this->crearSocio();

        $this->actingAs($this->duena);

        $socioActualizado = $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_NO_REQUERIDO,
            duena: $this->duena,
            observacion: 'Exento por declaración jurada administrativa'
        );

        $this->assertEquals(CertificadoMedicoService::ESTADO_NO_REQUERIDO, $socioActualizado->decision_medica);
        $this->assertEquals($this->duena->id, $socioActualizado->decision_medica_por);
        $this->assertNotNull($socioActualizado->decision_medica_at);
    }

    /** 04 – Recepcionista no puede cambiar la decisión médica */
    public function test_04_recepcionista_no_puede_cambiar_decision(): void
    {
        $socio = $this->crearSocio();

        $this->actingAs($this->recepcionista);
        $this->assertFalse($this->recepcionista->can('gestionarDecisionMedica', $socio));

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_NO_REQUERIDO,
            duena: $this->recepcionista
        );
    }

    /** 05 – Usuario no autenticado no puede modificar la decisión */
    public function test_05_usuario_no_autenticado_no_puede_modificar(): void
    {
        $socio = $this->crearSocio();

        $this->expectException(\TypeError::class);

        // Sin usuario autenticado ni parámetro User válido
        $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_REQUERIDO,
            duena: null // @phpstan-ignore-line
        );
    }

    /** 06 – Petición manipulada desde Livewire/HTTP es rechazada */
    public function test_06_peticion_manipulada_rechazada(): void
    {
        $socio = $this->crearSocio();

        $this->actingAs($this->recepcionista);

        // La recepcionista intenta forzar la ejecución del método en VerSocio
        Livewire::test(\App\Livewire\Socios\VerSocio::class, ['socio' => $socio])
            ->set('decision_medica_seleccionada', 'no_requerido')
            ->call('actualizarDecisionMedica')
            ->assertStatus(403);

        $socio->refresh();
        $this->assertEquals('pendiente', $socio->decision_medica);
    }

    /** 07 – Decisión pendiente no cumple el requisito médico (Caso A) */
    public function test_07_decision_pendiente_no_cumple_requisito(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'pendiente']);

        $eval = $this->service->evaluarRequisitoMedico($socio);

        $this->assertFalse($eval['cumple']);
        $this->assertEquals('A', $eval['caso']);
        $this->assertFalse($socio->cumpleRequisitoMedico());
    }

    /** 08 – No requerido cumple el requisito médico (Caso B) */
    public function test_08_no_requerido_cumple_requisito(): void
    {
        $socio = $this->crearSocio([
            'decision_medica' => 'no_requerido',
            'decision_medica_por' => $this->duena->id,
            'decision_medica_at' => now(),
        ]);

        $eval = $this->service->evaluarRequisitoMedico($socio);

        $this->assertTrue($eval['cumple']);
        $this->assertEquals('B', $eval['caso']);
        $this->assertTrue($socio->cumpleRequisitoMedico());
    }

    /** 09 – Requerido sin certificado no cumple (Caso C) */
    public function test_09_requerido_sin_certificado_no_cumple(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);

        $eval = $this->service->evaluarRequisitoMedico($socio);

        $this->assertFalse($eval['cumple']);
        $this->assertEquals('C', $eval['caso']);
        $this->assertFalse($socio->cumpleRequisitoMedico());
    }

    /** 10 – Requerido con certificado vigente cumple (Caso D) */
    public function test_10_requerido_con_certificado_vigente_cumple(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);

        $apto = AptoFisico::create([
            'socio_id' => $socio->id,
            'fecha_emision' => now()->subMonth()->toDateString(),
            'fecha_vencimiento' => now()->addMonths(11)->toDateString(),
            'estado' => AptoFisico::ESTADO_PENDIENTE_REVISION,
        ]);

        $pendiente = $this->service->evaluarRequisitoMedico($socio);
        $this->assertFalse($pendiente['cumple']);
        $this->assertEquals('F', $pendiente['caso']);

        $this->actingAs($this->duena);
        Livewire::test(\App\Livewire\AptosFisicos\EditarAptoFisico::class, ['aptoFisico' => $apto])
            ->set('estado', AptoFisico::ESTADO_VIGENTE)
            ->call('actualizar')
            ->assertHasNoErrors();

        $eval = $this->service->evaluarRequisitoMedico($socio);

        $this->assertTrue($eval['cumple']);
        $this->assertEquals('D', $eval['caso']);
        $this->assertSame(AptoFisico::ESTADO_VIGENTE, $apto->fresh()->estado);
        $this->assertTrue($socio->cumpleRequisitoMedico());
        $this->assertGreaterThan(0, $eval['dias_restantes']);
    }

    /** 11 – Requerido con certificado vencido no cumple (Caso E) */
    public function test_11_requerido_con_certificado_vencido_no_cumple(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);

        $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: now()->subYears(2)->toDateString(),
            fechaVencimiento: now()->subMonth()->toDateString(),
            operador: $this->duena
        );

        $eval = $this->service->evaluarRequisitoMedico($socio);

        $this->assertFalse($eval['cumple']);
        $this->assertEquals('E', $eval['caso']);
        $this->assertFalse($socio->cumpleRequisitoMedico());
    }

    /** 12 – Cambio posterior de 'no_requerido' a 'requerido' */
    public function test_12_cambio_posterior_no_requerido_a_requerido(): void
    {
        // Socio inicialmente exento
        $socio = $this->crearSocio(['decision_medica' => 'no_requerido']);
        $this->assertTrue($socio->cumpleRequisitoMedico());

        // La dueña cambia a requerido (ej: Juan)
        $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_REQUERIDO,
            duena: $this->duena,
            observacion: 'Cambio de disciplina a Kapap: certificado obligatorio'
        );

        $socio->refresh();
        $this->assertEquals('requerido', $socio->decision_medica);
        // Como no presentó certificado, ahora no cumple (Caso C)
        $this->assertFalse($socio->cumpleRequisitoMedico());
    }

    /** 13 – Cambio posterior de 'requerido' a 'no_requerido' */
    public function test_13_cambio_posterior_requerido_a_no_requerido(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);

        // Registrar certificado histórico previo
        $apto = $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: now()->subMonths(6)->toDateString(),
            fechaVencimiento: now()->addMonths(6)->toDateString(),
            operador: $this->duena
        );

        // Dueña cambia a no requerido
        $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_NO_REQUERIDO,
            duena: $this->duena,
            observacion: 'Pase social sin actividad física de contacto'
        );

        $socio->refresh();
        $this->assertEquals('no_requerido', $socio->decision_medica);
        $this->assertTrue($socio->cumpleRequisitoMedico());

        // El certificado histórico se conserva intacto
        $this->assertDatabaseHas('aptos_fisicos', ['id' => $apto->id]);
    }

    /** 14 – Conservación del PIN ante cambio médico */
    public function test_14_conservacion_pin_ante_cambio_medico(): void
    {
        $socio = $this->crearSocio([
            'pin' => '654321',
            'qr_token' => 'qr_token_original_123',
            'estado_credencial' => 'activo',
            'decision_medica' => 'no_requerido',
        ]);

        $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_REQUERIDO,
            duena: $this->duena
        );

        $socio->refresh();
        $this->assertEquals('654321', $socio->pin, 'El PIN no debe alterarse ante cambios de decisión médica');
    }

    /** 15 – Conservación del QR ante cambio médico */
    public function test_15_conservacion_qr_ante_cambio_medico(): void
    {
        $socio = $this->crearSocio([
            'pin' => '654321',
            'qr_token' => 'qr_token_original_fijo_456',
            'estado_credencial' => 'activo',
            'decision_medica' => 'no_requerido',
        ]);

        $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_REQUERIDO,
            duena: $this->duena
        );

        $socio->refresh();
        $this->assertEquals('qr_token_original_fijo_456', $socio->qr_token, 'El QR token no debe regenerarse');
    }

    /** 16 – Solo la dueña puede registrar documentación médica */
    public function test_16_recepcion_no_puede_registrar_documentacion_medica(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);

        $this->actingAs($this->recepcionista);
        $this->assertFalse($this->recepcionista->can('create', AptoFisico::class));

        $archivo = UploadedFile::fake()->create('certificado.pdf', 500, 'application/pdf');

        try {
            $this->service->registrarCertificado(
                socio: $socio,
                fechaEmision: today()->toDateString(),
                fechaVencimiento: today()->addYear()->toDateString(),
                operador: $this->recepcionista,
                archivo: $archivo
            );
            $this->fail('Recepción no debe registrar documentos médicos.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertDatabaseCount('aptos_fisicos', 0);
        }

        $this->get(route('aptos-fisicos.create', $socio))->assertForbidden();
    }

    /** 17 – Conservación del historial de certificados */
    public function test_17_conservacion_historial_certificados(): void
    {
        $socio = $this->crearSocio();

        $apto1 = $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: '2025-01-01',
            fechaVencimiento: '2026-01-01',
            operador: $this->duena
        );

        $apto2 = $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: '2026-01-02',
            fechaVencimiento: '2027-01-02',
            operador: $this->duena
        );

        $historial = $this->service->obtenerHistorialCertificados($socio);

        $this->assertCount(2, $historial);
        $this->assertEquals($apto2->id, $historial->first()->id);
        $this->assertEquals($apto1->id, $historial->last()->id);
    }

    /** 18 – Auditoría de decisiones y registros médicos */
    public function test_18_auditoria_decisiones_y_registros(): void
    {
        $socio = $this->crearSocio();

        // 1. Decisión de dueña auditada
        $this->service->definirDecision(
            socio: $socio,
            nuevaDecision: CertificadoMedicoService::ESTADO_REQUERIDO,
            duena: $this->duena,
            observacion: 'Prueba de auditoria'
        );

        $this->assertDatabaseHas('auditorias', [
            'tipo_evento' => 'medico',
            'accion'      => 'decision_medica_inicial',
            'user_id'     => $this->duena->id,
        ]);

        $auditoriaDecision = Auditoria::where('accion', 'decision_medica_inicial')->firstOrFail();
        $datosDecision = json_encode([$auditoriaDecision->datos_antes, $auditoriaDecision->datos_despues]);
        $this->assertStringNotContainsString('Prueba de auditoria', $datosDecision);
        $this->assertArrayNotHasKey('decision_medica_obs', $auditoriaDecision->datos_despues);

        // 2. Registro de certificado auditado
        $archivoAuditoria = UploadedFile::fake()->create('archivo-clinico-no-auditar.pdf', 300, 'application/pdf');
        $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: '2026-11-01',
            fechaVencimiento: '2027-11-01',
            operador: $this->duena,
            archivo: $archivoAuditoria
        );

        $this->assertDatabaseHas('auditorias', [
            'tipo_evento' => 'medico',
            'accion'      => 'certificado_medico_registrado',
            'user_id'     => $this->duena->id,
        ]);
        $auditoriaCertificado = Auditoria::where('accion', 'certificado_medico_registrado')->firstOrFail();
        $datosCertificado = json_encode([$auditoriaCertificado->datos_antes, $auditoriaCertificado->datos_despues]);
        $this->assertStringNotContainsString('archivo-clinico-no-auditar.pdf', $datosCertificado);
        $this->assertStringNotContainsString('secure/aptos_fisicos/', $datosCertificado);
        $this->assertArrayNotHasKey('archivo', $auditoriaCertificado->datos_despues);
    }

    /** 19 – Archivos médicos protegidos en almacenamiento privado */
    public function test_19_archivos_medicos_protegidos(): void
    {
        $socio = $this->crearSocio();
        $archivo = UploadedFile::fake()->create('privado.pdf', 300, 'application/pdf');

        $apto = $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: '2026-11-01',
            fechaVencimiento: '2027-11-01',
            operador: $this->duena,
            archivo: $archivo
        );

        // La ruta no debe estar en carpeta pública (public/)
        $this->assertStringStartsWith('secure/aptos_fisicos/', $apto->archivo);

        // Huésped no autenticado no puede descargar
        $this->get(route('aptos-fisicos.download', $apto->id))->assertRedirect(route('login'));

        // Recepción no puede ver ni descargar documentación médica privada.
        $this->actingAs($this->recepcionista);
        $this->get(route('aptos-fisicos.download', $apto->id))->assertForbidden();

        // La dueña conserva acceso a la descarga autorizada.
        $this->actingAs($this->duena);
        $this->get(route('aptos-fisicos.download', $apto->id))->assertOk();
    }

    /** 20 – No emisión automática de credenciales al registrar documentos */
    public function test_20_no_emision_automatica_credenciales_al_registrar_documentos(): void
    {
        $socio = $this->crearSocio([
            'pin' => null,
            'qr_token' => null,
            'estado_credencial' => 'sin_emitir',
            'decision_medica' => 'requerido',
        ]);

        $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: '2026-11-01',
            fechaVencimiento: '2027-11-01',
            operador: $this->duena
        );

        $socio->refresh();
        $this->assertNull($socio->pin);
        $this->assertNull($socio->qr_token);
        $this->assertEquals('sin_emitir', $socio->estado_credencial);
    }

    /** 21 – No aprobación automática de inscripciones */
    public function test_21_no_aprobacion_automatica_inscripciones(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'pendiente']);

        $disciplina = Disciplina::create(['nombre' => 'JiuJitsu', 'tarifa_mensual' => 30000]);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => 'Grupo A',
            'capacidad_maxima' => 15,
            'nivel' => 'todos',
            'activo' => true
        ]);

        $insc = Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => Inscripcion::ESTADO_PENDIENTE,
        ]);

        // Dueña marca 'no_requerido'
        $this->service->definirDecision($socio, 'no_requerido', $this->duena);

        // La inscripción DEBE seguir pendiente
        $insc->refresh();
        $this->assertEquals(Inscripcion::ESTADO_PENDIENTE, $insc->estado);
    }

    /** 22 – No modificación automática de pagos */
    public function test_22_no_modificacion_automatica_pagos(): void
    {
        $socio = $this->crearSocio();

        $pago = Pago::create([
            'socio_id'    => $socio->id,
            'fecha_pago'  => now()->toDateString(),
            'importe'     => 30000,
            'metodo_pago' => 'efectivo',
            'estado'      => Pago::ESTADO_ACTIVO,
        ]);

        $this->service->definirDecision($socio, 'no_requerido', $this->duena);

        $pago->refresh();
        $this->assertEquals(30000, $pago->importe);
        $this->assertEquals('activo', $pago->estado);
    }

    /** 23 – Compatibilidad con datos existentes (apto físico legacy) */
    public function test_23_compatibilidad_datos_existentes(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);

        // Apto físico creado directamente en base (modelo original legacy)
        $aptoLegacy = AptoFisico::create([
            'socio_id'          => $socio->id,
            'archivo'           => null,
            'fecha_emision'     => now()->subMonths(2)->toDateString(),
            'fecha_vencimiento' => now()->addMonths(10)->toDateString(),
            'estado'            => 'vigente',
        ]);

        $eval = $this->service->evaluarRequisitoMedico($socio);

        $this->assertTrue($eval['cumple']);
        $this->assertEquals('D', $eval['caso']);
        $this->assertEquals($aptoLegacy->id, $eval['certificado']->id);
    }

    /** 24 – Notificación/solicitud de recepción a dueña */
    public function test_24_notificacion_recepcion_a_duena(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'pendiente']);

        $solicitud = $this->service->informarRevisionADuena(
            socio: $socio,
            recepcionista: $this->recepcionista
        );

        $this->assertEquals(Solicitud::TIPO_EXCEPCION_MEDICA, $solicitud->tipo);
        $this->assertEquals(Solicitud::ESTADO_PENDIENTE, $solicitud->estado);
        $this->assertEquals($this->recepcionista->id, $solicitud->solicitante_id);
        $this->assertSame('Revisión administrativa solicitada a la dueña.', $solicitud->descripcion);
    }

    public function test_vencimiento_de_hoy_es_vigente_hasta_el_final_del_dia(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);

        $certificado = $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: today()->subMonth()->toDateString(),
            fechaVencimiento: today()->toDateString(),
            operador: $this->duena
        );

        $this->assertSame('vigente', $certificado->estado);
        $this->assertTrue($this->service->evaluarRequisitoMedico($socio)['cumple']);
        $this->assertTrue($socio->fresh()->aptoFisicoVigente());
    }

    public function test_reception_cannot_approve_a_pending_certificate(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);
        $apto = AptoFisico::create([
            'socio_id' => $socio->id,
            'fecha_emision' => today()->subDay()->toDateString(),
            'fecha_vencimiento' => today()->addYear()->toDateString(),
            'estado' => AptoFisico::ESTADO_PENDIENTE_REVISION,
        ]);

        $this->actingAs($this->recepcionista);
        $this->get(route('aptos-fisicos.edit', $apto))->assertForbidden();
        $this->assertSame('F', $this->service->evaluarRequisitoMedico($socio)['caso']);
    }

    public function test_owner_rejection_does_not_satisfy_medical_requirement(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'requerido']);
        $apto = AptoFisico::create([
            'socio_id' => $socio->id,
            'fecha_emision' => today()->subDay()->toDateString(),
            'fecha_vencimiento' => today()->addYear()->toDateString(),
            'estado' => AptoFisico::ESTADO_PENDIENTE_REVISION,
        ]);

        $this->actingAs($this->duena);
        Livewire::test(\App\Livewire\AptosFisicos\EditarAptoFisico::class, ['aptoFisico' => $apto])
            ->set('estado', AptoFisico::ESTADO_RECHAZADO)
            ->call('actualizar')
            ->assertHasNoErrors();

        $this->assertSame('G', $this->service->evaluarRequisitoMedico($socio)['caso']);
        $this->assertFalse($socio->fresh()->cumpleRequisitoMedico());
    }

    public function test_usuario_sin_rol_no_puede_descargar_certificado(): void
    {
        $socio = $this->crearSocio();
        $certificado = $this->service->registrarCertificado(
            socio: $socio,
            fechaEmision: today()->toDateString(),
            fechaVencimiento: today()->addYear()->toDateString(),
            operador: $this->duena
        );
        $usuario = User::create([
            'name' => 'Usuario sin rol',
            'email' => 'sin-rol@fenix.test',
            'password' => bcrypt('password'),
            'rol' => 'invitado',
        ]);

        $this->actingAs($usuario)
            ->get(route('aptos-fisicos.download', $certificado->id))
            ->assertForbidden();
    }
}
