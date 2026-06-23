<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    use HasFactory;

    // Indicamos que la tabla correspondiente en MySQL es "asistencias"
    protected $table = 'asistencias';

    // Atributos asignables masivamente
    protected $fillable = [
        'socio_id',
        'fecha',
        'hora_ingreso',
        'hora_salida',
        'tiempo_permanencia',
    ];

    // Indica cómo deben transformarse ciertos campos al acceder a ellos
    protected $casts = [
        'fecha' => 'date',
    ];

    // Relación de Muchos a Uno: Una asistencia pertenece a un socio
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    // Accessor para formatear el tiempo de permanencia de forma amigable
    public function getPermanenciaFormateadaAttribute(): string
    {
        if ($this->hora_salida) {
            $minutos = $this->tiempo_permanencia;
            if ($minutos === null) {
                return 'N/A';
            }
            if ($minutos < 60) {
                return "{$minutos} min";
            }
            $horas = intdiv($minutos, 60);
            $minsRestantes = $minutos % 60;
            return $minsRestantes > 0 ? "{$horas}h {$minsRestantes}m" : "{$horas}h";
        } else {
            try {
                $ingreso = \Carbon\Carbon::parse($this->fecha->format('Y-m-d') . ' ' . $this->hora_ingreso);
                $diferencia = max(0, (int) $ingreso->diffInMinutes(\Carbon\Carbon::now()));
                return "En sala (hace {$diferencia} min)";
            } catch (\Exception $e) {
                return 'En sala';
            }
        }
    }
}
