<?php

namespace App\Services;

use App\Events\GymRealtimeUpdate;
use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Auditoria;
use App\Models\Inscripcion;
use App\Models\Solicitud;
use App\Models\Socio;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SolicitudService
{
    public function crearCorreccion(
        string $recurso,
        int $registroId,
        User $solicitante,
        string $descripcion
    ): Solicitud {
        Gate::forUser($solicitante)->authorize('create', Solicitud::class);
        $validated = validator(
            compact('descripcion'),
            ['descripcion' => ['required', 'string', 'min:10', 'max:1000']]
        )->validate();
        $registro = match ($recurso) {
            'permanencia' => Asistencia::query()->findOrFail($registroId),
            'clase' => AsistenciaClase::query()->findOrFail($registroId),
            default => throw new DomainException('El tipo de registro solicitado no es válido.'),
        };
        $socio = $registro->socio;

        return DB::transaction(function () use ($recurso, $registroId, $solicitante, $validated, $socio) {
            $solicitud = Solicitud::create([
                'socio_id' => $socio->id,
                'solicitante_id' => $solicitante->id,
                'tipo' => Solicitud::TIPO_OTRO,
                'datos_solicitud' => ['recurso' => $recurso, 'registro_id' => $registroId],
                'descripcion' => 'Solicitud de corrección de ' . $recurso . ': ' . trim($validated['descripcion']),
                'estado' => Solicitud::ESTADO_PENDIENTE,
            ]);

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'solicitud_correccion_creada',
                entidad: $solicitud,
                datosDespues: [
                    'socio_id' => $socio->id,
                    'tipo_registro' => $recurso,
                    'registro_id' => $registroId,
                    'estado' => Solicitud::ESTADO_PENDIENTE,
                ],
                userId: $solicitante->id
            );

            event(GymRealtimeUpdate::request($solicitud, 'created'));

            return $solicitud;
        });
    }

    /**
     * Resuelve una solicitud una sola vez; sus cambios funcionales y auditorías
     * quedan en la misma transacción.
     *
     * @param array<string, mixed>|null $correccion
     */
    public function resolver(
        Solicitud $solicitud,
        User $duena,
        string $decision,
        string $respuesta,
        ?string $decisionMedica = null,
        ?array $correccion = null
    ): Solicitud {
        Gate::forUser($duena)->authorize('resolver', $solicitud);

        if (!in_array($decision, [Solicitud::ESTADO_APROBADA, Solicitud::ESTADO_RECHAZADA], true)) {
            throw new DomainException('La decisión de solicitud no es válida.');
        }

        $respuesta = trim($respuesta);
        if ($respuesta === '') {
            throw new DomainException('Debe indicar una observación para resolver la solicitud.');
        }

        return DB::transaction(function () use ($solicitud, $duena, $decision, $respuesta, $decisionMedica, $correccion) {
            $actual = Solicitud::query()->lockForUpdate()->findOrFail($solicitud->id);
            if (!$actual->estaPendiente()) {
                throw new DomainException('La solicitud ya fue resuelta y no puede modificarse.');
            }

            if ($decision === Solicitud::ESTADO_APROBADA) {
                $this->aplicarDecision($actual, $duena, $respuesta, $decisionMedica, $correccion);
            }

            if ($actual->fresh()->estado === Solicitud::ESTADO_PENDIENTE) {
                $actual->update([
                    'estado' => $decision,
                    'revisado_por' => $duena->id,
                    'respuesta_duena' => $respuesta,
                    'fecha_decision' => now(),
                ]);
            } elseif ($actual->fresh()->estado !== $decision) {
                throw new DomainException('La operación funcional produjo un estado de solicitud inesperado.');
            }

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: $decision === Solicitud::ESTADO_APROBADA ? 'solicitud_aprobada' : 'solicitud_rechazada',
                entidad: $actual,
                datosAntes: ['estado' => Solicitud::ESTADO_PENDIENTE],
                datosDespues: [
                    'tipo' => $actual->tipo,
                    'estado' => $decision,
                    'revisado_por' => $duena->id,
                ],
                userId: $duena->id
            );

            $resuelta = $actual->fresh();
            event(GymRealtimeUpdate::request($resuelta, 'resolved'));

            return $resuelta;
        }, 3);
    }

    /**
     * @param array<string, mixed>|null $correccion
     */
    private function aplicarDecision(
        Solicitud $solicitud,
        User $duena,
        string $respuesta,
        ?string $decisionMedica,
        ?array $correccion
    ): void {
        $inscripciones = app(InscripcionService::class);

        switch ($solicitud->tipo) {
            case Solicitud::TIPO_INSCRIPCION:
                $inscripcion = $this->inscripcionDe($solicitud);
                $inscripciones->aprobarInscripcion($inscripcion, $duena, $respuesta, $solicitud);
                return;

            case Solicitud::TIPO_BAJA_INSCRIPCION:
                $inscripciones->registrarBaja(
                    $this->inscripcionDe($solicitud),
                    $duena,
                    $respuesta,
                    true,
                    $solicitud
                );
                return;

            case Solicitud::TIPO_CAMBIO_GRUPO:
                if (!$solicitud->grupoDestino) {
                    throw new DomainException('La solicitud no tiene un grupo de destino válido.');
                }
                $inscripciones->ejecutarCambioGrupo(
                    $this->inscripcionDe($solicitud),
                    $solicitud->grupoDestino,
                    $duena,
                    $respuesta,
                    $solicitud
                );
                return;

            case Solicitud::TIPO_EXCEPCION_MEDICA:
                if (!in_array($decisionMedica, [
                    CertificadoMedicoService::ESTADO_REQUERIDO,
                    CertificadoMedicoService::ESTADO_NO_REQUERIDO,
                ], true)) {
                    throw new DomainException('Seleccione la decisión médica que debe aplicar la dueña.');
                }
                app(CertificadoMedicoService::class)->definirDecision(
                    $solicitud->socio,
                    $decisionMedica,
                    $duena,
                    $respuesta
                );
                return;

            case Solicitud::TIPO_OTRO:
                $this->aplicarCorreccion($solicitud, $duena, $correccion);
                return;
        }

        throw new DomainException('Este tipo de solicitud no puede aprobarse desde la bandeja.');
    }

    private function inscripcionDe(Solicitud $solicitud): Inscripcion
    {
        if (!$solicitud->inscripcion_id) {
            throw new DomainException('La solicitud no tiene una inscripción vigente asociada.');
        }

        $inscripcion = Inscripcion::query()->findOrFail($solicitud->inscripcion_id);
        if ($inscripcion->socio_id !== $solicitud->socio_id
            || ($solicitud->grupo_id && $inscripcion->grupo_id !== $solicitud->grupo_id)) {
            throw new DomainException('La inscripción asociada no coincide con la solicitud.');
        }

        return $inscripcion;
    }

    /**
     * @param array<string, mixed>|null $correccion
     */
    private function aplicarCorreccion(Solicitud $solicitud, User $duena, ?array $correccion): void
    {
        $contexto = $solicitud->contextoCorreccion();
        if (!$contexto) {
            return;
        }

        if (!$correccion) {
            throw new DomainException('Complete los datos de la corrección antes de aprobarla.');
        }

        if ($contexto['recurso'] === 'permanencia') {
            $registro = Asistencia::query()->findOrFail($contexto['registro_id']);
            if ($registro->socio_id !== $solicitud->socio_id) {
                throw new DomainException('La permanencia no corresponde al socio de la solicitud.');
            }
            validator($correccion, [
                'fecha_ingreso' => ['required', 'date'],
                'hora_ingreso' => ['required', 'date_format:H:i:s'],
                'fecha_salida' => ['nullable', 'required_with:hora_salida', 'date'],
                'hora_salida' => ['nullable', 'required_with:fecha_salida', 'date_format:H:i:s'],
            ])->validate();

            app(ControlAccesoService::class)->corregirPermanencia(
                $registro,
                $correccion['fecha_ingreso'],
                $correccion['hora_ingreso'],
                $correccion['fecha_salida'] ?: null,
                $correccion['hora_salida'] ?: null,
                $duena
            );
            return;
        }

        if ($contexto['recurso'] === 'clase') {
            $registro = AsistenciaClase::query()->findOrFail($contexto['registro_id']);
            if ($registro->socio_id !== $solicitud->socio_id) {
                throw new DomainException('La asistencia no corresponde al socio de la solicitud.');
            }
            validator($correccion, [
                'estado' => ['required', 'in:puntual,tarde,pendiente,ausencia_confirmada,ausente_justificado,cancelado'],
                'hora_marcado' => ['nullable', 'date_format:H:i:s'],
            ])->validate();

            app(AsistenciaClaseService::class)->corregirAsistencia(
                $registro,
                $correccion['estado'],
                $correccion['hora_marcado'] ?: null,
                $duena
            );
            return;
        }

        throw new DomainException('El tipo de corrección solicitado no es válido.');
    }
}
