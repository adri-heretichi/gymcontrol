<?php

namespace App\Services;

use App\Events\GymRealtimeUpdate;
use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Auditoria;
use App\Models\ConfiguracionGimnasio;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AsistenciaClaseService
{
    public const ESTADO_PUNTUAL = AsistenciaClase::ESTADO_PUNTUAL;
    public const ESTADO_TARDE = AsistenciaClase::ESTADO_TARDE;
    public const ESTADO_PENDIENTE = AsistenciaClase::ESTADO_PENDIENTE;
    public const ESTADO_AUSENCIA_CONFIRMADA = AsistenciaClase::ESTADO_AUSENCIA_CONFIRMADA;

    public function __construct(
        private readonly PagoService $pagoService,
        private readonly CertificadoMedicoService $certificadoMedicoService,
    ) {
    }

    /**
     * Registra una presentación a una sesión concreta del horario del grupo.
     *
     * @return array{autorizado: bool, codigo: string, mensaje: string, asistencia_clase: ?AsistenciaClase}
     */
    public function registrarAsistencia(
        Socio $socio,
        Grupo $grupo,
        User $operador,
        ?Carbon $momento = null,
        ?Asistencia $permanencia = null,
        string $origen = 'operador'
    ): array {
        Gate::forUser($operador)->authorize('registrar-asistencia-clase');
        $momento = ($momento ?? Carbon::now(config('app.timezone')))->copy()->setTimezone(config('app.timezone'));

        return DB::transaction(function () use ($socio, $grupo, $operador, $momento, $permanencia, $origen) {
            $socioBloqueado = Socio::query()->lockForUpdate()->find($socio->id);
            if (!$socioBloqueado || $socioBloqueado->estado !== 'activo') {
                return $this->denegar('socio_inactivo', 'El socio no está habilitado.', $socioBloqueado, $grupo, $operador);
            }

            $grupoActual = Grupo::query()->with(['disciplina', 'horarios'])
                ->lockForUpdate()
                ->find($grupo->id);
            if (!$grupoActual || $grupoActual->estado !== 'activo' || $grupoActual->disciplina?->estado !== 'activa') {
                return $this->denegar('grupo_inactivo', 'El grupo no está habilitado.', $socioBloqueado, $grupoActual, $operador);
            }

            $inscripcion = Inscripcion::query()
                ->where('socio_id', $socioBloqueado->id)
                ->where('grupo_id', $grupoActual->id)
                ->where('estado', Inscripcion::ESTADO_APROBADA)
                ->first();
            if (!$inscripcion) {
                return $this->denegar('inscripcion_no_aprobada', 'No hay una inscripción aprobada para este grupo.', $socioBloqueado, $grupoActual, $operador);
            }

            $cobertura = $this->pagoService->consultarCoberturaInscripcion($inscripcion, $momento->toDateString());
            if (!$cobertura['vigente']) {
                $codigo = $cobertura['estado'] === PagoDetalle::COBERTURA_VENCIDA
                    ? 'cuota_vencida'
                    : 'sin_cobertura';
                return $this->denegar($codigo, 'No hay cobertura vigente para este grupo.', $socioBloqueado, $grupoActual, $operador);
            }

            $evaluacionMedica = $this->certificadoMedicoService->evaluarRequisitoMedico(
                $socioBloqueado,
                $momento->toDateString()
            );
            if (!$evaluacionMedica['cumple']) {
                return $this->denegar('requisito_medico_pendiente', 'El acceso a esta clase no está habilitado.', $socioBloqueado, $grupoActual, $operador);
            }

            $horarios = $grupoActual->horarios
                ->filter(fn ($horario) => $this->normalizarDia($horario->dia_semana) === $this->diaSemana($momento)
                    && $horario->hora_inicio <= $momento->format('H:i:s')
                    && $horario->hora_fin > $momento->format('H:i:s'))
                ->values();
            if ($horarios->count() !== 1) {
                return $this->denegar(
                    $horarios->isEmpty() ? 'fuera_de_horario' : 'horario_ambiguo',
                    $horarios->isEmpty()
                        ? 'No hay una clase en curso para este grupo.'
                        : 'El horario del grupo es ambiguo; solicitá una revisión administrativa.',
                    $socioBloqueado,
                    $grupoActual,
                    $operador
                );
            }

            $horario = $horarios->first();
            $asistenciaFisica = $permanencia
                ? Asistencia::query()
                    ->whereKey($permanencia->id)
                    ->where('socio_id', $socioBloqueado->id)
                    ->whereNull('hora_salida')
                    ->lockForUpdate()
                    ->first()
                : Asistencia::query()
                    ->where('socio_id', $socioBloqueado->id)
                    ->whereNull('hora_salida')
                    ->lockForUpdate()
                    ->first();

            if (!$asistenciaFisica) {
                return $this->denegar('sin_permanencia_abierta', 'Se requiere una permanencia física abierta.', $socioBloqueado, $grupoActual, $operador);
            }

            $duplicada = AsistenciaClase::query()
                ->where('socio_id', $socioBloqueado->id)
                ->where('grupo_id', $grupoActual->id)
                ->whereDate('fecha', $momento->toDateString())
                ->where('hora_inicio_programada', $horario->hora_inicio)
                ->exists();
            if ($duplicada) {
                return $this->denegar('asistencia_duplicada', 'La asistencia a esta clase ya está registrada.', $socioBloqueado, $grupoActual, $operador);
            }

            $inicioProgramado = Carbon::parse(
                $momento->toDateString() . ' ' . $horario->hora_inicio,
                config('app.timezone')
            );
            $estado = $this->estadoDePresentacion($momento, $inicioProgramado);

            $asistenciaClase = AsistenciaClase::create([
                'asistencia_id' => $asistenciaFisica->id,
                'socio_id' => $socioBloqueado->id,
                'inscripcion_id' => $inscripcion->id,
                'grupo_id' => $grupoActual->id,
                'fecha' => $momento->toDateString(),
                'hora_inicio_programada' => $horario->hora_inicio,
                'hora_fin_programada' => $horario->hora_fin,
                'hora_marcado' => $momento->format('H:i:s'),
                'estado' => $estado,
                'marcado_por' => $operador->id,
                'origen_registro' => $origen,
            ]);

            Auditoria::registrar(
                tipoEvento: 'asistencia_clase',
                accion: 'asistencia_clase_registrada',
                entidad: $asistenciaClase,
                datosDespues: [
                    'socio_id' => $socioBloqueado->id,
                    'inscripcion_id' => $inscripcion->id,
                    'grupo_id' => $grupoActual->id,
                    'fecha' => $momento->toDateString(),
                    'hora_inicio_programada' => substr($horario->hora_inicio, 0, 5),
                    'estado' => $estado,
                    'origen' => $origen,
                ],
                userId: $operador->id
            );

            event(GymRealtimeUpdate::classAttendance(
                $asistenciaClase,
                $socioBloqueado,
                [
                    'grupo' => $grupoActual->nombre,
                    'disciplina' => $grupoActual->disciplina?->nombre,
                ]
            ));

            return [
                'autorizado' => true,
                'codigo' => 'asistencia_registrada',
                'mensaje' => 'Asistencia a la clase registrada.',
                'asistencia_clase' => $asistenciaClase,
            ];
        }, 3);
    }

    public function guardarTolerancia(?int $minutos, User $duena): ConfiguracionGimnasio
    {
        Gate::forUser($duena)->authorize('gestionar-asistencias');
        if ($minutos !== null && ($minutos < 0 || $minutos > 1440)) {
            throw ValidationException::withMessages([
                'tolerancia_tardanza_minutos' => 'La tolerancia debe ser un valor entre 0 y 1440 minutos o quedar sin configurar.',
            ]);
        }

        return DB::transaction(function () use ($minutos, $duena) {
            $configuracion = ConfiguracionGimnasio::query()->lockForUpdate()->find(1);
            $anterior = $configuracion?->tolerancia_tardanza_minutos;
            $configuracion ??= new ConfiguracionGimnasio(['id' => 1]);
            $configuracion->fill([
                'tolerancia_tardanza_minutos' => $minutos,
                'actualizada_por' => $duena->id,
            ])->save();

            Auditoria::registrar(
                tipoEvento: 'asistencia_clase',
                accion: 'tolerancia_tardanza_actualizada',
                entidad: $configuracion,
                datosAntes: ['tolerancia_tardanza_minutos' => $anterior],
                datosDespues: ['tolerancia_tardanza_minutos' => $minutos],
                userId: $duena->id
            );

            return $configuracion->fresh();
        });
    }

    /**
     * Confirma ausencias únicamente para una clase de hoy ya finalizada.
     *
     * @return int cantidad de ausencias agregadas
     */
    public function confirmarAusencias(
        Grupo $grupo,
        string $fecha,
        string $horaInicio,
        User $duena,
        ?Carbon $ahora = null
    ): int
    {
        Gate::forUser($duena)->authorize('gestionar-asistencias');
        $ahora = ($ahora ?? Carbon::now(config('app.timezone')))->copy()->setTimezone(config('app.timezone'));
        $fechaClase = Carbon::parse($fecha, config('app.timezone'));
        if (!$fechaClase->isSameDay($ahora)) {
            throw ValidationException::withMessages([
                'fecha' => 'Solo se pueden confirmar ausencias para una clase de hoy y después de su finalización.',
            ]);
        }

        return DB::transaction(function () use ($grupo, $fecha, $horaInicio, $duena, $ahora) {
            $grupoActual = Grupo::query()->with(['horarios', 'disciplina'])->lockForUpdate()->findOrFail($grupo->id);
            if ($grupoActual->estado !== 'activo' || $grupoActual->disciplina?->estado !== 'activa') {
                throw ValidationException::withMessages(['grupo' => 'El grupo no está activo.']);
            }

            $horario = $grupoActual->horarios
                ->first(fn ($item) => $item->hora_inicio === $horaInicio
                    && $this->normalizarDia($item->dia_semana) === $this->diaSemana($ahora)
                    && Carbon::parse($fecha . ' ' . $item->hora_fin, config('app.timezone'))->lte($ahora));
            if (!$horario) {
                throw ValidationException::withMessages(['grupo' => 'No hay una clase de hoy finalizada para este grupo.']);
            }

            $creadas = 0;
            $inscripciones = Inscripcion::query()
                ->where('grupo_id', $grupoActual->id)
                ->where('estado', Inscripcion::ESTADO_APROBADA)
                ->with('socio')
                ->get();

            foreach ($inscripciones as $inscripcion) {
                if (!$this->socioHabilitadoParaClase($inscripcion, $fecha)) {
                    continue;
                }

                $existe = AsistenciaClase::query()
                    ->where('socio_id', $inscripcion->socio_id)
                    ->where('grupo_id', $grupoActual->id)
                    ->whereDate('fecha', $fecha)
                    ->where('hora_inicio_programada', $horario->hora_inicio)
                    ->exists();
                if ($existe) {
                    continue;
                }

                $ausencia = AsistenciaClase::create([
                    'socio_id' => $inscripcion->socio_id,
                    'inscripcion_id' => $inscripcion->id,
                    'grupo_id' => $grupoActual->id,
                    'fecha' => $fecha,
                    'hora_inicio_programada' => $horario->hora_inicio,
                    'hora_fin_programada' => $horario->hora_fin,
                    'hora_marcado' => null,
                    'estado' => self::ESTADO_AUSENCIA_CONFIRMADA,
                    'marcado_por' => $duena->id,
                    'origen_registro' => 'cierre_clase',
                ]);
                Auditoria::registrar(
                    tipoEvento: 'asistencia_clase',
                    accion: 'ausencia_clase_confirmada',
                    entidad: $ausencia,
                    datosDespues: [
                        'socio_id' => $inscripcion->socio_id,
                        'grupo_id' => $grupoActual->id,
                        'fecha' => $fecha,
                        'hora_inicio_programada' => substr($horario->hora_inicio, 0, 5),
                    ],
                    userId: $duena->id
                );
                $creadas++;
            }

            return $creadas;
        }, 3);
    }

    public function corregirAsistencia(
        AsistenciaClase $asistencia,
        string $estado,
        ?string $horaMarcado,
        User $duena
    ): AsistenciaClase {
        Gate::forUser($duena)->authorize('gestionar-asistencias');
        $estadosValidos = [
            self::ESTADO_PUNTUAL,
            self::ESTADO_TARDE,
            self::ESTADO_PENDIENTE,
            self::ESTADO_AUSENCIA_CONFIRMADA,
            'ausente_justificado',
            'cancelado',
        ];
        if (!in_array($estado, $estadosValidos, true)) {
            throw ValidationException::withMessages(['estado' => 'El estado de asistencia no es válido.']);
        }
        if ($estado !== self::ESTADO_AUSENCIA_CONFIRMADA && $horaMarcado === null) {
            throw ValidationException::withMessages(['hora_marcado' => 'Indicá la hora real de presentación.']);
        }

        return DB::transaction(function () use ($asistencia, $estado, $horaMarcado, $duena) {
            $actual = AsistenciaClase::query()->lockForUpdate()->findOrFail($asistencia->id);
            $anterior = [
                'estado' => $actual->estado,
                'hora_marcado' => $actual->hora_marcado,
            ];
            $actual->update([
                'estado' => $estado,
                'hora_marcado' => $estado === self::ESTADO_AUSENCIA_CONFIRMADA ? null : $horaMarcado,
                'marcado_por' => $duena->id,
                'origen_registro' => 'correccion_admin',
            ]);

            Auditoria::registrar(
                tipoEvento: 'asistencia_clase',
                accion: 'asistencia_clase_corregida',
                entidad: $actual,
                datosAntes: $anterior,
                datosDespues: [
                    'estado' => $actual->estado,
                    'hora_marcado' => $actual->hora_marcado,
                ],
                userId: $duena->id
            );

            return $actual->fresh();
        }, 3);
    }

    private function estadoDePresentacion(Carbon $momento, Carbon $inicioProgramado): string
    {
        if ($momento->lte($inicioProgramado)) {
            return self::ESTADO_PUNTUAL;
        }

        $tolerancia = ConfiguracionGimnasio::query()->find(1)?->tolerancia_tardanza_minutos;
        if ($tolerancia === null) {
            return self::ESTADO_PENDIENTE;
        }

        return $momento->lte($inicioProgramado->copy()->addMinutes($tolerancia))
            ? self::ESTADO_PUNTUAL
            : self::ESTADO_TARDE;
    }

    private function socioHabilitadoParaClase(Inscripcion $inscripcion, string $fecha): bool
    {
        if (!$inscripcion->socio || $inscripcion->socio->estado !== 'activo') {
            return false;
        }

        $cobertura = $this->pagoService->consultarCoberturaInscripcion($inscripcion, $fecha);
        $medico = $this->certificadoMedicoService->evaluarRequisitoMedico($inscripcion->socio, $fecha);

        return $cobertura['vigente'] && $medico['cumple'];
    }

    private function diaSemana(Carbon $momento): string
    {
        return [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo',
        ][(int) $momento->format('N')];
    }

    private function normalizarDia(string $dia): string
    {
        return strtr(mb_strtolower(trim($dia)), [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
        ]);
    }

    private function denegar(
        string $codigo,
        string $mensaje,
        ?Socio $socio,
        ?Grupo $grupo,
        User $operador
    ): array
    {
        Auditoria::registrar(
            tipoEvento: 'asistencia_clase',
            accion: 'asistencia_clase_denegada',
            entidad: $socio,
            datosDespues: [
                'grupo_id' => $grupo?->id,
                'motivo' => 'reglas_de_acceso_no_cumplidas',
            ],
            userId: $operador->id
        );

        return [
            'autorizado' => false,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'asistencia_clase' => null,
        ];
    }
}
