<?php

namespace App\Providers;

use App\Models\AptoFisico;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Policies\AptoFisicoPolicy;
use App\Policies\AuditoriaPolicy;
use App\Policies\DisciplinaPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\InscripcionPolicy;
use App\Policies\MembresiaPolicy;
use App\Policies\PagoPolicy;
use App\Policies\SocioPolicy;
use App\Policies\SolicitudPolicy;
use App\Policies\UserPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Mapeo explícito de Policies del Núcleo Funcional.
     */
    protected array $policies = [
        Socio::class => SocioPolicy::class,
        Disciplina::class => DisciplinaPolicy::class,
        Grupo::class => GrupoPolicy::class,
        Inscripcion::class => InscripcionPolicy::class,
        Solicitud::class => SolicitudPolicy::class,
        Auditoria::class => AuditoriaPolicy::class,
        Membresia::class => MembresiaPolicy::class,
        Pago::class => PagoPolicy::class,
        User::class => UserPolicy::class,
        AptoFisico::class => AptoFisicoPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        date_default_timezone_set('America/Argentina/Buenos_Aires');

        // Cargar las migraciones de Sanctum de forma automática desde el directorio vendor
        $this->loadMigrationsFrom(base_path('vendor/laravel/sanctum/database/migrations'));

        // Forzar esquema HTTPS en producción
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Registro de Policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Definición de Gates centralizados
        Gate::define('administrar-operadores', fn(User $user) => $user->esDuena());
        Gate::define('gestionar-disciplinas', fn(User $user) => $user->esDuena());
        Gate::define('gestionar-grupos', fn(User $user) => $user->esDuena());
        Gate::define('aprobar-inscripciones', fn(User $user) => $user->esDuena());
        Gate::define('gestionar-decision-medica', fn(User $user) => $user->esDuena());
        Gate::define('controlar-acceso', fn(User $user) => $user->esDuena() || $user->esRecepcionista());
        Gate::define('registrar-asistencia-clase', fn(User $user) => $user->esDuena() || $user->esRecepcionista());
        Gate::define('gestionar-asistencias', fn(User $user) => $user->esDuena());
        Gate::define('resolver-solicitudes', fn(User $user) => $user->esDuena());
        Gate::define('ver-auditoria', fn(User $user) => $user->esDuena());
        Gate::define('ver-recaudacion', fn(User $user) => $user->esDuena());

        Gate::define('registrar-socio', fn(User $user) => $user->esDuena() || $user->esRecepcionista());
        Gate::define('registrar-pago', fn(User $user) => $user->esDuena() || $user->esRecepcionista());
        Gate::define('crear-solicitud', fn(User $user) => $user->esDuena() || $user->esRecepcionista());
    }
}
