<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Disciplina extends Model
{
    use HasFactory;

    public const PLANES_FENIX = [
        'Krav Maga Kids',
        'Kapap Krav Maga',
        'Kickboxing Muay Thai',
        'Funcional',
    ];

    protected $table = 'disciplinas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'estado',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
    ];

    /**
     * Relación de Uno a Muchos: Una disciplina tiene múltiples grupos.
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'disciplina_id');
    }

    /**
     * Relación a través de grupos: Inscripciones de esta disciplina.
     */
    public function inscripciones(): HasManyThrough
    {
        return $this->hasManyThrough(Inscripcion::class, Grupo::class, 'disciplina_id', 'grupo_id');
    }

    /**
     * Verifica si la disciplina puede ser eliminada físicamente (no debe poseer grupos vinculados).
     */
    public function puedeEliminarse(): bool
    {
        return !$this->grupos()->exists();
    }
}
