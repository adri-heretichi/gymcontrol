<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membresia extends Model
{
    use HasFactory;

    // Indicamos que la tabla correspondiente en MySQL es "membresias"
    protected $table = 'membresias';

    // Atributos asignables masivamente
    protected $fillable = [
        'nombre',
        'precio',
        'horas_mensuales',
        'estado',
    ];

    // Relación de Uno a Muchos: Una membresía pertenece a muchos socios
    public function socios(): HasMany
    {
        return $this->hasMany(Socio::class, 'membresia_id');
    }
}
