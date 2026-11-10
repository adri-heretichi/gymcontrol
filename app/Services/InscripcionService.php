<?php

namespace App\Services;

use App\Events\GymRealtimeUpdate;
use App\Models\Auditoria;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use DomainException;

class InscripcionService
{
    /**
     * Registra una solicitud de inscripción para un socio en un grupo.
     * Si el grupo tiene vacantes, genera una inscripción con estado 'pendiente' y su Solicitud.
     * Si el grupo está completo, registra al socio en la Lista de Espera del grupo.
     */
    public function solicitarInscripcion(
        Socio $socio,
        Grupo $grupo,
        User $solicitante,
        ?string $observaciones = null
    ): array {
        Gate::forUser($solicitante)->authorize('create', Solicitud::class);

        // 1. Validar el estado administrativo del socio
        if ($socio->estado !== 'activo') {
            throw new DomainException("El socio '{$socio->nombre_completo}' se encuentra inactivo.");
        }

        return DB::transaction(function () use ($socio, $grupo, $solicitante, $observaciones) {
            // Bloqueo de fila para verificación de cupo concurrente
            $grupoLocked = Grupo::where('id', $grupo->id)->lockForUpdate()->firstOrFail();
            $grupoLocked->load(['disciplina', 'horarios']);

            if ($grupoLocked->estado !== 'activo' || $grupoLocked->disciplina->estado !== 'activo') {
                throw new DomainException("El grupo o su disciplina se encuentran inactivos.");
            }

            if ($grupoLocked->horarios->isEmpty()) {
                throw new DomainException("El grupo '{$grupoLocked->nombre}' no tiene días y horarios configurados.");
            }

            $socio = Socio::findOrFail($socio->id);
            if ($socio->estado !== 'activo') {
                throw new DomainException("El socio '{$socio->nombre_completo}' se encuentra inactivo.");
            }

            $inscripcionExistente = Inscripcion::where('socio_id', $socio->id)
                ->where('grupo_id', $grupoLocked->id)
                ->lockForUpdate()
                ->first();

            if ($inscripcionExistente?->estaAprobada()) {
                throw new DomainException("El socio ya cuenta con una inscripción aprobada y vigente en el grupo '{$grupoLocked->nombre}'.");
            }

            if ($inscripcionExistente?->estaPendiente()) {
                throw new DomainException("El socio ya tiene una solicitud de inscripción pendiente de aprobación para el grupo '{$grupoLocked->nombre}'.");
            }

            $esperaExistente = ListaEspera::where('socio_id', $socio->id)
                ->where('grupo_id', $grupoLocked->id)
                ->where('estado', ListaEspera::ESTADO_ESPERANDO)
                ->lockForUpdate()
                ->first();

            if ($esperaExistente) {
                throw new DomainException("El socio ya se encuentra registrado en la lista de espera de este grupo (Posición #{$esperaExistente->posicion}).");
            }

            $conflicto = self::buscarConflictoHorarioSocio($socio, $grupoLocked);
            if ($conflicto) {
                $dia = ucfirst($conflicto['dia']);
                throw new DomainException(
                    "Incompatibilidad horaria: el socio ya cursa en '{$conflicto['grupo']->nombre}' el día {$dia} ({$conflicto['horario_actual']}). No puede superponerse con el nuevo horario ({$conflicto['horario_nuevo']})."
                );
            }

            $inscriptosActuales = Inscripcion::where('grupo_id', $grupoLocked->id)
                ->where('estado', Inscripcion::ESTADO_APROBADA)
                ->count();

            // Si el grupo ya está completo (15 o cupo_maximo alcanzado), va a Lista de Espera
            if ($inscriptosActuales >= $grupoLocked->limiteCupo()) {
                $ultimaPosicion = ListaEspera::where('grupo_id', $grupoLocked->id)
                    ->where('estado', ListaEspera::ESTADO_ESPERANDO)
                    ->max('posicion') ?? 0;

                $nuevaPosicion = $ultimaPosicion + 1;

                $espera = ListaEspera::create([
                    'socio_id' => $socio->id,
                    'grupo_id' => $grupoLocked->id,
                    'posicion' => $nuevaPosicion,
                    'fecha_solicitud' => now(),
                    'estado' => ListaEspera::ESTADO_ESPERANDO,
                    'observaciones' => $observaciones ?: 'Ingreso a lista de espera por cupo colmado.',
                ]);

                Auditoria::registrar(
                    'administrativo',
                    'registro_lista_espera',
                    $espera,
                    null,
                    $espera->toArray(),
                    $solicitante->id
                );

                return [
                    'tipo' => 'lista_espera',
                    'modelo' => $espera,
                    'mensaje' => "Grupo completo ({$inscriptosActuales}/{$grupoLocked->limiteCupo()}). Solicitud registrada en lista de espera (Posición #{$nuevaPosicion}).",
                ];
            }

            // Si hay cupo disponible, se genera la inscripción en estado 'pendiente'
            if ($inscripcionExistente) {
                // Reutilizar registro histórico existente (ej. previa baja o rechazo)
                $datosAntes = $inscripcionExistente->toArray();
                $inscripcionExistente->update([
                    'fecha_inscripcion' => now()->toDateString(),
                    'estado' => Inscripcion::ESTADO_PENDIENTE,
                    'aprobado_por' => null,
                    'aprobado_at' => null,
                    'fecha_baja' => null,
                    'motivo_baja' => null,
                    'baja_por' => null,
                    'motivo_rechazo' => null,
                    'rechazado_por' => null,
                    'rechazado_at' => null,
                ]);
                $inscripcion = $inscripcionExistente;
            } else {
                $datosAntes = null;
                $inscripcion = Inscripcion::create([
                    'socio_id' => $socio->id,
                    'grupo_id' => $grupoLocked->id,
                    'fecha_inscripcion' => now()->toDateString(),
                    'estado' => Inscripcion::ESTADO_PENDIENTE,
                ]);
            }

            // Crear la Solicitud formal para la bandeja de la dueña
            $solicitud = Solicitud::create([
                'socio_id' => $socio->id,
                'solicitante_id' => $solicitante->id,
                'tipo' => Solicitud::TIPO_INSCRIPCION,
                'inscripcion_id' => $inscripcion->id,
                'grupo_id' => $grupoLocked->id,
                'descripcion' => $observaciones ?: "Solicitud de inscripción al grupo {$grupoLocked->nombre} ({$grupoLocked->disciplina->nombre}).",
                'estado' => Solicitud::ESTADO_PENDIENTE,
            ]);

            Auditoria::registrar(
                'administrativo',
                'solicitud_inscripcion',
                $solicitud,
                $datosAntes ? ['inscripcion' => $datosAntes] : null,
                [
                    'socio_id' => $socio->id,
                    'inscripcion_id' => $inscripcion->id,
                    'grupo_id' => $grupoLocked->id,
                    'estado' => $solicitud->estado,
                    'inscripcion' => $inscripcion->toArray(),
                ],
                $solicitante->id
            );

            event(GymRealtimeUpdate::request($solicitud, 'created'));

            return [
                'tipo' => 'inscripcion_pendiente',
                'modelo' => $inscripcion,
                'solicitud' => $solicitud,
                'mensaje' => "Solicitud de inscripción registrada con éxito. Queda pendiente de aprobación por la dueña.",
            ];
        });
    }

