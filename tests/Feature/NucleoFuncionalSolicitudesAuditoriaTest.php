<?php

namespace Tests\Feature;

use App\Livewire\Auditoria\PanelAuditoria;
use App\Livewire\Solicitudes\GestionarSolicitudes;
use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Solicitud;
use App\Models\Socio;
use App\Models\User;
use App\Services\CertificadoMedicoService;
use App\Services\InscripcionService;
use App\Services\SolicitudService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class NucleoFuncionalSolicitudesAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $duena;
    private User $recepcionista;
    private Membresia $membresia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duena = User::factory()->create(['rol' => 'admin']);
        $this->recepcionista = User::factory()->create(['rol' => 'recepcionista']);
        $this->membresia = Membresia::create([
            'nombre' => 'Membresía fase once',
            'precio' => 100,
            'duracion_dias' => 30,
            'estado' => 'activo',
        ]);
    }

    public function test_recepcion_crea_solicitud_medica_pendiente_sin_cambiar_el_acceso(): void
    {
        $socio = $this->crearSocio();

        $this->actingAs($this->recepcionista);
        Livewire::test(GestionarSolicitudes::class)
            ->set('socioId', (string) $socio->id)
            ->set('motivo', 'Solicita revisar su requisito')
            ->call('crearRevisionMedica')
            ->assertHasNoErrors();

        $solicitud = Solicitud::query()->sole();
        $this->assertSame(Solicitud::ESTADO_PENDIENTE, $solicitud->estado);
        $this->assertSame($this->recepcionista->id, $solicitud->solicitante_id);
        $this->assertSame('pendiente', $socio->fresh()->decision_medica);
        $this->assertDatabaseHas('auditorias', [
            'auditable_type' => Solicitud::class,
            'auditable_id' => $solicitud->id,
            'accion' => 'solicitud_medica_creada',
        ]);
    }

    public function test_recepcion_no_puede_resolver_ni_modificar_solicitudes_por_peticion_manipulada(): void
    {
        $solicitud = $this->crearSolicitudMedica($this->crearSocio());

        $this->actingAs($this->recepcionista);
        $this->get(route('solicitudes.index'))->assertOk();
        $this->get(route('auditoria.index'))->assertForbidden();

        try {
            app(SolicitudService::class)->resolver(
                $solicitud,
                $this->recepcionista,
                Solicitud::ESTADO_APROBADA,
                'Resolución no autorizada',
                'no_requerido'
            );
            $this->fail('Recepción no debe resolver solicitudes.');
        } catch (AuthorizationException) {
            $this->assertSame(Solicitud::ESTADO_PENDIENTE, $solicitud->fresh()->estado);
        }
    }

    public function test_duena_puede_rechazar_y_se_conserva_el_motivo_y_el_historial(): void
    {
        $solicitud = $this->crearSolicitudMedica($this->crearSocio());

        $resuelta = app(SolicitudService::class)->resolver(
            $solicitud,
            $this->duena,
            Solicitud::ESTADO_RECHAZADA,
            'Se requiere documentación adicional.'
        );

        $this->assertSame(Solicitud::ESTADO_RECHAZADA, $resuelta->estado);
        $this->assertSame('Se requiere documentación adicional.', $resuelta->respuesta_duena);
        $this->assertSame($this->duena->id, $resuelta->revisado_por);
        $this->assertNotNull($resuelta->fecha_decision);
        $this->assertSame('pendiente', $resuelta->socio->decision_medica);
        $this->assertDatabaseHas('auditorias', [
            'auditable_type' => Solicitud::class,
            'auditable_id' => $solicitud->id,
            'accion' => 'solicitud_rechazada',
        ]);
    }

    public function test_solicitud_resuelta_no_puede_editarse_ni_eliminarse(): void
    {
        $solicitud = $this->crearSolicitudMedica($this->crearSocio());
        $solicitud->update([
            'estado' => Solicitud::ESTADO_RECHAZADA,
            'revisado_por' => $this->duena->id,
            'respuesta_duena' => 'No autorizada.',
            'fecha_decision' => now(),
        ]);
        $resuelta = $solicitud->fresh();

        try {
            $resuelta->update(['respuesta_duena' => 'Modificación posterior.']);
            $this->fail('No deben alterarse solicitudes resueltas.');
        } catch (LogicException) {
            $this->assertSame('No autorizada.', $resuelta->fresh()->respuesta_duena);
        }

        $this->expectException(LogicException::class);
        $resuelta->delete();
    }

    public function test_solicitud_medica_aprobada_aplica_decision_explicita_sin_regenerar_credenciales(): void
    {
        $socio = $this->crearSocio([
            'decision_medica' => 'pendiente',
            'estado_credencial' => 'activo',
            'pin' => '485120',
            'qr_token' => str_repeat('a', 64),
        ]);
        $solicitud = $this->crearSolicitudMedica($socio);

        $resuelta = app(SolicitudService::class)->resolver(
            $solicitud,
            $this->duena,
            Solicitud::ESTADO_APROBADA,
            'La dueña confirmó que debe presentar certificado.',
            'requerido'
        );

        $this->assertSame(Solicitud::ESTADO_APROBADA, $resuelta->estado);
        $this->assertSame('requerido', $socio->fresh()->decision_medica);
        $this->assertSame('485120', $socio->fresh()->pin);
        $this->assertSame(str_repeat('a', 64), $socio->fresh()->qr_token);
        $this->assertDatabaseHas('auditorias', [
            'auditable_type' => Socio::class,
            'auditable_id' => $socio->id,
            'accion' => 'decision_medica_inicial',
        ]);
    }

    public function test_solicitud_de_inscripcion_se_aprueba_una_sola_vez_y_aplica_cupo(): void
    {
        $socio = $this->crearSocio();
        $grupo = $this->crearGrupo('Funcional', 1);
        $resultado = app(InscripcionService::class)->solicitarInscripcion(
            $socio,
            $grupo,
            $this->recepcionista,
            'Inscripción solicitada'
        );
        $solicitud = $resultado['solicitud'];

        $this->assertSame(Inscripcion::ESTADO_PENDIENTE, $resultado['modelo']->fresh()->estado);
        app(SolicitudService::class)->resolver(
            $solicitud,
            $this->duena,
            Solicitud::ESTADO_APROBADA,
            'Cupo confirmado.'
        );

        $this->assertSame(Inscripcion::ESTADO_APROBADA, $resultado['modelo']->fresh()->estado);
        $this->assertSame(Solicitud::ESTADO_APROBADA, $solicitud->fresh()->estado);
        $this->assertDatabaseHas('auditorias', [
            'auditable_type' => Solicitud::class,
            'auditable_id' => $solicitud->id,
            'accion' => 'solicitud_aprobada',
        ]);

        $this->expectException(DomainException::class);
        app(SolicitudService::class)->resolver(
            $solicitud,
            $this->duena,
            Solicitud::ESTADO_APROBADA,
            'Intento duplicado.'
        );
    }

    public function test_baja_aprobada_conserva_la_inscripcion_historica(): void
    {
        $socio = $this->crearSocio();
        $inscripcion = $this->crearInscripcion($socio, $this->crearGrupo('Kickboxing', 5));
        $resultado = app(InscripcionService::class)->registrarBaja(
            $inscripcion,
            $this->recepcionista,
            'Solicitud de baja'
        );

        $this->assertSame(Inscripcion::ESTADO_APROBADA, $inscripcion->fresh()->estado);
        app(SolicitudService::class)->resolver(
            $resultado['solicitud'],
            $this->duena,
            Solicitud::ESTADO_APROBADA,
            'Baja autorizada.'
        );

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => Inscripcion::ESTADO_BAJA,
        ]);
        $this->assertDatabaseHas('solicitudes', [
            'id' => $resultado['solicitud']->id,
            'estado' => Solicitud::ESTADO_APROBADA,
        ]);
    }

    public function test_cambio_de_grupo_rechaza_destino_sin_cupo_y_no_resuelve_la_solicitud(): void
    {
        $socio = $this->crearSocio();
        $grupoOrigen = $this->crearGrupo('Origen', 5);
        $grupoDestino = $this->crearGrupo('Destino', 1);
        $inscripcion = $this->crearInscripcion($socio, $grupoOrigen);
        $otroSocio = $this->crearSocio();
        $this->crearInscripcion($otroSocio, $grupoDestino);

        $resultado = app(InscripcionService::class)->solicitarCambioGrupo(
            $inscripcion,
            $grupoDestino,
            $this->recepcionista,
            'Cambio solicitado'
        );

        try {
            app(SolicitudService::class)->resolver(
                $resultado['solicitud'],
                $this->duena,
                Solicitud::ESTADO_APROBADA,
                'Cambio autorizado.'
            );
            $this->fail('No debe superar el cupo del destino.');
        } catch (DomainException) {
            $this->assertSame(Solicitud::ESTADO_PENDIENTE, $resultado['solicitud']->fresh()->estado);
            $this->assertSame(Inscripcion::ESTADO_APROBADA, $inscripcion->fresh()->estado);
        }
    }

    public function test_cambio_de_grupo_aprobado_mueve_inscripcion_y_conserva_origen(): void
    {
        $socio = $this->crearSocio();
        $grupoOrigen = $this->crearGrupo('Grupo anterior', 5);
        $grupoDestino = $this->crearGrupo('Grupo nuevo', 5);
        $inscripcion = $this->crearInscripcion($socio, $grupoOrigen);
        $resultado = app(InscripcionService::class)->solicitarCambioGrupo(
            $inscripcion,
            $grupoDestino,
            $this->recepcionista,
            'Cambio de horario solicitado'
        );

        app(SolicitudService::class)->resolver(
            $resultado['solicitud'],
            $this->duena,
            Solicitud::ESTADO_APROBADA,
            'Cambio autorizado.'
        );

        $this->assertSame(Inscripcion::ESTADO_BAJA, $inscripcion->fresh()->estado);
        $this->assertDatabaseHas('inscripciones', [
            'socio_id' => $socio->id,
            'grupo_id' => $grupoDestino->id,
            'estado' => Inscripcion::ESTADO_APROBADA,
        ]);
        $this->assertSame(Solicitud::ESTADO_APROBADA, $resultado['solicitud']->fresh()->estado);
    }

    public function test_correccion_solicitada_no_cambia_registro_hasta_aprobacion_auditada(): void
    {
        $socio = $this->crearSocio();
        $asistencia = Asistencia::create([
            'socio_id' => $socio->id,
            'fecha' => now()->toDateString(),
            'hora_ingreso' => '18:00:00',
        ]);
        $solicitud = app(SolicitudService::class)->crearCorreccion(
            'permanencia',
            $asistencia->id,
            $this->recepcionista,
            'El horario de ingreso fue registrado incorrectamente'
        );

        $this->assertSame('18:00:00', $asistencia->fresh()->hora_ingreso);
        $this->assertSame(Solicitud::ESTADO_PENDIENTE, $solicitud->estado);

        app(SolicitudService::class)->resolver(
            $solicitud,
            $this->duena,
            Solicitud::ESTADO_APROBADA,
            'Se verificó el registro y se corrigió.',
            null,
            [
                'fecha_ingreso' => now()->toDateString(),
                'hora_ingreso' => '18:15:00',
                'fecha_salida' => null,
                'hora_salida' => null,
            ]
        );

        $this->assertSame('18:15:00', $asistencia->fresh()->hora_ingreso);
        $this->assertSame(Solicitud::ESTADO_APROBADA, $solicitud->fresh()->estado);
        $this->assertDatabaseHas('auditorias', [
            'auditable_type' => Asistencia::class,
            'auditable_id' => $asistencia->id,
            'accion' => 'permanencia_corregida',
        ]);
    }

    public function test_solicitud_no_puede_corregir_un_registro_de_otro_socio(): void
    {
        $socio = $this->crearSocio();
        $otroSocio = $this->crearSocio();
        $asistencia = Asistencia::create([
            'socio_id' => $otroSocio->id,
            'fecha' => now()->toDateString(),
            'hora_ingreso' => '18:00:00',
        ]);
        $solicitud = $this->crearSolicitudMedica($socio);
        $solicitud->update([
            'tipo' => Solicitud::TIPO_OTRO,
            'datos_solicitud' => ['recurso' => 'permanencia', 'registro_id' => $asistencia->id],
        ]);

        $this->expectException(DomainException::class);
        app(SolicitudService::class)->resolver(
            $solicitud,
            $this->duena,
            Solicitud::ESTADO_APROBADA,
            'No corresponde.',
            null,
            [
                'fecha_ingreso' => now()->toDateString(),
                'hora_ingreso' => '18:15:00',
                'fecha_salida' => null,
                'hora_salida' => null,
            ]
        );
    }

    public function test_auditoria_sanitiza_claves_sensibles_nidadas_y_es_inmutable(): void
    {
        $evento = Auditoria::registrar(
            'seguridad',
            'prueba_sanitizacion',
            null,
            ['anidado' => ['pin' => '111111', 'qr_token' => 'secreto-qr', 'diagnostico' => 'privado']],
            ['valor' => 'visible'],
            $this->duena->id
        );

        $datos = json_encode([$evento->datos_antes, $evento->datos_despues], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('111111', $datos);
        $this->assertStringNotContainsString('secreto-qr', $datos);
        $this->assertStringNotContainsString('privado', $datos);

        $this->expectException(LogicException::class);
        $evento->update(['accion' => 'alterada']);
    }

    public function test_panel_de_auditoria_filtra_por_socio_y_pagina_eventos(): void
    {
        $socio = $this->crearSocio(['dni' => '49999111']);
        $otroSocio = $this->crearSocio(['dni' => '49999112']);
        Auditoria::registrar('acceso', 'acceso_autorizado', $socio, null, ['resultado' => 'autorizado'], $this->duena->id);
        Auditoria::registrar('acceso', 'acceso_denegado', $otroSocio, null, ['resultado' => 'cuota_vencida'], $this->duena->id);
        for ($i = 0; $i < 30; $i++) {
            Auditoria::registrar('administrativo', 'evento_' . $i, $socio, null, null, $this->duena->id);
        }

        $this->actingAs($this->duena);
        $this->get(route('auditoria.index'))->assertOk();
        Livewire::test(PanelAuditoria::class)
            ->set('dniSocio', '49999111')
            ->set('resultado', 'autorizado')
            ->assertViewHas('eventos', function ($eventos) {
                return $eventos->total() === 1
                    && $eventos->first()->accion === 'acceso_autorizado';
            });

        Livewire::test(PanelAuditoria::class)
            ->assertViewHas('eventos', fn ($eventos) => $eventos->count() === 25 && $eventos->total() === 32);
    }

    public function test_recepcion_solo_ve_sus_ultimos_cincuenta_movimientos(): void
    {
        for ($i = 0; $i < 55; $i++) {
            Auditoria::registrar('administrativo', 'operacion_recepcion_' . $i, null, null, null, $this->recepcionista->id);
        }
        Auditoria::registrar('administrativo', 'operacion_otra', null, null, null, $this->duena->id);

        $this->actingAs($this->recepcionista);
        Livewire::test(GestionarSolicitudes::class)
            ->assertViewHas('movimientosRecepcion', fn ($movimientos) => $movimientos->count() === 50)
            ->assertDontSee('operacion_otra');
    }

    private function crearSolicitudMedica(Socio $socio): Solicitud
    {
        return app(CertificadoMedicoService::class)->informarRevisionADuena(
            $socio,
            $this->recepcionista,
            'Solicita revisión administrativa'
        );
    }

    private function crearSocio(array $atributos = []): Socio
    {
        static $dni = 40000000;
        $dni++;

        return Socio::create(array_merge([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Socio',
            'apellido' => 'Solicitudes',
            'dni' => (string) $dni,
            'sexo' => 'F',
            'token' => 'solicitud-' . $dni,
            'fecha_alta' => now()->toDateString(),
            'decision_medica' => 'pendiente',
            'estado_credencial' => 'sin_emitir',
            'estado' => 'activo',
        ], $atributos));
    }

    private function crearGrupo(string $nombre, int $cupo): Grupo
    {
        $disciplina = Disciplina::create(['nombre' => $nombre . ' ' . uniqid(), 'estado' => 'activo']);

        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => $nombre,
            'cupo_maximo' => $cupo,
            'estado' => 'activo',
        ]);

        GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => 'lunes',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
        ]);

        return $grupo;
    }

    private function crearInscripcion(Socio $socio, Grupo $grupo): Inscripcion
    {
        return Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => Inscripcion::ESTADO_APROBADA,
            'aprobado_por' => $this->duena->id,
            'aprobado_at' => now(),
        ]);
    }
}
