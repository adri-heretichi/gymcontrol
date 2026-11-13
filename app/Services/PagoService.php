<?php

namespace App\Services;

use App\Events\GymRealtimeUpdate;
use App\Models\Auditoria;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * PagoService – Servicio centralizado de pagos y coberturas.
 *
 * Toda la lógica de negocio relacionada con:
 *   - Registro de pagos y sus detalles.
 *   - Consulta de cobertura por inscripción.
 *   - Verificación de vigencia.
 *   - Renovaciones.
 *   - Detección de duplicaciones.
 *   - Correcciones administrativas auditadas.
 *   - Datos para el futuro motor de acceso.
 *
 * IMPORTANTE:
 *   - Un pago nunca aprueba automáticamente una inscripción.
 *   - Un pago nunca emite credenciales.
 *   - Las coberturas son independientes por inscripción.
 *   - Los pagos históricos (sin operador_id) se conservan intactos.
 */
class PagoService
{
    // ─── Constantes internas ───────────────────────────────────────────────────

    /** Margen de tolerancia en días para detectar superposición de coberturas. */
    private const TOLERANCIA_SUPERPOSICION_DIAS = 0;

    // ─── 1. Registro de pagos ──────────────────────────────────────────────────

    /**
     * Registra un pago con uno o varios detalles de cobertura.
     *
     * @param  Socio  $socio         Socio al que pertenece el pago.
     * @param  array  $detalles      Array de detalles. Cada elemento debe tener:
     *                               - inscripcion_id (int, approved or pending enrollment)
     *                               - concepto       (string)
     *                               - fecha_inicio   (string Y-m-d)
     *                               - fecha_fin      (string Y-m-d)
     *                               - subtotal       (numeric, positivo)
     * @param  string $medioPago     Clave del medio de pago.
     * @param  User   $operador      Usuario que registra el cobro.
     * @param  string|null $referencia Referencia administrativa opcional.
     * @param  string|null $fechaPago  Fecha del pago (default: hoy).
     *
     * @throws \InvalidArgumentException si algún detalle es inválido.
     * @throws \DomainException si se detecta una cobertura duplicada.
     */
    public function registrarPago(
        Socio $socio,
        array $detalles,
        string $medioPago,
        User $operador,
        ?string $referencia = null,
        ?string $fechaPago  = null
    ): Pago {
        Gate::forUser($operador)->authorize('create', Pago::class);

        $this->validarDetalles($detalles);
        $this->validarMedioPago($medioPago);

        $coberturasSolicitadas = [];
        foreach ($detalles as $det) {
            $clave = implode('|', [
                (int) $det['inscripcion_id'],
                Carbon::parse($det['fecha_inicio'])->toDateString(),
                Carbon::parse($det['fecha_fin'])->toDateString(),
            ]);
            if (isset($coberturasSolicitadas[$clave])) {
                throw new \DomainException('El pago contiene dos detalles para la misma inscripción y período.');
            }
            $coberturasSolicitadas[$clave] = true;
        }

        $fechaPagoFinal = $fechaPago
            ? Carbon::parse($fechaPago)->toDateString()
            : Carbon::now(config('app.timezone'))->toDateString();

        $importeTotal = collect($detalles)->sum('subtotal');
        $inscripcionIds = collect($detalles)->pluck('inscripcion_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();

        return DB::transaction(function () use (
            $socio, $detalles, $medioPago, $operador, $referencia,
            $fechaPagoFinal, $importeTotal, $inscripcionIds
        ) {
            $inscripciones = Inscripcion::query()
                ->whereIn('id', $inscripcionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($detalles as $det) {
                $inscripcion = $inscripciones->get((int) $det['inscripcion_id']);
                if (!$inscripcion || (int) $inscripcion->socio_id !== (int) $socio->id) {
                    throw new \InvalidArgumentException('La inscripción seleccionada no pertenece al socio del pago.');
                }

                if (!in_array($inscripcion->estado, [
                    Inscripcion::ESTADO_APROBADA,
                    Inscripcion::ESTADO_PENDIENTE,
                ], true)) {
                    throw new \InvalidArgumentException('No se puede asociar un pago a una inscripción rechazada o dada de baja.');
                }

                $this->verificarSinDuplicacion(
                    $inscripcion->id,
                    $det['fecha_inicio'],
                    $det['fecha_fin'],
                    null
                );
            }

            // Crear el pago principal
            $pago = Pago::create([
                'socio_id'    => $socio->id,
                'operador_id' => $operador->id,
                'fecha_pago'  => $fechaPagoFinal,
                'importe'     => $importeTotal,
                'metodo_pago' => $medioPago,
                'referencia'  => $referencia,
                'estado'      => Pago::ESTADO_ACTIVO,
            ]);

            // Crear cada detalle de cobertura
            foreach ($detalles as $det) {
                $pago->detalles()->create([
                    'inscripcion_id'         => (int) $det['inscripcion_id'],
                    'membresia_id'           => null,
                    'concepto'               => $det['concepto'],
                    'fecha_inicio_cobertura' => $det['fecha_inicio'],
                    'fecha_fin_cobertura'    => $det['fecha_fin'],
                    'subtotal'               => $det['subtotal'],
                    'estado_cobertura'       => PagoDetalle::COBERTURA_VIGENTE,
                ]);
            }

            // Auditoría
            $this->registrarAuditoria($operador, 'pago_creado', $pago, [
                'pago_id'        => $pago->id,
                'socio_id'       => $socio->id,
                'importe'        => $importeTotal,
                'metodo_pago'    => $medioPago,
                'detalles_count' => count($detalles),
                'referencia'     => $referencia,
            ]);

            event(GymRealtimeUpdate::operationalChange('payment-recorded'));

            return $pago->fresh(['detalles']);
        });

    }

    // ─── 2. Consulta de cobertura ──────────────────────────────────────────────

    /**
     * Consulta la cobertura vigente de una inscripción en una fecha determinada.
     *
     * Devuelve un array con:
     *   - vigente         (bool)
     *   - fecha_inicio    (string|null)
     *   - fecha_fin       (string|null)
     *   - dias_restantes  (int)
     *   - estado          ('vigente' | 'proxima' | 'vencida' | 'suspendida' | 'sin_cobertura')
     *   - pago_id         (int|null) – pago activo más reciente
     *   - inscripcion_estado (string) – estado de la inscripción misma
     *
     * NOTA: La cobertura puede existir aunque la inscripción esté pendiente.
     * En ese caso vigente=true pero la inscripción no está aprobada.
     */
    public function consultarCoberturaInscripcion(
        Inscripcion $inscripcion,
        ?string $fecha = null
    ): array {
        $hoy = $fecha
            ? Carbon::parse($fecha)->toDateString()
            : Carbon::now(config('app.timezone'))->toDateString();

        // Buscar la cobertura activa más reciente (pago no anulado)
        $detalle = PagoDetalle::whereHas('pago', fn ($q) => $q
                ->where('estado', Pago::ESTADO_ACTIVO)
                ->where('socio_id', $inscripcion->socio_id))
            ->where('inscripcion_id', $inscripcion->id)
            ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
            ->vigenteEn($hoy)
            ->orderByDesc('fecha_fin_cobertura')
            ->first();

        if ($detalle) {
            return [
                'vigente'              => true,
                'fecha_inicio'         => $detalle->fecha_inicio_cobertura->toDateString(),
                'fecha_fin'            => $detalle->fecha_fin_cobertura->toDateString(),
                'dias_restantes'       => $detalle->diasRestantes($hoy),
                'estado'               => PagoDetalle::COBERTURA_VIGENTE,
                'pago_id'              => $detalle->pago_id,
                'importe'              => $detalle->subtotal,
                'inscripcion_estado'   => $inscripcion->estado,
            ];
        }

        // Sin cobertura activa para la fecha dada
        // Buscamos el último período para saber si fue pagado alguna vez
        $ultimoDetalle = PagoDetalle::whereHas('pago', fn ($q) => $q
                ->where('estado', Pago::ESTADO_ACTIVO)
                ->where('socio_id', $inscripcion->socio_id))
            ->where('inscripcion_id', $inscripcion->id)
            ->orderByDesc('fecha_fin_cobertura')
            ->first();

        $estado = $ultimoDetalle?->estadoTemporal($hoy) ?? 'sin_cobertura';
        if ($estado === PagoDetalle::COBERTURA_VIGENTE
            && $ultimoDetalle->estado_cobertura === PagoDetalle::COBERTURA_SUSPENDIDA) {
            $estado = PagoDetalle::COBERTURA_SUSPENDIDA;
        }

        return [
            'vigente'            => false,
            'fecha_inicio'       => $ultimoDetalle?->fecha_inicio_cobertura?->toDateString(),
            'fecha_fin'          => $ultimoDetalle?->fecha_fin_cobertura?->toDateString(),
            'dias_restantes'     => 0,
            'estado'             => $estado,
            'pago_id'            => $ultimoDetalle?->pago_id,
            'importe'            => $ultimoDetalle?->subtotal,
            'inscripcion_estado' => $inscripcion->estado,
        ];
    }

    /**
     * Consulta las coberturas de todas las inscripciones de un socio.
     *
     * Devuelve una Collection indexada por inscripcion_id.
     * Incluye la disciplina para fácil acceso.
     */
    public function consultarCoberturasSocio(Socio $socio, ?string $fecha = null): Collection
    {
        $inscripciones = $socio->inscripciones()
            ->with(['grupo.disciplina'])
            ->whereIn('estado', [Inscripcion::ESTADO_APROBADA, Inscripcion::ESTADO_PENDIENTE])
            ->get();

        return $inscripciones->mapWithKeys(function (Inscripcion $inscripcion) use ($fecha) {
            $cobertura = $this->consultarCoberturaInscripcion($inscripcion, $fecha);
            $cobertura['disciplina'] = $inscripcion->grupo?->disciplina?->nombre ?? 'Sin disciplina';
            $cobertura['grupo']      = $inscripcion->grupo?->nombre ?? '—';
            $cobertura['inscripcion_id'] = $inscripcion->id;

            return [$inscripcion->id => $cobertura];
        });
    }

    /**
     * Calcula la próxima fecha de inicio de cobertura para una inscripción.
     * Si ya hay cobertura activa, la fecha de inicio es el día siguiente al vencimiento actual.
     * Si no hay cobertura, devuelve hoy.
     */
    public function obtenerProximaFechaInicio(Inscripcion $inscripcion): string
    {
        $ultimoDetalle = PagoDetalle::whereHas('pago', fn ($q) => $q
                ->where('estado', Pago::ESTADO_ACTIVO)
                ->where('socio_id', $inscripcion->socio_id))
            ->where('inscripcion_id', $inscripcion->id)
            ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
            ->orderByDesc('fecha_fin_cobertura')
            ->first();

        $hoy = Carbon::now(config('app.timezone'))->startOfDay();
        if ($ultimoDetalle && $ultimoDetalle->fecha_fin_cobertura->gte($hoy)) {
            // Renovación anticipada: comienza el día siguiente al vencimiento actual
            return $ultimoDetalle->fecha_fin_cobertura->copy()->addDay()->toDateString();
        }

        return $hoy->toDateString();
    }

    // ─── 3. Recaudación ────────────────────────────────────────────────────────

    /**
     * Recaudación general filtrada por rango de fechas (exclusivo de la dueña).
     *
     * @param  User   $duena  Quien consulta (debe ser dueña; la Policy lo verifica en el controller).
     * @param  string $desde  Fecha inicial Y-m-d.
     * @param  string $hasta  Fecha final Y-m-d.
     */
    public function obtenerRecaudacionGeneral(string $desde, string $hasta, User $duena): array
    {
        Gate::forUser($duena)->authorize('verRecaudacion', Pago::class);

        $query = Pago::activos()
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->with(['socio', 'operador', 'detalles.inscripcion.grupo.disciplina']);

        $pagos       = $query->orderByDesc('fecha_pago')->get();
        $total       = $pagos->sum('importe');
        $porMetodo   = $pagos->groupBy('metodo_pago')
            ->map(fn ($g) => ['cantidad' => $g->count(), 'total' => $g->sum('importe')]);

        return [
            'pagos'      => $pagos,
            'total'      => $total,
            'por_metodo' => $porMetodo,
            'desde'      => $desde,
            'hasta'      => $hasta,
        ];
    }

    // ─── 4. Corrección administrativa ─────────────────────────────────────────

    /**
     * Anulación interna de un pago (solo la dueña).
     *
     * NO borra el pago. Lo marca como 'anulado' y queda auditado.
     * Las coberturas derivadas quedan con estado 'suspendida'.
     */
    public function anularPago(Pago $pago, User $duena, string $motivo): Pago
    {
        Gate::forUser($duena)->authorize('anular', $pago);

        if ($pago->estaAnulado()) {
            throw new \InvalidArgumentException('El pago ya está anulado.');
        }

        if (empty(trim($motivo))) {
            throw new \InvalidArgumentException('El motivo de anulación es obligatorio.');
        }

        return DB::transaction(function () use ($pago, $duena, $motivo) {
            $pago->update([
                'estado'          => Pago::ESTADO_ANULADO,
                'anulado_por'     => $duena->id,
                'anulado_at'      => now(),
                'motivo_anulacion'=> $motivo,
            ]);

            // Marcar coberturas como suspendidas (no se borran)
            $pago->detalles()->update(['estado_cobertura' => PagoDetalle::COBERTURA_SUSPENDIDA]);

            $this->registrarAuditoria($duena, 'pago_anulado', $pago, [
                'pago_id'   => $pago->id,
                'socio_id'  => $pago->socio_id,
                'importe'   => $pago->importe,
                'motivo'    => $motivo,
            ]);

            event(GymRealtimeUpdate::operationalChange('payment-voided'));

            return $pago->fresh();
        });
    }

    // ─── 5. Motor de acceso (interfaz) ────────────────────────────────────────

    /**
     * Devuelve el estado económico para el futuro motor de acceso.
     *
     * Retorna un array compacto:
     *   - inscripcion_id
     *   - disciplina
     *   - cobertura_vigente  (bool)
     *   - inscripcion_aprobada (bool) — el motor de acceso DEBE verificar ambos
     *   - fecha_fin          (string|null)
     *
     * Un socio puede tener cobertura vigente pero inscripción pendiente;
     * en ese caso el acceso NO debe ser habilitado.
     */
    public function estadoAccesoInscripcion(Inscripcion $inscripcion, ?string $fecha = null): array
    {
        $cobertura = $this->consultarCoberturaInscripcion($inscripcion, $fecha);

        return [
            'inscripcion_id'       => $inscripcion->id,
            'disciplina'           => $inscripcion->grupo?->disciplina?->nombre ?? 'Sin disciplina',
            'cobertura_vigente'    => $cobertura['vigente'],
            'inscripcion_aprobada' => $inscripcion->estaAprobada(),
            'fecha_fin'            => $cobertura['fecha_fin'],
        ];
    }

    // ─── Validaciones internas ─────────────────────────────────────────────────

    /**
     * Valida el array de detalles antes de iniciar la transacción.
     *
     * @throws \InvalidArgumentException
     */
    private function validarDetalles(array $detalles): void
    {
        if (empty($detalles)) {
            throw new \InvalidArgumentException('El pago debe tener al menos un concepto.');
        }

        foreach ($detalles as $i => $det) {
            $idx = $i + 1;

            if (empty($det['inscripcion_id']) || !filter_var($det['inscripcion_id'], FILTER_VALIDATE_INT)) {
                throw new \InvalidArgumentException("El detalle #{$idx} debe estar asociado a una inscripción.");
            }

            if (!empty($det['membresia_id'])) {
                throw new \InvalidArgumentException('Los pagos nuevos deben asociarse a una inscripción, no a una membresía general.');
            }

            if (empty($det['concepto'])) {
                throw new \InvalidArgumentException("El concepto del detalle #{$idx} es obligatorio.");
            }

            if (empty($det['fecha_inicio']) || empty($det['fecha_fin'])) {
                throw new \InvalidArgumentException("Las fechas del detalle #{$idx} son obligatorias.");
            }

            $inicio = Carbon::parse($det['fecha_inicio'])->startOfDay();
            $fin    = Carbon::parse($det['fecha_fin'])->startOfDay();

            if ($fin->lt($inicio)) {
                throw new \InvalidArgumentException(
                    "La fecha de fin del detalle #{$idx} no puede ser anterior a la de inicio."
                );
            }

            $subtotal = (float) ($det['subtotal'] ?? -1);
            if ($subtotal < 0) {
                throw new \InvalidArgumentException(
                    "El subtotal del detalle #{$idx} no puede ser negativo."
                );
            }
        }
    }

    /** Valida que el medio de pago sea uno de los admitidos. */
    private function validarMedioPago(string $medioPago): void
    {
        $validos = array_merge(
            array_keys(Pago::MEDIOS_PAGO),
            // Compatibilidad con valores históricos
            ['tarjeta', 'transferencia']
        );

        if (!in_array($medioPago, $validos, true)) {
            throw new \InvalidArgumentException(
                "Medio de pago '{$medioPago}' no reconocido."
            );
        }
    }

    /**
     * Verifica que no exista ya una cobertura activa que se superponga exactamente
     * con el período solicitado para la misma inscripción.
     *
     * Permite renovaciones legítimas con períodos diferentes.
     * Solo bloquea duplicaciones exactas o superposiciones idénticas.
     *
     * @param  int|null $excludePagoId  Excluir el propio pago en edición (para updates).
     */
    public function verificarSinDuplicacion(
        int $inscripcionId,
        string $fechaInicio,
        string $fechaFin,
        ?int $excludePagoId = null
    ): void {
        $fechaInicioStr = Carbon::parse($fechaInicio)->toDateString();
        $fechaFinStr    = Carbon::parse($fechaFin)->toDateString();

        $inscripcion = Inscripcion::findOrFail($inscripcionId);
        $query = PagoDetalle::whereHas('pago', fn ($q) => $q
                ->where('estado', Pago::ESTADO_ACTIVO)
                ->where('socio_id', $inscripcion->socio_id))
            ->where('inscripcion_id', $inscripcionId)
            ->whereDate('fecha_inicio_cobertura', $fechaInicioStr)
            ->whereDate('fecha_fin_cobertura', $fechaFinStr);

        if ($excludePagoId) {
            $query->where('pago_id', '!=', $excludePagoId);
        }

        if ($query->exists()) {
            throw new \DomainException(
                "Ya existe una cobertura activa para esta inscripción con el mismo período ({$fechaInicio} – {$fechaFin}). "
                . "Para renovar, utilizá fechas distintas."
            );
        }
    }

    // ─── Auditoría ─────────────────────────────────────────────────────────────

    private function registrarAuditoria(User $user, string $accion, ?\Illuminate\Database\Eloquent\Model $entidad = null, array $datos = []): void
    {
        Auditoria::registrar(
            tipoEvento: 'administrativo',
            accion: $accion,
            entidad: $entidad,
            datosAntes: null,
            datosDespues: $datos,
            userId: $user->id,
        );
    }
}