    /**
     * Aprueba formalmente una inscripción pendiente.
     * Operación transaccional con bloqueo pesimista en MySQL (lockForUpdate)
     * para garantizar que jamás se sobrepase el límite máximo de cupo.
     */
    public function aprobarInscripcion(
        Inscripcion $inscripcion,
        User $duena,
        ?string $respuesta = null,
        ?Solicitud $solicitud = null
    ): Inscripcion {
        if (!$duena->esDuena()) {
            throw new AuthorizationException("Solo la dueña del gimnasio tiene permisos para aprobar inscripciones.");
        }

        return DB::transaction(function () use ($inscripcion, $duena, $respuesta, $solicitud) {
            if ($solicitud) {
                Solicitud::query()->whereKey($solicitud->id)
                    ->where('estado', Solicitud::ESTADO_PENDIENTE)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($solicitud->tipo !== Solicitud::TIPO_INSCRIPCION
                    || $solicitud->inscripcion_id !== $inscripcion->id) {
                    throw new DomainException('La solicitud no corresponde a esta inscripción.');
                }
            }
            // 1. Bloquear la fila del grupo para sincronizar concurrentes
            $grupo = Grupo::where('id', $inscripcion->grupo_id)->lockForUpdate()->firstOrFail();
            $grupo->load(['disciplina', 'horarios']);

            // 2. Bloquear y validar la inscripción antes de contabilizar el cupo.
            $inscripcionLocked = Inscripcion::where('id', $inscripcion->id)->lockForUpdate()->firstOrFail();
            if (!$inscripcionLocked->estaPendiente()) {
                throw new DomainException("Solo una inscripción pendiente puede aprobarse.");
            }

            if ($grupo->estado !== 'activo' || $grupo->disciplina->estado !== 'activo') {
                throw new DomainException('No se puede aprobar la inscripción porque el grupo o la disciplina están inactivos.');
            }

            if ($grupo->horarios->isEmpty()) {
                throw new DomainException("No se puede aprobar: el grupo '{$grupo->nombre}' no tiene horarios configurados.");
            }

            if ($inscripcionLocked->socio->estado !== 'activo') {
                throw new DomainException('No se puede aprobar la inscripción de un socio administrativamente inactivo.');
            }

            // 3. Recuento transaccional exacto de alumnos con inscripción aprobada
            $aprobadosCount = Inscripcion::where('grupo_id', $grupo->id)
                ->where('estado', Inscripcion::ESTADO_APROBADA)
                ->lockForUpdate()
                ->count();

            // 3. Control estricto de cupo: no permitir superar cupo_maximo (máx. 15)
            if ($aprobadosCount >= $grupo->limiteCupo()) {
                throw new DomainException(
                    "No se puede aprobar la inscripción: el grupo '{$grupo->nombre}' ha alcanzado su cupo máximo de {$grupo->limiteCupo()} alumnos."
                );
            }

            // 4. Validar compatibilidad horaria del socio (por si cambió otro grupo)
            $conflicto = self::buscarConflictoHorarioSocio($inscripcionLocked->socio, $grupo, $grupo->id);
            if ($conflicto) {
                throw new DomainException(
                    "No se puede aprobar: existe incompatibilidad horaria con el grupo '{$conflicto['grupo']->nombre}' el día " . ucfirst($conflicto['dia']) . "."
                );
            }

            // 5. Efectivizar la aprobación
            $datosAntes = $inscripcionLocked->toArray();

            $inscripcionLocked->update([
                'estado' => Inscripcion::ESTADO_APROBADA,
                'aprobado_por' => $duena->id,
                'aprobado_at' => now(),
            ]);

            // 7. Actualizar la Solicitud asociada si existe
            Solicitud::where('inscripcion_id', $inscripcionLocked->id)
                ->where('estado', Solicitud::ESTADO_PENDIENTE)
                ->when($solicitud, fn ($query) => $query->whereKey($solicitud->id))
                ->update([
                    'estado' => Solicitud::ESTADO_APROBADA,
                    'revisado_por' => $duena->id,
                    'respuesta_duena' => $respuesta ?: 'Inscripción aprobada por la dueña.',
                    'fecha_decision' => now(),
                ]);

            // 8. Si estaba en lista de espera para este grupo, marcarlo como promovido
            $espera = ListaEspera::where('socio_id', $inscripcionLocked->socio_id)
                ->where('grupo_id', $grupo->id)
                ->where('estado', ListaEspera::ESTADO_ESPERANDO)
                ->first();

            if ($espera) {
                $espera->update(['estado' => ListaEspera::ESTADO_PROMOVIDO]);
                self::reordenarListaEspera($grupo->id);
            }

            // 9. Registrar auditoría
            Auditoria::registrar(
                'administrativo',
                'aprobacion_inscripcion',
                $inscripcionLocked,
                $datosAntes,
                $inscripcionLocked->toArray(),
                $duena->id
            );

            if (!$solicitud) {
                event(GymRealtimeUpdate::enrollment($inscripcionLocked, 'approved'));
            }

            return $inscripcionLocked;
        });
    }

