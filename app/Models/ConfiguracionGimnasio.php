<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionGimnasio extends Model
{
    protected $table = 'configuraciones_gimnasio';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'tolerancia_tardanza_minutos',
        'actualizada_por',
    ];

    protected $casts = [
        'tolerancia_tardanza_minutos' => 'integer',
    ];

    public function actualizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizada_por');
    }
}
