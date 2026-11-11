<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos';

    protected $fillable = [
        'disciplina_id',
        'nombre',
        'profesor',
        'cupo_maximo',
        'estado',
    ];

    protected $casts = [
        'cupo_maximo' => 'integer',
    ];

    /**
     * Relación de Muchos a Uno: El grupo pertenece a una disciplina.
     */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }

    public function estaHabilitado(): bool
    {
        return $this->estado === 'activo'
            && $this->disciplina !== null
            && $this->disciplina->estado === 'activa';
    }

    /**
     * Relación de Uno a Muchos: Días y horarios del grupo.
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(GrupoHorario::class, 'grupo_id');
    }

    /**
     * Relación de Uno a Muchos: Inscripciones vinculadas al grupo.
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'grupo_id');
    }

    /**
     * Relación de Muchos a Muchos con Socios a través de inscripciones aprobadas.
     */
    public function socios(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'inscripciones', 'grupo_id', 'socio_id')
            ->withPivot(['id', 'fecha_inscripcion', 'estado'])
            ->withTimestamps();
    }

    /**
     * Relación de Uno a Muchos: Lista de espera cuando el cupo está colmado.
     */
    public function listasEspera(): HasMany
    {
        return $this->hasMany(ListaEspera::class, 'grupo_id');
    }

    /**
     * Relación de Uno a Muchos: Asistencias registradas a este grupo/clase.
     */
    public function asistenciasClases(): HasMany
    {
        return $this->hasMany(AsistenciaClase::class, 'grupo_id');
    }

    /**
     * Cantidad de alumnos inscriptos actualmente con estado aprobado que ocupan cupo.
     */
    public function alumnosInscriptosCount(): int
    {
        return $this->inscripciones()->where('estado', Inscripcion::ESTADO_APROBADA)->count();
    }

    /**
     * Determina las vacantes disponibles en el grupo.
     */
    public function vacantesDisponibles(): int
    {
        return max(0, $this->limiteCupo() - $this->alumnosInscriptosCount());
    }

    /**
     * Determina si el grupo tiene vacantes disponibles (límite estricto de 15 alumnos).
     */
    public function tieneCupoDisponible(): bool
    {
        return $this->alumnosInscriptosCount() < $this->limiteCupo();
    }

    public function limiteCupo(): int
    {
        return min(15, max(0, (int) $this->cupo_maximo));
    }

    /**
     * Representación textual estandarizada del cupo y vacantes.
     * Ejemplo: "Cupo completo: 15/15" o "12/15 – 3 vacantes".
     */
    public function getTextoCupoAttribute(): string
    {
        $inscriptos = $this->alumnosInscriptosCount();
        $vacantes = $this->vacantesDisponibles();

        if ($vacantes === 0) {
            return "Cupo completo: {$inscriptos}/{$this->limiteCupo()}";
        }

        return "{$inscriptos}/{$this->limiteCupo()} – {$vacantes} vacantes";
    }

    /**
     * Valida que no se reduzca la capacidad por debajo de la cantidad de alumnos aprobados.
     */
    public function puedeReducirCupoA(int $nuevoCupo): bool
    {
        return $nuevoCupo <= 15 && $nuevoCupo >= $this->alumnosInscriptosCount();
    }

    /**
     * Determina si el grupo puede eliminarse (no debe tener inscripciones ni asistencias históricas).
     */
    public function puedeEliminarse(): bool
    {
        return !$this->inscripciones()->exists() && !$this->asistenciasClases()->exists();
    }

    /**
     * Resumen amigable de los horarios asignados al grupo.
     */
    public function getResumenHorariosAttribute(): string
    {
        if ($this->horarios->isEmpty()) {
            return 'Sin horarios asignados';
        }

        return $this->horarios->map(function ($h) {
            $ini = substr($h->hora_inicio, 0, 5);
            $fin = substr($h->hora_fin, 0, 5);
            return "{$h->dia_semana_label} {$ini} a {$fin} hs";
        })->implode(' | ');
    }
}
