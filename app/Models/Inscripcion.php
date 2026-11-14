<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADA = 'aprobada';
    public const ESTADO_BAJA = 'baja';
    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADOS_VALIDOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_APROBADA,
        self::ESTADO_BAJA,
        self::ESTADO_RECHAZADA,
    ];

    protected $fillable = [
        'socio_id',
        'grupo_id',
        'fecha_inscripcion',
        'estado',
        'aprobado_por',
        'aprobado_at',
        'fecha_baja',
        'motivo_baja',
        'baja_por',
        'motivo_rechazo',
        'rechazado_por',
        'rechazado_at',
    ];

    protected $casts = [
        'fecha_inscripcion' => 'date',
        'fecha_baja' => 'date',
        'aprobado_at' => 'datetime',
        'rechazado_at' => 'datetime',
    ];

    /**
     * Relación de Muchos a Uno: La inscripción pertenece a un socio.
     */
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    /**
     * Relación de Muchos a Uno: La inscripción corresponde a un grupo de disciplina.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Relación de Muchos a Uno: Usuario (dueña) que aprobó la inscripción.
     */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Relación de Muchos a Uno: Usuario que registró la baja.
     */
    public function bajaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baja_por');
    }

    /**
     * Relación de Muchos a Uno: Usuario (dueña) que rechazó la solicitud.
     */
    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }

    /**
     * Relación de Uno a Muchos: Períodos y cuotas abonadas para esta inscripción.
     */
    public function pagoDetalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class, 'inscripcion_id');
    }

    /**
     * Verifica si la inscripción está aprobada y ocupa cupo.
     */
    public function estaAprobada(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }

    /**
     * Determina si la inscripción ocupa efectivamente una plaza del grupo.
     * Únicamente las inscripciones aprobadas vigentes ocupan cupo.
     */
    public function ocupaCupo(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function estaDadaDeBaja(): bool
    {
        return $this->estado === self::ESTADO_BAJA;
    }

    public function estaRechazada(): bool
    {
        return $this->estado === self::ESTADO_RECHAZADA;
    }

    /**
     * Verifica si esta inscripción tiene su período abonado y vigente a la fecha dada.
     */
    public function tieneCoberturaVigente(?string $fecha = null): bool
    {
        $fechaConsulta = $fecha ?? now()->toDateString();

        return $this->pagoDetalles()
            ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
            ->where('fecha_inicio_cobertura', '<=', $fechaConsulta)
            ->where('fecha_fin_cobertura', '>=', $fechaConsulta)
            ->whereHas('pago', fn ($query) => $query
                ->where('estado', Pago::ESTADO_ACTIVO)
                ->where('socio_id', $this->socio_id))
            ->exists();
    }
}
