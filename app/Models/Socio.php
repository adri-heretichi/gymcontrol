<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Socio extends Model
{
    use HasFactory;

    // Indicamos que la tabla correspondiente en MySQL es "socios"
    protected $table = 'socios';

    // Atributos asignables masivamente
    protected $fillable = [
        'membresia_id',
        'apellido',
        'nombre',
        'dni',
        'sexo',
        'correo',
        'celular',
        'foto',
        'token',
        'qr',
        'fecha_alta',
        'fecha_vencimiento',
        'estado',
    ];

    // Indica cómo deben transformarse ciertos campos al acceder a ellos
    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    // Relación de Muchos a Uno: Un socio pertenece a una membresía
    public function membresia(): BelongsTo
    {
        return $this->belongsTo(Membresia::class, 'membresia_id');
    }

    // Relación de Uno a Muchos: Un socio puede tener muchos pagos
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'socio_id');
    }

    // Relación de Uno a Muchos: Un socio puede tener muchas asistencias
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'socio_id');
    }

    // Relación de Uno a Muchos: Un socio puede tener uno o varios aptos físicos
    public function aptosFisicos(): HasMany
    {
        return $this->hasMany(AptoFisico::class, 'socio_id');
    }

    /**
     * Determina si el socio tiene un apto físico vigente.
     */
    public function aptoFisicoVigente(): bool
    {
        $ultimo = $this->aptosFisicos()->latest('fecha_emision')->first();
        
        if (!$ultimo) {
            return false;
        }
        
        // Si la fecha de vencimiento es anterior a hoy, está vencido
        if ($ultimo->fecha_vencimiento->isPast()) {
            if ($ultimo->estado !== 'vencido') {
                $ultimo->update(['estado' => 'vencido']);
            }
            return false;
        }
        
        return $ultimo->estado === 'vigente';
    }
}
