<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Events\GymRealtimeUpdate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ControlAccesoService
{
    private const CODIGOS_DENEGACION_MEDICA = [
        'decision_medica_pendiente',
        'certificado_vencido',
        'certificado_pendiente_aprobacion',
        'certificado_rechazado',
        'certificado_faltante',
    ];

    public const METODO_PIN = 'pin';
    public const METODO_QR = 'qr';

    public function __construct(
        private readonly PagoService $pagoService,
        private readonly CertificadoMedicoService $certificadoMedicoService,
    ) {
    }

    /**
     * Corrige una permanencia existente sin borrar el movimiento ni su auditoría.
     */
    public function corregirPermanencia(
        Asistencia $asistencia,
        string $fechaIngreso,
        string $horaIngreso,
        ?string $fechaSalida,
        ?string $horaSalida,
        \App\Models\User $duena
    ): Asistencia {
        \Illuminate\Support\Facades\Gate::forUser($duena)->authorize('gestionar-asistencias');

        return DB::transaction(function () use (
            $asistencia,
            $fechaIngreso,
            $horaIngreso,
            $fechaSalida,
            $horaSalida,
            $duena
        ) {
            $actual = Asistencia::query()->lockForUpdate()->findOrFail($asistencia->id);
            Socio::query()->lockForUpdate()->findOrFail($actual->socio_id);

            if (($fechaSalida === null) !== ($horaSalida === null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'hora_salida' => 'La fecha y la hora de salida deben indicarse juntas.',
                ]);
            }

            $inicio = Carbon::parse($fechaIngreso . ' ' . $horaIngreso, config('app.timezone'));
            $fin = $fechaSalida !== null
                ? Carbon::parse($fechaSalida . ' ' . $horaSalida, config('app.timezone'))
                : null;
            if ($fin && $fin->lte($inicio)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'hora_salida' => 'La salida debe ser posterior al ingreso.',
                ]);
            }
            if (!$fin && Asistencia::query()
                ->where('socio_id', $actual->socio_id)
                ->whereNull('hora_salida')
                ->where('id', '<>', $actual->id)
                ->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'hora_salida' => 'El socio ya tiene otra permanencia abierta.',
                ]);
            }

            $antes = [
                'fecha' => $actual->fecha?->toDateString(),
                'hora_ingreso' => $actual->hora_ingreso,
                'fecha_salida' => $actual->fecha_salida?->toDateString(),
                'hora_salida' => $actual->hora_salida,
                'tiempo_permanencia' => $actual->tiempo_permanencia,
            ];
            $actual->update([
                'fecha' => $inicio->toDateString(),
                'hora_ingreso' => $inicio->format('H:i:s'),
                'fecha_salida' => $fin?->toDateString(),
                'hora_salida' => $fin?->format('H:i:s'),
                'tiempo_permanencia' => $fin
                    ? (int) floor(($fin->getTimestamp() - $inicio->getTimestamp()) / 60)
                    : null,
                'operador_salida_id' => $fin ? $duena->id : null,
                'metodo_salida' => $fin ? 'correccion_admin' : null,
            ]);

            Auditoria::registrar(
                tipoEvento: 'asistencia_fisica',
                accion: 'permanencia_corregida',
                entidad: $actual,
                datosAntes: $antes,
                datosDespues: [
                    'fecha' => $actual->fecha?->toDateString(),
                    'hora_ingreso' => $actual->hora_ingreso,
                    'fecha_salida' => $actual->fecha_salida?->toDateString(),
                    'hora_salida' => $actual->hora_salida,
                    'tiempo_permanencia' => $actual->tiempo_permanencia,
                ],
                userId: $duena->id
            );

            return $actual->fresh();
        }, 3);
    }

    /**
     * Evalúa el ingreso sin escribir datos. Si hay varias actividades elegibles,
     * obliga a seleccionar el grupo en vez de escoger una implícitamente.
     *
     * @return array<string, mixed>
     */
    public function evaluarAcceso(
        string $identificador,
        string $metodo,
        ?int $grupoId = null,
        ?Carbon $momento = null
    ): array {
        $momento = $this->momentoLocal($momento);
        $socio = $this->resolverCredencial($identificador, $metodo);

        if (!$socio) {
            $revocada = $this->credencialRevocada($identificador, $metodo);
            $legada = $metodo === self::METODO_QR
                && Socio::query()->where('token', $identificador)->exists();

            return $this->denegar(
                $revocada ? 'credencial_revocada' : ($legada ? 'credencial_legada' : 'credencial_invalida'),
                'La credencial no es válida para el acceso actual.'
            );
        }

        return $this->evaluarSocio($socio, $metodo, $grupoId, $momento);
    }

    /**
     * Oculta los detalles médicos en las respuestas operativas y públicas.
     */
    public function ocultarDetalleMedico(array $resultado): array
    {
        if (in_array($resultado['codigo'], self::CODIGOS_DENEGACION_MEDICA, true)) {
            $resultado['codigo'] = 'requisitos_pendientes';
            $resultado['mensaje'] = 'No habilitado. Consultar con la dueña.';
        }

        return $resultado;
    }

    /**
     * Registra únicamente ingresos autorizados. Bloquea al socio durante la
     * evaluación y escritura para serializar solicitudes concurrentes.
     *
     * @return array<string, mixed>
     */
    public function registrarIngreso(
        string $identificador,
        string $metodo,
        ?int $grupoId = null,
        ?int $operadorId = null,
        ?Carbon $momento = null,
        ?string $origen = null,
        ?string $dispositivo = null
    ): array {
        $momento = $this->momentoLocal($momento);
        $socioInicial = $this->resolverCredencial($identificador, $metodo);

        if (!$socioInicial) {
            return DB::transaction(function () use ($identificador, $metodo, $grupoId, $momento, $operadorId, $origen) {
                $resultado = $this->evaluarAcceso($identificador, $metodo, $grupoId, $momento);
                $this->auditarAcceso($resultado, $metodo, $grupoId, $operadorId, null, $origen);
                return $resultado;
            }, 3);
        }

        return DB::transaction(function () use (
            $identificador,
            $metodo,
            $grupoId,
            $operadorId,
            $momento,
            $origen,
            $dispositivo,
            $socioInicial
        ) {
            $socio = Socio::query()->lockForUpdate()->find($socioInicial->id);
            if (!$socio) {
                $resultado = $this->denegar('credencial_invalida', 'La credencial no es válida para el acceso actual.');
                $this->auditarAcceso($resultado, $metodo, $grupoId, $operadorId, null, $origen);
                return $resultado;
            }

            $resultado = $this->evaluarSocio($socio, $metodo, $grupoId, $momento);
            if (!$resultado['autorizado']) {
                $this->auditarAcceso($resultado, $metodo, $grupoId, $operadorId, $socio, $origen);
                return $resultado;
            }

            $asistenciaAbierta = Asistencia::query()
                ->where('socio_id', $socio->id)
                ->whereNull('hora_salida')
                ->lockForUpdate()
                ->first();

            if ($asistenciaAbierta) {
                $resultado = $this->denegar(
                    'ingreso_duplicado',
                    'Ya existe un ingreso abierto para este socio.',
                    $socio,
                    $resultado['actividad']
                );
                $this->auditarAcceso($resultado, $metodo, $grupoId, $operadorId, $socio, $origen);
                return $resultado;
            }

            $asistencia = Asistencia::create([
                'socio_id' => $socio->id,
                'fecha' => $momento->toDateString(),
                'hora_ingreso' => $momento->format('H:i:s'),
                'metodo_acceso' => $this->metodoAsistencia($metodo, $origen),
                'dispositivo_origen' => $dispositivo,
                'operador_ingreso_id' => $operadorId,
            ]);

            $resultado['asistencia'] = $asistencia;
            $resultado['mensaje'] = 'Acceso autorizado.';
            $this->auditarAcceso($resultado, $metodo, $grupoId, $operadorId, $socio, $origen);

            return $resultado;
        }, 3);
    }

    /**
     * Registra una salida explícita. No vuelve a evaluar pagos, certificados,
     * inscripción ni horario: una salida abierta no debe quedar bloqueada.
     *
     * @return array<string, mixed>
     */
    public function registrarSalida(
        Socio $socio,
        ?int $operadorId = null,
        ?Carbon $momento = null,
        string $metodoSalida = 'manual_operador'
    ): array {
        $momento = $this->momentoLocal($momento);

        return DB::transaction(function () use ($socio, $operadorId, $momento, $metodoSalida) {
            $socioBloqueado = Socio::query()->lockForUpdate()->find($socio->id);
            if (!$socioBloqueado) {
                $resultado = $this->denegar('socio_inexistente', 'No se encontró el socio.');
                $this->auditarAcceso($resultado, 'salida', null, $operadorId, null, $metodoSalida);
                return $resultado;
            }

            $asistencia = Asistencia::query()
                ->where('socio_id', $socioBloqueado->id)
                ->whereNull('hora_salida')
                ->lockForUpdate()
                ->first();

            if (!$asistencia) {
                $resultado = $this->denegar('sin_ingreso_abierto', 'No hay un ingreso abierto para registrar la salida.', $socioBloqueado);
                $this->auditarAcceso($resultado, 'salida', null, $operadorId, $socioBloqueado, $metodoSalida);
                return $resultado;
            }

            $fechaIngreso = Carbon::parse(
                $asistencia->fecha->toDateString() . ' ' . $asistencia->hora_ingreso,
                config('app.timezone')
            );
            if ($momento->lte($fechaIngreso)) {
                $resultado = $this->denegar(
                    'salida_anterior_al_ingreso',
                    'La hora de salida debe ser posterior al ingreso.',
                    $socioBloqueado
                );
                $this->auditarAcceso($resultado, 'salida', null, $operadorId, $socioBloqueado, $metodoSalida);
                return $resultado;
            }
            $minutos = (int) floor(($momento->getTimestamp() - $fechaIngreso->getTimestamp()) / 60);

            $asistencia->update([
                'fecha_salida' => $momento->toDateString(),
                'hora_salida' => $momento->format('H:i:s'),
                'tiempo_permanencia' => $minutos,
                'metodo_salida' => $metodoSalida,
                'operador_salida_id' => $operadorId,
            ]);

            $resultado = [
                'autorizado' => true,
                'codigo' => 'salida_registrada',
                'mensaje' => 'Salida registrada.',
                'socio' => $socioBloqueado,
                'actividad' => null,
                'dia' => $this->diaSemana($momento),
                'horario' => null,
                'asistencia' => $asistencia->fresh(),
                'minutos_permanencia' => $minutos,
            ];
            $this->auditarAcceso($resultado, 'salida', null, $operadorId, $socioBloqueado, $metodoSalida);

            return $resultado;
        }, 3);
    }

    /**
     * Cierra una permanencia con una credencial activa o un fingerprint revocado.
     * La validación económica, médica y horaria no se aplica a una salida.
     */
    public function registrarSalidaPorCredencial(
        string $identificador,
        string $metodo,
        ?int $operadorId = null,
        ?Carbon $momento = null,
        ?string $metodoSalida = null
    ): array {
        $socio = $this->resolverCredencial($identificador, $metodo);

        if (!$socio && in_array($metodo, [self::METODO_PIN, self::METODO_QR], true)) {
            $columna = $metodo === self::METODO_PIN ? 'pin_revocado_hash' : 'qr_token_revocado_hash';
            $hash = hash_hmac('sha256', $identificador, (string) config('app.key'));
            $socio = Socio::query()->where($columna, $hash)->first();
        }

        if (!$socio) {
            $resultado = $this->evaluarAcceso($identificador, $metodo, null, $momento);
            $resultado['autorizado'] = false;
            $resultado['codigo'] = 'credencial_invalida';
            $resultado['mensaje'] = 'No se encontró un ingreso para registrar la salida.';
            $this->auditarAcceso(
                $resultado,
                'salida',
                null,
                $operadorId,
                null,
                $metodoSalida ?? ($metodo === self::METODO_QR ? 'qr_movil' : 'pin_recepcion')
            );
            return $resultado;
        }

        return $this->registrarSalida(
            $socio,
            $operadorId,
            $momento,
            $metodoSalida ?? ($metodo === self::METODO_QR ? 'qr_movil' : 'pin_recepcion')
        );
    }

    private function evaluarSocio(Socio $socio, string $metodo, ?int $grupoId, Carbon $momento): array
    {
        if (!$this->credencialActivaPerteneceAlSocio($socio, $metodo)) {
            return $this->denegar('credencial_revocada', 'La credencial no es válida para el acceso actual.');
        }

        if ($socio->estado !== 'activo') {
            return $this->denegar('socio_inactivo', 'El acceso no está habilitado para este socio.', $socio);
        }

        $inscripciones = $socio->inscripciones()->with(['grupo.disciplina', 'grupo.horarios'])->get();
        $aprobadas = $inscripciones->where('estado', Inscripcion::ESTADO_APROBADA);
        $pendientes = $inscripciones->where('estado', Inscripcion::ESTADO_PENDIENTE);

        if ($grupoId !== null) {
            $inscripcion = $aprobadas->firstWhere('grupo_id', $grupoId);
            if (!$inscripcion) {
                if ($pendientes->firstWhere('grupo_id', $grupoId)) {
                    return $this->denegar('inscripcion_pendiente', 'La inscripción a esta actividad todavía está pendiente de aprobación.', $socio);
                }

                return $this->denegar('inscripcion_inexistente', 'No hay una inscripción aprobada para esta actividad.', $socio);
            }

            return $this->evaluarInscripcion($socio, $inscripcion, $metodo, $momento);
        }

        if ($aprobadas->isEmpty()) {
            if ($pendientes->isNotEmpty()) {
                return $this->denegar('inscripcion_pendiente', 'La inscripción todavía está pendiente de aprobación.', $socio);
            }

            return $this->denegar('inscripcion_inexistente', 'No hay una inscripción aprobada para el socio.', $socio);
        }

        $horariosActuales = $aprobadas->filter(
            fn (Inscripcion $inscripcion) => $this->horarioActual($inscripcion->grupo, $momento) !== null
        );

        if ($horariosActuales->isEmpty()) {
            $grupoInactivo = $aprobadas->first(
                fn (Inscripcion $inscripcion) => !$this->grupoHabilitado($inscripcion->grupo)
            );
            if ($grupoInactivo) {
                return $this->denegar(
                    'grupo_inactivo',
                    'La actividad seleccionada no está habilitada.',
                    $socio,
                    $this->actividad($grupoInactivo->grupo)
                );
            }

            $pendienteEnHorario = $pendientes->first(
                fn (Inscripcion $inscripcion) => $this->horarioActual($inscripcion->grupo, $momento) !== null
            );
            if ($pendienteEnHorario) {
                return $this->denegar('inscripcion_pendiente', 'La inscripción todavía está pendiente de aprobación.', $socio);
            }

            return $this->denegar('fuera_de_horario', 'En este momento no hay una actividad inscripta en curso.', $socio);
        }

        $gruposHabilitados = $horariosActuales->filter(
            fn (Inscripcion $inscripcion) => $this->grupoHabilitado($inscripcion->grupo)
        );

        if ($gruposHabilitados->isEmpty()) {
            return $this->denegar(
                'grupo_inactivo',
                'La actividad seleccionada no está habilitada.',
                $socio,
                $this->actividad($horariosActuales->first()->grupo)
            );
        }

        if ($gruposHabilitados->count() > 1) {
            return $this->denegar(
                'actividad_ambigua',
                'Hay más de una actividad en curso. Seleccioná explícitamente el grupo.',
                $socio,
                ['opciones' => $gruposHabilitados->map(fn (Inscripcion $i) => $this->actividad($i->grupo))->values()->all()]
            );
        }

        return $this->evaluarInscripcion(
            $socio,
            $gruposHabilitados->first(),
            $metodo,
            $momento
        );
    }

    private function evaluarInscripcion(Socio $socio, Inscripcion $inscripcion, string $metodo, Carbon $momento): array
    {
        $grupo = $inscripcion->grupo;
        $actividad = $this->actividad($grupo);

        if (!$this->grupoHabilitado($grupo)) {
            return $this->denegar('grupo_inactivo', 'La actividad seleccionada no está habilitada.', $socio, $actividad);
        }

        $horario = $this->horarioActual($grupo, $momento);
        if (!$horario) {
            return $this->denegar('fuera_de_horario', 'La actividad no corresponde al día u horario actual.', $socio, $actividad);
        }

        $cobertura = $this->pagoService->consultarCoberturaInscripcion($inscripcion, $momento->toDateString());
        if (!$cobertura['vigente']) {
            $codigo = $cobertura['estado'] === PagoDetalle::COBERTURA_VENCIDA
                ? 'cuota_vencida'
                : 'sin_cobertura';
            $mensaje = $codigo === 'cuota_vencida'
                ? 'La cobertura de esta actividad está vencida.'
                : 'No hay una cobertura vigente para esta actividad.';

            return $this->denegar($codigo, $mensaje, $socio, $actividad, $horario);
        }

        $evaluacionMedica = $this->certificadoMedicoService->evaluarRequisitoMedico($socio, $momento->toDateString());
        if ($evaluacionMedica['decision'] === CertificadoMedicoService::ESTADO_PENDIENTE) {
            return $this->denegar('decision_medica_pendiente', 'El acceso requiere que la decisión médica esté resuelta.', $socio, $actividad, $horario);
        }

        if (!$evaluacionMedica['cumple']) {
            [$codigo, $mensaje] = match ($evaluacionMedica['caso']) {
                'E' => ['certificado_vencido', 'El certificado médico obligatorio está vencido.'],
                'F' => ['certificado_pendiente_aprobacion', 'El certificado médico presentado está pendiente de aprobación de la dueña.'],
                'G' => ['certificado_rechazado', 'El certificado médico presentado no fue aprobado.'],
                'H' => ['certificado_faltante', 'El certificado médico todavía no está vigente.'],
                default => ['certificado_faltante', 'Se requiere un certificado médico vigente para esta actividad.'],
            };

            return $this->denegar($codigo, $mensaje, $socio, $actividad, $horario);
        }

        $resultado = [
            'autorizado' => true,
            'codigo' => 'acceso_autorizado',
            'mensaje' => 'Acceso autorizado.',
            'socio' => $socio,
            'actividad' => $actividad,
            'dia' => $this->diaSemana($momento),
            'horario' => $horario,
            'asistencia' => null,
        ];

        $asistenciaAbierta = Asistencia::query()
            ->where('socio_id', $socio->id)
            ->whereNull('hora_salida')
            ->exists();
        if ($asistenciaAbierta) {
            return $this->denegar('ingreso_duplicado', 'Ya existe un ingreso abierto para este socio.', $socio, $actividad, $horario);
        }

        return $resultado;
    }

    private function resolverCredencial(string $identificador, string $metodo): ?Socio
    {
        if ($identificador === '' || !in_array($metodo, [self::METODO_PIN, self::METODO_QR], true)) {
            return null;
        }

        $columna = $metodo === self::METODO_PIN ? 'pin' : 'qr_token';

        return Socio::query()
            ->where($columna, $identificador)
            ->where('estado_credencial', 'activo')
            ->first();
    }

    private function credencialActivaPerteneceAlSocio(Socio $socio, string $metodo): bool
    {
        return $socio->estado_credencial === 'activo'
            && ($metodo === self::METODO_PIN
                ? $socio->pin !== null
                : ($metodo === self::METODO_QR && $socio->qr_token !== null));
    }

    private function credencialRevocada(string $identificador, string $metodo): bool
    {
        if (!in_array($metodo, [self::METODO_PIN, self::METODO_QR], true) || $identificador === '') {
            return false;
        }

        $columna = $metodo === self::METODO_PIN ? 'pin_revocado_hash' : 'qr_token_revocado_hash';
        $hash = hash_hmac('sha256', $identificador, (string) config('app.key'));

        return Socio::query()->where($columna, $hash)->exists();
    }

    private function grupoHabilitado(?Grupo $grupo): bool
    {
        return $grupo?->estaHabilitado() ?? false;
    }

    private function horarioActual(?Grupo $grupo, Carbon $momento): ?array
    {
        if (!$grupo) {
            return null;
        }

        $dia = $this->diaSemana($momento);
        $hora = $momento->format('H:i:s');

        $horario = $grupo->horarios->first(function (GrupoHorario $horario) use ($dia, $hora) {
            return $this->normalizarDia($horario->dia_semana) === $dia
                && $horario->hora_inicio <= $hora
                && $horario->hora_fin > $hora;
        });

        if (!$horario) {
            return null;
        }

        return [
            'dia' => $dia,
            'hora_inicio' => substr($horario->hora_inicio, 0, 5),
            'hora_fin' => substr($horario->hora_fin, 0, 5),
        ];
    }

    private function actividad(?Grupo $grupo): ?array
    {
        if (!$grupo) {
            return null;
        }

        return [
            'grupo_id' => $grupo->id,
            'grupo' => $grupo->nombre,
            'disciplina_id' => $grupo->disciplina?->id,
            'disciplina' => $grupo->disciplina?->nombre,
        ];
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

    private function momentoLocal(?Carbon $momento): Carbon
    {
        return $momento
            ? $momento->copy()->setTimezone(config('app.timezone'))
            : Carbon::now(config('app.timezone'));
    }

    private function metodoAsistencia(string $metodo, ?string $origen = null): string
    {
        return $origen ?? ($metodo === self::METODO_QR ? 'qr_movil' : 'pin_terminal');
    }

    private function denegar(
        string $codigo,
        string $mensaje,
        ?Socio $socio = null,
        ?array $actividad = null,
        ?array $horario = null
    ): array {
        return [
            'autorizado' => false,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'socio' => $socio,
            'actividad' => $actividad,
            'dia' => null,
            'horario' => $horario,
            'asistencia' => null,
        ];
    }

    private function auditarAcceso(
        array $resultado,
        string $metodo,
        ?int $grupoId,
        ?int $operadorId,
        ?Socio $socio,
        ?string $origen = null
    ): void {
        Auditoria::registrar(
            tipoEvento: 'acceso',
            accion: $resultado['autorizado'] ? 'acceso_autorizado' : 'acceso_denegado',
            entidad: $socio,
            datosAntes: null,
            datosDespues: [
                'resultado' => $resultado['codigo'],
                'metodo' => $metodo,
                'grupo_id' => $grupoId,
                'asistencia_id' => $resultado['asistencia']?->id,
                'operador_id' => $operadorId,
            ],
            userId: $operadorId
        );

        event(GymRealtimeUpdate::access($resultado, $metodo, $socio, $origen));
    }
}
