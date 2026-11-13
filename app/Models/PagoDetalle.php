<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoDetalle extends Model
{
    use HasFactory;

    protected $table = 'pago_detalles';

    public const COBERTURA_VIGENTE    = 'vigente';
    public const COBERTURA_VENCIDA    = 'vencida';
    public const COBERTURA_PROXIMA    = 'proxima';
    public const COBERTURA_SUSPENDIDA = 'suspendida';

    protected $fillable = [
        'pago_id',
        'inscripcion_id',     // Inscripción cubierta (nullable para conceptos sin inscripción)
        'membresia_id',        // Membresía histórica (compatibilidad; nullable)
        'concepto',            // Texto descriptivo del concepto abonado
        'fecha_inicio_cobertura',
        'fecha_fin_cobertura',
        'subtotal',            // Importe correspondiente a este detalle
        'estado_cobertura',    // 'vigente' | 'vencida' | 'suspendida'
    ];

    protected $casts = [
        'fecha_inicio_cobertura' => 'date',
        'fecha_fin_cobertura'    => 'date',
        'subtotal'               => 'decimal:2',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────────

    /** Pago principal al que pertenece este detalle. */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    /** Inscripción abonada (puede ser null para conceptos generales). */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    /** Membresía general abonada (compatibilidad con modelo anterior). */
    public function membresia(): BelongsTo
    {
        return $this->belongsTo(Membresia::class, 'membresia_id');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Determina si esta cobertura está vigente en la fecha dada.
     * La evaluación se hace en el servidor usando la zona horaria de la aplicación,
     * independientemente del navegador del cliente.
     */
    public function estaVigente(?string $fecha = null): bool
    {
        $hoy = $fecha ? Carbon::parse($fecha)->startOfDay() : Carbon::now(config('app.timezone'))->startOfDay();

        return $this->fecha_inicio_cobertura->lte($hoy)
            && $this->fecha_fin_cobertura->gte($hoy);
    }

    /** Estado temporal calculado; no modifica el estado administrativo del pago. */
    public function estadoTemporal(?string $fecha = null): string
    {
        $hoy = $fecha ? Carbon::parse($fecha)->startOfDay() : Carbon::now(config('app.timezone'))->startOfDay();

        if ($this->fecha_inicio_cobertura->gt($hoy)) {
            return self::COBERTURA_PROXIMA;
        }

        return $this->fecha_fin_cobertura->lt($hoy)
            ? self::COBERTURA_VENCIDA
            : self::COBERTURA_VIGENTE;
    }

    /**
     * Días restantes de cobertura (0 si ya está vencida).
     */
    public function diasRestantes(?string $fecha = null): int
    {
        $hoy = $fecha ? Carbon::parse($fecha)->startOfDay() : Carbon::now(config('app.timezone'))->startOfDay();

        if ($this->fecha_fin_cobertura->lt($hoy)) {
            return 0;
        }

        return (int) $hoy->diffInDays($this->fecha_fin_cobertura);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Filtra detalles cuya cobertura incluye la fecha dada.
     * Úsalo para consultar si hay cobertura vigente en una fecha específica.
     */
    public function scopeVigenteEn($query, string $fecha)
    {
        return $query->whereDate('fecha_inicio_cobertura', '<=', $fecha)
                     ->whereDate('fecha_fin_cobertura', '>=', $fecha);
    }
}
