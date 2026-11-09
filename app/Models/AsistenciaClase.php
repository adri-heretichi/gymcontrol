<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsistenciaClase extends Model
{
    use HasFactory;

    public const ESTADO_PUNTUAL = 'puntual';
    public const ESTADO_TARDE = 'tarde';
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_AUSENCIA_CONFIRMADA = 'ausencia_confirmada';

    protected $table = 'asistencias_clases';

    protected $fillable = [
        'asistencia_id',
        'socio_id',
        'inscripcion_id',
        'grupo_id',
        'fecha',
        'hora_inicio_programada',
        'hora_fin_programada',
        'hora_marcado',
        'estado',
        'marcado_por',
        'origen_registro',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    /**
     * Relación de Muchos a Uno: Permanencia física en el gimnasio durante la cual se tomó la clase (opcional).
     */
    public function asistenciaFisica(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class, 'asistencia_id');
    }

    /**
     * Relación de Muchos a Uno: Socio que asiste a la clase.
     */
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    /**
     * Relación de Muchos a Uno: Grupo/clase al que asistió.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    /**
     * Relación de Muchos a Uno: Usuario que registró el presente (profesor o recepcionista).
     */
    public function marcadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marcado_por');
    }
}