    /**
     * Rechaza una solicitud de inscripción con motivo obligatorio.
     */
    public function rechazarInscripcion(
        Inscripcion $inscripcion,
        User $duena,
        string $motivo,
        ?Solicitud $solicitud = null
    ): Inscripcion {
        if (!$duena->esDuena()) {
            throw new AuthorizationException("Solo la dueña del gimnasio tiene permisos para rechazar inscripciones.");
        }

        if (empty(trim($motivo))) {
            throw new DomainException("Debe especificar el motivo del rechazo de la inscripción.");
        }

        return DB::transaction(function () use ($inscripcion, $duena, $motivo, $solicitud) {
            if ($solicitud) {
                Solicitud::query()->whereKey($solicitud->id)
                    ->where('estado', Solicitud::ESTADO_PENDIENTE)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($solicitud->tipo !== Solicitud::TIPO_INSCRIPCION
                    || $solicitud->inscripcion_id !== $inscripcion->id) {
                    throw new DomainException('La solicitud no corresponde a esta inscripción.');
                }
            }

            $inscripcionLocked = Inscripcion::where('id', $inscripcion->id)->lockForUpdate()->firstOrFail();
            if (!$inscripcionLocked->estaPendiente()) {
                throw new DomainException('La inscripción ya fue resuelta.');
            }
            $datosAntes = $inscripcionLocked->toArray();

            $inscripcionLocked->update([
                'estado' => Inscripcion::ESTADO_RECHAZADA,
                'motivo_rechazo' => trim($motivo),
                'rechazado_por' => $duena->id,
                'rechazado_at' => now(),
            ]);

            Solicitud::where('inscripcion_id', $inscripcionLocked->id)
                ->where('estado', Solicitud::ESTADO_PENDIENTE)
                ->when($solicitud, fn ($query) => $query->whereKey($solicitud->id))
                ->update([
                    'estado' => Solicitud::ESTADO_RECHAZADA,
                    'revisado_por' => $duena->id,
                    'respuesta_duena' => trim($motivo),
                    'fecha_decision' => now(),
                ]);

            Auditoria::registrar(
                'administrativo',
                'rechazo_inscripcion',
                $inscripcionLocked,
                $datosAntes,
                $inscripcionLocked->toArray(),
                $duena->id
            );

            if (!$solicitud) {
                event(GymRealtimeUpdate::enrollment($inscripcionLocked, 'rejected'));
            }

            return $inscripcionLocked;
        });
    }

