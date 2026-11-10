<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Solicitudes generadas por este usuario (recepcionista).
     */
    public function solicitudesCreadas(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'solicitante_id');
    }

    /**
     * Solicitudes revisadas o aprobadas por este usuario (dueña).
     */
    public function solicitudesRevisadas(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'revisado_por');
    }

    /**
     * Inscripciones aprobadas por este usuario (dueña).
     */
    public function inscripcionesAprobadas(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'aprobado_por');
    }

    /**
     * Decisiones médicas dictadas por este usuario (dueña).
     */
    public function decisionesMedicasTomadas(): HasMany
    {
        return $this->hasMany(Socio::class, 'decision_medica_por');
    }

    /**
     * Eventos de auditoría realizados por este operador.
     */
    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class, 'user_id');
    }

    /**
     * Determina si el usuario tiene rol de Dueña / Administradora.
     */
    public function esDuena(): bool
    {
        return in_array($this->rol, ['admin', 'duena', 'dueña']);
    }

    /**
     * Determina si el usuario tiene rol de Recepcionista.
     */
    public function esRecepcionista(): bool
    {
        return $this->rol === 'recepcionista';
    }

    /**
     * Obtiene el nombre de la profesora a cargo desde la cuenta de la dueña.
     */
    public static function nombreProfesora(): string
    {
        return static::where('rol', 'admin')->value('name') ?? 'Profesora a cargo';
    }
}
