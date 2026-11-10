<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaEspera extends Model
{
    use HasFactory;

    protected $table = 'listas_espera';

    public const ESTADO_ESPERANDO = 'esperando';
    public const ESTADO_NOTIFICADO = 'notificado';
    public const ESTADO_PROMOVIDO = 'promovido';
    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'socio_id',
        'grupo_id',
        'posicion',
        'fecha_solicitud',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'posicion' => 'integer',
        'fecha_solicitud' => 'datetime',
    ];

    /**
     * Relación de Muchos a Uno: El registro en lista de espera pertenece a un socio.
     */
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    /**
     * Relación de Muchos a Uno: El registro en lista de espera pertenece a un grupo.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function scopeEsperando(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ESPERANDO)->orderBy('posicion', 'asc');
    }

    public function estaEsperando(): bool
    {
        return $this->estado === self::ESTADO_ESPERANDO;
    }
}