    /**
     * Registra o ejecuta la baja de un socio en un grupo.
     * - Si lo ejecuta la recepcionista: genera una Solicitud pendiente de baja.
     * - Si lo ejecuta la dueña: ejecuta la baja transaccional, libera cupo y notifica vacante.
     */
    public function registrarBaja(
        Inscripcion $inscripcion,
        User $usuario,
        string $motivo,
        bool $esAprobacionDirecta = false,
        ?Solicitud $solicitud = null
    ): array {
        if (empty(trim($motivo))) {
            throw new DomainException("Debe indicar el motivo de la baja.");
        }

        // Si la ejecuta recepción sin ser dueña, genera Solicitud pendiente
        if (!$usuario->esDuena() && !$usuario->esRecepcionista()) {
            throw new AuthorizationException('El usuario no puede gestionar solicitudes de baja.');
        }

        if (!$usuario->esDuena() && !$esAprobacionDirecta) {
            Gate::forUser($usuario)->authorize('create', Solicitud::class);
            if (!$inscripcion->estaAprobada()) {
                throw new DomainException('Solo se puede solicitar la baja de una inscripción aprobada.');
            }
            return DB::transaction(function () use ($inscripcion, $usuario, $motivo) {
                $solicitud = Solicitud::create([
                    'socio_id' => $inscripcion->socio_id,
                    'solicitante_id' => $usuario->id,
                    'tipo' => Solicitud::TIPO_BAJA_INSCRIPCION,
                    'inscripcion_id' => $inscripcion->id,
                    'grupo_id' => $inscripcion->grupo_id,
                    'descripcion' => 'Solicitud de baja: ' . trim($motivo),
                    'estado' => Solicitud::ESTADO_PENDIENTE,
                ]);

                Auditoria::registrar(
                    'administrativo',
                    'solicitud_baja_inscripcion',
                    $solicitud,
                    null,
                    ['socio_id' => $solicitud->socio_id, 'inscripcion_id' => $solicitud->inscripcion_id, 'estado' => $solicitud->estado],
                    $usuario->id
                );

                event(GymRealtimeUpdate::request($solicitud, 'created'));

                return [
                    'tipo' => 'solicitud_baja_pendiente',
                    'solicitud' => $solicitud,
                    'mensaje' => 'Solicitud de baja registrada. Requiere confirmación de la dueña.',
                ];
            });
        }

        // Ejecución efectiva de la baja por la Dueña
        return DB::transaction(function () use ($inscripcion, $usuario, $motivo, $solicitud) {
            if ($solicitud) {
                Solicitud::query()->whereKey($solicitud->id)
                    ->where('estado', Solicitud::ESTADO_PENDIENTE)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($solicitud->tipo !== Solicitud::TIPO_BAJA_INSCRIPCION
                    || $solicitud->inscripcion_id !== $inscripcion->id) {
                    throw new DomainException('La solicitud no corresponde a esta inscripción.');
                }
            }

            $inscripcionLocked = Inscripcion::where('id', $inscripcion->id)->lockForUpdate()->firstOrFail();
            if (!$inscripcionLocked->estaAprobada()) {
                throw new DomainException('Solo se puede dar de baja una inscripción aprobada.');
            }
            $grupoLocked = Grupo::where('id', $inscripcionLocked->grupo_id)->lockForUpdate()->firstOrFail();

            $datosAntes = $inscripcionLocked->toArray();

            $inscripcionLocked->update([
                'estado' => Inscripcion::ESTADO_BAJA,
                'fecha_baja' => now()->toDateString(),
                'motivo_baja' => trim($motivo),
                'baja_por' => $usuario->id,
            ]);

            // Actualizar solicitudes de baja pendientes asociadas
            Solicitud::where('inscripcion_id', $inscripcionLocked->id)
                ->where('tipo', Solicitud::TIPO_BAJA_INSCRIPCION)
                ->where('estado', Solicitud::ESTADO_PENDIENTE)
                ->when($solicitud, fn ($query) => $query->whereKey($solicitud->id))
                ->update([
                    'estado' => Solicitud::ESTADO_APROBADA,
                    'revisado_por' => $usuario->id,
                    'respuesta_duena' => 'Baja confirmada: ' . trim($motivo),
                    'fecha_decision' => now(),
                ]);

            // Comprobar si hay lista de espera para este grupo
            $siguienteEnEspera = ListaEspera::where('grupo_id', $grupoLocked->id)
                ->where('estado', ListaEspera::ESTADO_ESPERANDO)
                ->orderBy('posicion', 'asc')
                ->first();

            Auditoria::registrar(
                'administrativo',
                'baja_inscripcion',
                $inscripcionLocked,
                $datosAntes,
                $inscripcionLocked->toArray(),
                $usuario->id
            );

            if (!$solicitud) {
                event(GymRealtimeUpdate::enrollment($inscripcionLocked, 'cancelled'));
            }

            return [
                'tipo' => 'baja_efectivizada',
                'inscripcion' => $inscripcionLocked,
                'vacante_disponible' => true,
                'siguiente_espera' => $siguienteEnEspera,
                'mensaje' => "Baja efectivizada correctamente. Se liberó una vacante en el grupo '{$grupoLocked->nombre}'." .
                    ($siguienteEnEspera ? " Hay candidatos en lista de espera (Siguiente: {$siguienteEnEspera->socio->nombre_completo})." : ""),
            ];
        });
    }

