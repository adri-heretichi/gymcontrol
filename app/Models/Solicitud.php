<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Solicitud extends Model
{
    use HasFactory;

    protected $table = 'solicitudes';

    public const TIPO_INSCRIPCION = 'inscripcion';
    public const TIPO_CAMBIO_GRUPO = 'cambio_grupo';
    public const TIPO_BAJA_INSCRIPCION = 'baja_inscripcion';
    public const TIPO_EXCEPCION_MEDICA = 'excepcion_medica';
    public const TIPO_OTRO = 'otro';

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADA = 'aprobada';
    public const ESTADO_RECHAZADA = 'rechazada';
    public const ESTADO_CANCELADA = 'cancelada';

    protected $fillable = [
        'socio_id',
        'solicitante_id',
        'tipo',
        'inscripcion_id',
        'grupo_id',
        'grupo_destino_id',
        'datos_solicitud',
        'descripcion',
        'estado',
        'revisado_por',
        'respuesta_duena',
        'fecha_decision',
    ];

    protected $casts = [
        'fecha_decision' => 'datetime',
        'datos_solicitud' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (Solicitud $solicitud): void {
            if ($solicitud->getOriginal('estado') !== self::ESTADO_PENDIENTE) {
                throw new LogicException('Una solicitud resuelta es inmutable.');
            }
        });

        static::deleting(fn () => throw new LogicException('Las solicitudes históricas no se pueden eliminar.'));
    }

    /**
     * Relación de Muchos a Uno: Socio sobre el que versa la solicitud.
     */
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    /**
     * Relación de Muchos a Uno: Operador (recepcionista) que originó la solicitud.
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /**
     * Relación de Muchos a Uno: Usuario administrador/dueña que revisó y resolvió.
     */
    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /**
     * Relación de Muchos a Uno: Inscripción vinculada.
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    /**
     * Relación de Muchos a Uno: Grupo de origen o solicitado.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Relación de Muchos a Uno: Grupo de destino en solicitudes de cambio.
     */
    public function grupoDestino(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_destino_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    /**
     * Recupera también el formato textual utilizado por las solicitudes de
     * corrección creadas antes de guardar contexto estructurado.
     *
     * @return array{recurso: string, registro_id: int}|null
     */
    public function contextoCorreccion(): ?array
    {
        $contexto = $this->datos_solicitud;
        if (isset($contexto['recurso'], $contexto['registro_id'])
            && in_array($contexto['recurso'], ['permanencia', 'clase'], true)) {
            return [
                'recurso' => $contexto['recurso'],
                'registro_id' => (int) $contexto['registro_id'],
            ];
        }

        if (preg_match('/^Solicitud de corrección de (permanencia|clase) #(\d+):/u', $this->descripcion, $matches)) {
            return ['recurso' => $matches[1], 'registro_id' => (int) $matches[2]];
        }

        return null;
    }
}
