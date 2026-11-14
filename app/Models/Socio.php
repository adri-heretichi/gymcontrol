<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
        'pin',
        'qr_token',
        'pin_revocado_hash',
        'qr_token_revocado_hash',
        'estado_credencial',
        'credenciales_emitidas_at',
        'credenciales_revocadas_at',
        'motivo_revocacion',
        'decision_medica',
        'decision_medica_at',
        'decision_medica_por',
        'decision_medica_obs',
        'fecha_alta',
        'fecha_vencimiento',
        'estado',
    ];

    // Ocultar credenciales sensibles de serializaciones directas si se requiere
    protected $hidden = [
        'pin',
        'qr_token',
    ];

    // Indica cómo deben transformarse ciertos campos al acceder a ellos
    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_vencimiento' => 'date',
        'credenciales_emitidas_at' => 'datetime',
        'credenciales_revocadas_at' => 'datetime',
        'decision_medica_at' => 'datetime',
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

    // Relación de Uno a Muchos: Un socio puede tener muchas asistencias físicas
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'socio_id');
    }

    // Relación de Uno a Muchos: Un socio puede tener uno o varios aptos físicos
    public function aptosFisicos(): HasMany
    {
        return $this->hasMany(AptoFisico::class, 'socio_id');
    }

    // Relación de Muchos a Uno: Usuario (dueña) que tomó la decisión médica
    public function decisionMedicaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_medica_por');
    }

    // Relación de Uno a Muchos: Inscripciones del socio a grupos de disciplinas
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'socio_id');
    }

    // Relación de Muchos a Muchos: Grupos a los que pertenece el socio
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'inscripciones', 'socio_id', 'grupo_id')
            ->withPivot(['id', 'fecha_inscripcion', 'estado'])
            ->withTimestamps();
    }

    // Relación de Uno a Muchos: Listas de espera donde está registrado el socio
    public function listasEspera(): HasMany
    {
        return $this->hasMany(ListaEspera::class, 'socio_id');
    }

    // Relación de Uno a Muchos: Asistencias a clases específicas
    public function asistenciasClases(): HasMany
    {
        return $this->hasMany(AsistenciaClase::class, 'socio_id');
    }

    // Relación de Uno a Muchos: Solicitudes generadas sobre este socio
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'socio_id');
    }

    /**
     * Evalúa si el socio cumple con el requisito médico según la decisión individual de la Dueña.
     * Delega en CertificadoMedicoService para centralizar la lógica.
     */
    public function cumpleRequisitoMedico(?string $fecha = null): bool
    {
        $eval = app(\App\Services\CertificadoMedicoService::class)->evaluarRequisitoMedico($this, $fecha);
        return $eval['cumple'];
    }

    /**
     * Retorna la evaluación médica detallada (caso, mensaje, certificado, días).
     */
    public function estadoRequisitoMedico(?string $fecha = null): array
    {
        return app(\App\Services\CertificadoMedicoService::class)->evaluarRequisitoMedico($this, $fecha);
    }

    /**
     * Determina si el socio tiene un apto físico vigente (preservando compatibilidad histórica).
     */
    public function aptoFisicoVigente(): bool
    {
        $hoy = Carbon::now(config('app.timezone'))->toDateString();

        return $this->aptosFisicos()
            ->where('estado', AptoFisico::ESTADO_VIGENTE)
            ->whereDate('fecha_emision', '<=', $hoy)
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->exists();
    }

    /**
     * Verifica si una inscripción aprobada tiene cobertura vigente en un pago activo.
     */
    public function tieneCuotaVigente(): bool
    {
        $fecha = now(config('app.timezone'))->toDateString();

        return $this->inscripciones()
            ->where('estado', Inscripcion::ESTADO_APROBADA)
            ->whereHas('pagoDetalles', fn ($query) => $query
                ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
                ->whereDate('fecha_inicio_cobertura', '<=', $fecha)
                ->whereDate('fecha_fin_cobertura', '>=', $fecha)
                ->whereHas('pago', fn ($pago) => $pago
                    ->where('estado', Pago::ESTADO_ACTIVO)
                    ->where('socio_id', $this->id)))
            ->exists();
    }

    /**
     * Determina si el socio está habilitado para su PRIMERA emisión de credenciales (QR y PIN):
     * 1. Al menos una inscripción aprobada.
     * 2. Cuota abonada y vigente.
     * 3. Requisito médico individual cumplido según decisión de la dueña.
     */
    public function puedeEmitirCredenciales(): bool
    {
        return app(\App\Services\CredencialService::class)
            ->evaluarRequisitosEmision($this)['habilitado'];
    }

    /**
     * Alias semántico para evaluar los tres requisitos de credenciales.
     */
    public function cumpleRequisitosParaCredenciales(): bool
    {
        return $this->puedeEmitirCredenciales();
    }

    /**
     * Verifica si las credenciales están emitidas y en estado activo.
     */
    public function tieneCredencialesActivas(): bool
    {
        return $this->estado_credencial === 'activo'
            && !empty($this->pin)
            && !empty($this->qr_token);
    }
}
