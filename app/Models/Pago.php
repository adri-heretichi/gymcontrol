<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    use HasFactory;

    // Indicamos que la tabla correspondiente en MySQL es "pagos"
    protected $table = 'pagos';

    // Atributos asignables masivamente
    protected $fillable = [
        'socio_id',
        'fecha_pago',
        'importe',
        'metodo_pago',
    ];

    // Indica cómo deben transformarse ciertos campos al acceder a ellos
    protected $casts = [
        'fecha_pago' => 'date',
    ];

    // Relación de Muchos a Uno: Un pago pertenece a un socio
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }
}