    /**
     * Solicita o ejecuta un cambio de grupo de manera transaccional indivisible.
     */
    public function solicitarCambioGrupo(
        Inscripcion $inscripcionActual,
        Grupo $grupoDestino,
        User $usuario,
        ?string $motivo = null
    ): array {
        if ($inscripcionActual->grupo_id === $grupoDestino->id) {
            throw new DomainException("El grupo de destino es el mismo que el actual.");
        }

        if (!$inscripcionActual->estaAprobada()) {
            throw new DomainException('Solo se puede solicitar el cambio de una inscripción aprobada.');
        }

        $grupoDestino = Grupo::with(['disciplina', 'horarios'])->findOrFail($grupoDestino->id);
        if ($grupoDestino->estado !== 'activo' || $grupoDestino->disciplina->estado !== 'activo') {
            throw new DomainException("El grupo de destino '{$grupoDestino->nombre}' o su disciplina están inactivos.");
        }
        if ($grupoDestino->horarios->isEmpty()) {
            throw new DomainException("El grupo de destino '{$grupoDestino->nombre}' no tiene horarios configurados.");
        }

        if (!$usuario->esDuena() && !$usuario->esRecepcionista()) {
            throw new AuthorizationException('El usuario no puede solicitar cambios de grupo.');
        }

        // Si recepción lo solicita, crea Solicitud de cambio
        if (!$usuario->esDuena()) {
            Gate::forUser($usuario)->authorize('create', Solicitud::class);
            return DB::transaction(function () use ($inscripcionActual, $grupoDestino, $usuario, $motivo) {
                $solicitud = Solicitud::create([
                    'socio_id' => $inscripcionActual->socio_id,
                    'solicitante_id' => $usuario->id,
                    'tipo' => Solicitud::TIPO_CAMBIO_GRUPO,
                    'inscripcion_id' => $inscripcionActual->id,
                    'grupo_id' => $inscripcionActual->grupo_id,
                    'grupo_destino_id' => $grupoDestino->id,
                    'descripcion' => "Solicitud de cambio a {$grupoDestino->nombre}: " . ($motivo ?: 'Sin observaciones'),
                    'estado' => Solicitud::ESTADO_PENDIENTE,
                ]);

                Auditoria::registrar(
                    'administrativo',
                    'solicitud_cambio_grupo',
                    $solicitud,
                    null,
                    ['socio_id' => $solicitud->socio_id, 'inscripcion_id' => $solicitud->inscripcion_id, 'grupo_id' => $solicitud->grupo_id, 'grupo_destino_id' => $solicitud->grupo_destino_id, 'estado' => $solicitud->estado],
                    $usuario->id
                );

                event(GymRealtimeUpdate::request($solicitud, 'created'));

                return [
                    'tipo' => 'solicitud_cambio_pendiente',
                    'solicitud' => $solicitud,
                    'mensaje' => 'Solicitud de cambio de grupo enviada a la dueña para su evaluación.',
                ];
            });
        }

        // Ejecución directa por la Dueña
        return $this->ejecutarCambioGrupo($inscripcionActual, $grupoDestino, $usuario, $motivo);
    }

