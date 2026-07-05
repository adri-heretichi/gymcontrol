<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AptoFisico extends Model
{
    use HasFactory;

    // Indicamos que la tabla correspondiente en MySQL es "aptos_fisicos" (plural correcto en español)
    protected $table = 'aptos_fisicos';

    // Atributos asignables masivamente
    protected $fillable = [
        'socio_id',
        'archivo',
        'fecha_emision',
        'fecha_vencimiento',
        'estado',
    ];

    // Indica cómo deben transformarse ciertos campos al acceder a ellos
    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    // Relación de Muchos a Uno: Un apto físico pertenece a un socio
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }
}
