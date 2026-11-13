<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asistencia extends Model
{
    use HasFactory;

    public const MAX_PERMANENCIA_AUTOMATICA_MINUTOS = 1440;

    public function requiereRevision(?\Carbon\Carbon $momento = null): bool
    {
        if ($this->hora_salida) {
            return $this->tiempo_permanencia !== null
                && ($this->tiempo_permanencia < 0
                    || $this->tiempo_permanencia > self::MAX_PERMANENCIA_AUTOMATICA_MINUTOS);
        }

        $ingreso = \Carbon\Carbon::parse($this->fecha->toDateString() . ' ' . $this->hora_ingreso, config('app.timezone'));
        $momento ??= \Carbon\Carbon::now(config('app.timezone'));

        return $momento->lt($ingreso)
            || $ingreso->diffInMinutes($momento) > self::MAX_PERMANENCIA_AUTOMATICA_MINUTOS;
    }

    public function scopeConPermanenciaVerificable($query)
    {
        return $query->whereNotNull('hora_salida')
            ->whereBetween('tiempo_permanencia', [0, self::MAX_PERMANENCIA_AUTOMATICA_MINUTOS]);
    }

    // Indicamos que la tabla correspondiente en MySQL es "asistencias"
    protected $table = 'asistencias';

    // Atributos asignables masivamente
    protected $fillable = [
        'socio_id',
        'fecha',
        'hora_ingreso',
        'hora_salida',
        'fecha_salida',
        'tiempo_permanencia',
        'metodo_acceso',
        'dispositivo_origen',
        'operador_ingreso_id',
        'metodo_salida',
        'operador_salida_id',
    ];

    // Indica cómo deben transformarse ciertos campos al acceder a ellos
    protected $casts = [
        'fecha' => 'date',
        'fecha_salida' => 'date',
    ];

    // Relación de Muchos a Uno: Una asistencia física pertenece a un socio
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    // Relación de Uno a Muchos: Clases tomadas durante esta permanencia física
    public function asistenciasClases(): HasMany
    {
        return $this->hasMany(AsistenciaClase::class, 'asistencia_id');
    }

    // Accessor para formatear el tiempo de permanencia de forma amigable
    public function getPermanenciaFormateadaAttribute(): string
    {
        if ($this->requiereRevision()) {
            return 'Permanencia pendiente de revisión';
        }

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
