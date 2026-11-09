<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    /**
     * Medios de pago válidos.
     * Compatibilidad: valores históricos como 'tarjeta' o 'transferencia' siguen siendo
     * aceptados en la base, pero los nuevos registros usan los valores normalizados.
     */
    public const MEDIOS_PAGO = [
        'efectivo'     => 'Efectivo',
        'debito'       => 'Tarjeta de débito',
        'credito'      => 'Tarjeta de crédito',
        'mercadopago'  => 'Mercado Pago',
    ];

    public const ESTADO_ACTIVO  = 'activo';
    public const ESTADO_ANULADO = 'anulado';

    protected $fillable = [
        'socio_id',
        'operador_id',
        'fecha_pago',
        'importe',          // Total del comprobante (suma de detalles)
        'metodo_pago',
        'referencia',       // Número de recibo o nota libre
        'estado',           // 'activo' | 'anulado'
        'anulado_por',
        'anulado_at',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_pago'  => 'date',
        'importe'     => 'decimal:2',
        'anulado_at'  => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────────

    /** Socio al que pertenece el pago. */
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    /** Operador (usuario) que registró el cobro. */
    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    /** Usuario que ejecutó la anulación interna (solo la dueña). */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * Desglose de inscripciones y períodos que cubre este pago.
     * Un pago puede tener varios detalles (un socio abona dos disciplinas en la misma operación).
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class, 'pago_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /** Solo pagos en estado activo (no anulados). */
    public function scopeActivos($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    /**
     * Etiqueta legible del medio de pago.
     * Mantiene compatibilidad con valores históricos ('tarjeta', 'transferencia').
     */
    public function etiquetaMedioPago(): string
    {
        return self::MEDIOS_PAGO[$this->metodo_pago]
            ?? ucfirst($this->metodo_pago ?? '—');
    }
}