    /**
     * Ejecuta el cambio de grupo atómicamente:
     * - Bloquea ambos grupos
     * - Verifica cupo en destino
     * - Verifica horarios compatibles
     * - Da de baja en origen
     * - Da de alta aprobada en destino
     */
    public function ejecutarCambioGrupo(
        Inscripcion $inscripcionActual,
        Grupo $grupoDestino,
        User $duena,
        ?string $motivo = null,
        ?Solicitud $solicitud = null
    ): array {
        if (!$duena->esDuena()) {
            throw new AuthorizationException("Solo la dueña puede autorizar el cambio de grupo.");
        }

        return DB::transaction(function () use ($inscripcionActual, $grupoDestino, $duena, $motivo, $solicitud) {
            if ($solicitud) {
                Solicitud::query()->whereKey($solicitud->id)
                    ->where('estado', Solicitud::ESTADO_PENDIENTE)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($solicitud->tipo !== Solicitud::TIPO_CAMBIO_GRUPO
                    || $solicitud->inscripcion_id !== $inscripcionActual->id
                    || $solicitud->grupo_destino_id !== $grupoDestino->id) {
                    throw new DomainException('La solicitud no corresponde a este cambio de grupo.');
                }
            }

            $inscripcionActual = Inscripcion::query()->lockForUpdate()->findOrFail($inscripcionActual->id);
            if (!$inscripcionActual->estaAprobada()) {
                throw new DomainException('La inscripción de origen ya no está aprobada.');
            }
            $gruposBloqueados = Grupo::query()
                ->whereIn('id', [$inscripcionActual->grupo_id, $grupoDestino->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $grupoOrigenLocked = $gruposBloqueados->get($inscripcionActual->grupo_id);
            $grupoDestinoLocked = $gruposBloqueados->get($grupoDestino->id);
            if (!$grupoOrigenLocked || !$grupoDestinoLocked) {
                throw new DomainException('No se pudo confirmar el grupo de origen o destino.');
            }
            $grupoDestinoLocked->load(['disciplina', 'horarios']);
            if ($grupoDestinoLocked->estado !== 'activo' || $grupoDestinoLocked->disciplina->estado !== 'activo') {
                throw new DomainException('No se puede cambiar al grupo porque el grupo o la disciplina están inactivos.');
            }
            if ($grupoDestinoLocked->horarios->isEmpty()) {
                throw new DomainException('No se puede cambiar a un grupo sin horarios configurados.');
            }
            $socio = $inscripcionActual->socio;
            if ($socio->estado !== 'activo') {
                throw new DomainException('No se puede cambiar el grupo de un socio administrativamente inactivo.');
            }

            // Verificar cupo en grupo de destino
            $inscriptosDestino = Inscripcion::where('grupo_id', $grupoDestinoLocked->id)
                ->where('estado', Inscripcion::ESTADO_APROBADA)
                ->count();

            if ($inscriptosDestino >= $grupoDestinoLocked->limiteCupo()) {
                throw new DomainException(
                    "No se puede realizar el cambio: el grupo destino '{$grupoDestinoLocked->nombre}' no tiene vacantes disponibles."
                );
            }

            // Verificar incompatibilidad horaria con otros grupos del socio (ignorando el grupo origen que se dará de baja)
            $conflicto = self::buscarConflictoHorarioSocio($socio, $grupoDestinoLocked, $grupoOrigenLocked->id);
            if ($conflicto) {
                throw new DomainException(
                    "No se puede realizar el cambio: el horario de '{$grupoDestinoLocked->nombre}' colisiona con el grupo '{$conflicto['grupo']->nombre}' el día " . ucfirst($conflicto['dia']) . "."
                );
            }

            // 1. Dar de baja la inscripción anterior
            $datosAntesOrigen = $inscripcionActual->toArray();
            $inscripcionActual->update([
                'estado' => Inscripcion::ESTADO_BAJA,
                'fecha_baja' => now()->toDateString(),
                'motivo_baja' => "Cambio de grupo autorizado hacia '{$grupoDestinoLocked->nombre}'. " . ($motivo ? "Motivo: {$motivo}" : ""),
                'baja_por' => $duena->id,
            ]);

            // 2. Dar de alta la nueva inscripción aprobada en destino
            $inscripcionDestinoExistente = Inscripcion::where('socio_id', $socio->id)
                ->where('grupo_id', $grupoDestinoLocked->id)
                ->first();

            if ($inscripcionDestinoExistente) {
                $inscripcionDestinoExistente->update([
                    'fecha_inscripcion' => now()->toDateString(),
                    'estado' => Inscripcion::ESTADO_APROBADA,
                    'aprobado_por' => $duena->id,
                    'aprobado_at' => now(),
                    'fecha_baja' => null,
                    'motivo_baja' => null,
                    'baja_por' => null,
                    'motivo_rechazo' => null,
                    'rechazado_por' => null,
                    'rechazado_at' => null,
                ]);
                $nuevaInscripcion = $inscripcionDestinoExistente;
            } else {
                $nuevaInscripcion = Inscripcion::create([
                    'socio_id' => $socio->id,
                    'grupo_id' => $grupoDestinoLocked->id,
                    'fecha_inscripcion' => now()->toDateString(),
                    'estado' => Inscripcion::ESTADO_APROBADA,
                    'aprobado_por' => $duena->id,
                    'aprobado_at' => now(),
                ]);
            }

            // 3. Actualizar solicitud de cambio si existía
            Solicitud::where('inscripcion_id', $inscripcionActual->id)
                ->where('tipo', Solicitud::TIPO_CAMBIO_GRUPO)
                ->where('estado', Solicitud::ESTADO_PENDIENTE)
                ->when($solicitud, fn ($query) => $query->whereKey($solicitud->id))
                ->update([
                    'estado' => Solicitud::ESTADO_APROBADA,
                    'revisado_por' => $duena->id,
                    'respuesta_duena' => 'Cambio de grupo aprobado: ' . ($motivo ?: 'Aprobado'),
                    'fecha_decision' => now(),
                ]);

            Auditoria::registrar(
                'administrativo',
                'cambio_grupo',
                $nuevaInscripcion,
                $datosAntesOrigen,
                $nuevaInscripcion->toArray(),
                $duena->id
            );

            if (!$solicitud) {
                event(GymRealtimeUpdate::enrollment($nuevaInscripcion, 'group-changed'));
            }

            return [
                'tipo' => 'cambio_completado',
                'inscripcion_anterior' => $inscripcionActual,
                'nueva_inscripcion' => $nuevaInscripcion,
                'mensaje' => "Cambio de grupo realizado exitosamente: pase de '{$grupoOrigenLocked->nombre}' a '{$grupoDestinoLocked->nombre}'.",
            ];
        });
    }

    /**
     * Incorpora a un socio desde la Lista de Espera a una vacante disponible.
     * Acción exclusiva de la Dueña con verificación previa de cupo y horarios.
     */
    public function incorporarDesdeListaEspera(ListaEspera $espera, User $duena): Inscripcion
    {
        if (!$duena->esDuena()) {
            throw new AuthorizationException("Solo la dueña puede incorporar alumnos desde la lista de espera.");
        }

        return DB::transaction(function () use ($espera, $duena) {
            $grupoId = ListaEspera::query()->whereKey($espera->id)->value('grupo_id');
            if ($grupoId === null) {
                throw new DomainException('El registro de lista de espera ya no existe.');
            }

            $grupoLocked = Grupo::whereKey($grupoId)->lockForUpdate()->firstOrFail();
            $esperaLocked = ListaEspera::whereKey($espera->id)->lockForUpdate()->firstOrFail();
            if ((int) $esperaLocked->grupo_id !== (int) $grupoLocked->id
                || !$esperaLocked->estaEsperando()) {
                throw new DomainException('El registro ya no está esperando en este grupo.');
            }

            $primeraEspera = ListaEspera::query()
                ->where('grupo_id', $grupoLocked->id)
                ->where('estado', ListaEspera::ESTADO_ESPERANDO)
                ->orderBy('posicion')
                ->orderBy('fecha_solicitud')
                ->orderBy('id')
                ->lockForUpdate()
                ->firstOrFail();

            if ($primeraEspera->id !== $esperaLocked->id) {
                throw new DomainException(
                    "Debe respetarse el orden de la lista de espera. El próximo turno corresponde a la posición #{$primeraEspera->posicion}."
                );
            }

            $grupoLocked->load(['disciplina', 'horarios']);
            if ($grupoLocked->estado !== 'activo' || $grupoLocked->disciplina->estado !== 'activo') {
                throw new DomainException('No se puede incorporar al socio porque el grupo o la disciplina están inactivos.');
            }

            if ($grupoLocked->horarios->isEmpty()) {
                throw new DomainException('No se puede incorporar al socio porque el grupo no tiene horarios configurados.');
            }

            $socio = $esperaLocked->socio;
            if ($socio->estado !== 'activo') {
                throw new DomainException('No se puede incorporar a un socio administrativamente inactivo.');
            }

            // 1. Verificar cupo disponible
            $inscriptos = Inscripcion::where('grupo_id', $grupoLocked->id)
                ->where('estado', Inscripcion::ESTADO_APROBADA)
                ->lockForUpdate()
                ->count();

            if ($inscriptos >= $grupoLocked->limiteCupo()) {
                throw new DomainException(
                    "No se puede incorporar al socio: el grupo '{$grupoLocked->nombre}' no tiene vacantes libres ({$inscriptos}/{$grupoLocked->limiteCupo()})."
                );
            }

            $conflicto = self::buscarConflictoHorarioSocio($socio, $grupoLocked);
            if ($conflicto) {
                throw new DomainException(
                    "No se puede incorporar: incompatibilidad horaria con el grupo '{$conflicto['grupo']->nombre}' el día " . ucfirst($conflicto['dia']) . "."
                );
            }

            $inscripcionExistente = Inscripcion::where('socio_id', $socio->id)
                ->where('grupo_id', $grupoLocked->id)
                ->lockForUpdate()
                ->first();

            if ($inscripcionExistente?->estaAprobada() || $inscripcionExistente?->estaPendiente()) {
                throw new DomainException('El socio ya tiene una inscripción aprobada o pendiente para este grupo.');
            }

            // 3. Crear o actualizar la inscripción a estado 'aprobada'
            if ($inscripcionExistente) {
                $inscripcionExistente->update([
                    'fecha_inscripcion' => now()->toDateString(),
                    'estado' => Inscripcion::ESTADO_APROBADA,
                    'aprobado_por' => $duena->id,
                    'aprobado_at' => now(),
                    'fecha_baja' => null,
                    'motivo_baja' => null,
                    'baja_por' => null,
                    'motivo_rechazo' => null,
                    'rechazado_por' => null,
                    'rechazado_at' => null,
                ]);
                $inscripcion = $inscripcionExistente;
            } else {
                $inscripcion = Inscripcion::create([
                    'socio_id' => $socio->id,
                    'grupo_id' => $grupoLocked->id,
                    'fecha_inscripcion' => now()->toDateString(),
                    'estado' => Inscripcion::ESTADO_APROBADA,
                    'aprobado_por' => $duena->id,
                    'aprobado_at' => now(),
                ]);
            }

            // 4. Marcar como promovido en la lista de espera y reordenar
            $esperaLocked->update(['estado' => ListaEspera::ESTADO_PROMOVIDO]);
            self::reordenarListaEspera($grupoLocked->id);

            Auditoria::registrar(
                'administrativo',
                'incorporacion_desde_lista_espera',
                $inscripcion,
                ['posicion_espera' => $espera->posicion],
                $inscripcion->toArray(),
                $duena->id
            );

            return $inscripcion;
        });
    }

    /**
     * Cancela un registro de lista de espera y reordena posiciones restantes.
     */
    public function cancelarListaEspera(ListaEspera $espera, User $usuario, ?string $motivo = null): ListaEspera
    {
        if (!$usuario->esDuena()) {
            throw new AuthorizationException('Solo la dueña puede cancelar un turno de la lista de espera.');
        }

        return DB::transaction(function () use ($espera, $usuario, $motivo) {
            $grupoId = ListaEspera::query()->whereKey($espera->id)->value('grupo_id');
            if ($grupoId === null) {
                throw new DomainException('El registro de lista de espera ya no existe.');
            }

            Grupo::query()->whereKey($grupoId)->lockForUpdate()->firstOrFail();
            $esperaLocked = ListaEspera::whereKey($espera->id)->lockForUpdate()->firstOrFail();
            if ((int) $esperaLocked->grupo_id !== (int) $grupoId || !$esperaLocked->estaEsperando()) {
                throw new DomainException('El registro ya no está esperando y no puede cancelarse.');
            }
            $datosAntes = $esperaLocked->toArray();

            $esperaLocked->update([
                'estado' => ListaEspera::ESTADO_CANCELADO,
                'observaciones' => $motivo ? ($esperaLocked->observaciones . " | Cancelado: " . $motivo) : $esperaLocked->observaciones,
            ]);

            self::reordenarListaEspera($esperaLocked->grupo_id);

            Auditoria::registrar(
                'administrativo',
                'cancelacion_lista_espera',
                $esperaLocked,
                $datosAntes,
                $esperaLocked->toArray(),
                $usuario->id
            );

            return $esperaLocked;
        });
    }

    /**
     * Reordena las posiciones de la lista de espera de un grupo en orden FIFO.
     */
    public static function reordenarListaEspera(int $grupoId): void
    {
        $esperas = ListaEspera::where('grupo_id', $grupoId)
            ->where('estado', ListaEspera::ESTADO_ESPERANDO)
            ->orderBy('posicion', 'asc')
            ->orderBy('fecha_solicitud', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $pos = 1;
        foreach ($esperas as $item) {
            if ($item->posicion !== $pos) {
                $item->update(['posicion' => $pos]);
            }
            $pos++;
        }
    }

    /**
     * Busca incompatibilidades horarias entre los grupos aprobados de un socio y un nuevo grupo.
     * Retorna array descriptivo del conflicto o null si no hay colisiones.
     * Las clases consecutivas NO se consideran colisión.
     */
    public static function buscarConflictoHorarioSocio(
        Socio $socio,
        Grupo $grupoNuevo,
        ?int $grupoIdIgnorar = null
    ): ?array {
        $gruposActuales = Grupo::whereHas('inscripciones', function ($q) use ($socio) {
            $q->where('socio_id', $socio->id)
              ->where('estado', Inscripcion::ESTADO_APROBADA);
        })
        ->when($grupoIdIgnorar, fn($q) => $q->where('id', '!=', $grupoIdIgnorar))
        ->with('horarios')
        ->get();

        $horariosNuevos = $grupoNuevo->horarios;

        foreach ($gruposActuales as $gActual) {
            if ($gActual->id === $grupoNuevo->id) {
                continue;
            }

            foreach ($gActual->horarios as $hActual) {
                foreach ($horariosNuevos as $hNuevo) {
                    if (strtolower($hActual->dia_semana) === strtolower($hNuevo->dia_semana)) {
                        $inicioActual = HorarioGimnasioService::normalizarHora($hActual->hora_inicio);
                        $finActual = HorarioGimnasioService::normalizarHora($hActual->hora_fin);
                        $inicioNuevo = HorarioGimnasioService::normalizarHora($hNuevo->hora_inicio);
                        $finNuevo = HorarioGimnasioService::normalizarHora($hNuevo->hora_fin);

                        // Se superponen si y solo si inicioA < finB y finA > inicioB
                        if ($inicioActual < $finNuevo && $finActual > $inicioNuevo) {
                            return [
                                'grupo' => $gActual,
                                'dia' => $hActual->dia_semana,
                                'horario_actual' => substr($inicioActual, 0, 5) . " a " . substr($finActual, 0, 5) . " hs",
                                'horario_nuevo' => substr($inicioNuevo, 0, 5) . " a " . substr($finNuevo, 0, 5) . " hs",
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }
}
