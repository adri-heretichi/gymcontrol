<?php

namespace App\Models;

use App\Services\HorarioGimnasioService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoHorario extends Model
{
    use HasFactory;

    protected $table = 'grupo_horarios';

    protected $fillable = [
        'grupo_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    /**
     * Relación de Muchos a Uno: El horario pertenece a un grupo.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function getDiaSemanaLabelAttribute(): string
    {
        return HorarioGimnasioService::DIAS_SEMANA[$this->dia_semana] ?? ucfirst($this->dia_semana);
    }

    /**
     * Verifica si este horario se superpone con un día y rango horario dado.
     */
    public function seSuperponeCon(string $dia, string $inicio, string $fin): bool
    {
        if (strtolower($this->dia_semana) !== strtolower($dia)) {
            return false;
        }

        return ($this->hora_inicio < $fin) && ($this->hora_fin > $inicio);
    }
}
