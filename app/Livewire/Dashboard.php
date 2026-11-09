<?php

namespace App\Livewire;

use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Auditoria;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Solicitud;
use App\Models\Socio;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Dashboard extends Component
{
    #[Locked]
    public bool $esDuena = false;
    public int $totalSocios = 0;
    public int $sociosActivos = 0;
    public int $inscripcionesPendientes = 0;
    public int $inscripcionesAprobadas = 0;
    public int $sociosEnSala = 0;
    public int $ingresosHoy = 0;
    public float $recaudacionHoy = 0.0;
    public float $ingresosMes = 0.0;
    public int $asistenciasClasesHoy = 0;
    public int $cuotasVigentes = 0;
    public int $cuotasVencidas = 0;
    public int $solicitudesPendientes = 0;
    public int $decisionesMedicasPendientes = 0;
    public int $certificadosFaltantesOVencidos = 0;
    public int $accesosDenegadosRecientes = 0;
    public array $grupos = [];
    public $movimientosRecientes = [];
    public $permanenciasAbiertas = [];
    public $asistenciasClaseRecientes = [];
    public $solicitudesRecientes = [];
    public $sociosRecientes = [];
    public $accesosDenegados = [];

    public function mount(): void
    {
        $usuario = auth()->user();
        abort_unless($usuario && ($usuario->esDuena() || $usuario->esRecepcionista()), 403);
        $this->esDuena = $usuario->esDuena();
        $this->updateStats();
    }

    public function updateStats(): void
    {
        Gate::authorize('controlar-acceso');
        $this->esDuena = auth()->user()->esDuena();

        $hoy = today(config('app.timezone'))->toDateString();
        $this->totalSocios = Socio::query()->count();
        $this->sociosActivos = Socio::query()->where('estado', 'activo')->count();
        $this->inscripcionesPendientes = Inscripcion::query()
            ->where('estado', Inscripcion::ESTADO_PENDIENTE)
            ->count();
        $this->inscripcionesAprobadas = Inscripcion::query()
            ->where('estado', Inscripcion::ESTADO_APROBADA)
            ->count();
        $this->sociosEnSala = Asistencia::query()->whereNull('hora_salida')->count();
        $this->asistenciasClasesHoy = AsistenciaClase::query()
            ->whereDate('fecha', $hoy)
            ->whereIn('estado', [AsistenciaClase::ESTADO_PUNTUAL, AsistenciaClase::ESTADO_TARDE])
            ->count();
        $this->cuotasVigentes = $this->inscripcionesConCoberturaVigente($hoy);
        $this->cuotasVencidas = $this->inscripcionesConCoberturaVencida($hoy);
        $this->solicitudesPendientes = Solicitud::query()
            ->where('estado', Solicitud::ESTADO_PENDIENTE)
            ->when(!$this->esDuena, fn ($query) => $query->where('solicitante_id', auth()->id()))
            ->count();

        if ($this->esDuena) {
            $this->ingresosHoy = (int) Pago::activos()->whereDate('fecha_pago', $hoy)->count();
            $this->recaudacionHoy = (float) Pago::activos()
                ->whereDate('fecha_pago', $hoy)
                ->sum('importe');
            $this->ingresosMes = (float) Pago::activos()
                ->whereDate('fecha_pago', '>=', today()->startOfMonth()->toDateString())
                ->whereDate('fecha_pago', '<=', today()->endOfMonth()->toDateString())
                ->sum('importe');
            $this->decisionesMedicasPendientes = Socio::query()
                ->where('estado', 'activo')
                ->where('decision_medica', 'pendiente')
                ->count();
            $this->certificadosFaltantesOVencidos = Socio::query()
                ->where('estado', 'activo')
                ->where('decision_medica', 'requerido')
                ->whereDoesntHave('aptosFisicos', fn ($query) => $query->whereDate('fecha_vencimiento', '>=', $hoy))
                ->count();
            $this->accesosDenegadosRecientes = Auditoria::query()
                ->where('tipo_evento', 'acceso')
                ->where('accion', 'acceso_denegado')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            $this->accesosDenegados = Auditoria::query()
                ->where('tipo_evento', 'acceso')
                ->where('accion', 'acceso_denegado')
                ->with('usuario:id,name')
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'user_id', 'accion', 'created_at']);
            $this->sociosRecientes = Socio::query()
                ->select(['id', 'nombre', 'apellido', 'estado', 'fecha_alta'])
                ->latest('id')
                ->limit(5)
                ->get();
        }

        $this->grupos = Grupo::query()
            ->with('disciplina:id,nombre')
            ->withCount([
                'inscripciones as inscripciones_aprobadas_count' => fn ($query) => $query
                    ->where('estado', Inscripcion::ESTADO_APROBADA),
                'listasEspera as lista_espera_count' => fn ($query) => $query->where('estado', 'esperando'),
            ])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Grupo $grupo) => [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'disciplina' => $grupo->disciplina?->nombre,
                'ocupados' => $grupo->inscripciones_aprobadas_count,
                'cupo' => $grupo->cupo_maximo,
                'disponibles' => max(0, $grupo->cupo_maximo - $grupo->inscripciones_aprobadas_count),
                'en_espera' => $grupo->lista_espera_count,
            ])
            ->all();

        $this->movimientosRecientes = Asistencia::query()
            ->with('socio:id,nombre,apellido')
            ->latest('fecha')
            ->latest('hora_ingreso')
            ->limit($this->esDuena ? 10 : 50)
            ->get();
        $this->permanenciasAbiertas = Asistencia::query()
            ->with('socio:id,nombre,apellido')
            ->whereNull('hora_salida')
            ->latest('fecha')
            ->latest('hora_ingreso')
            ->limit(10)
            ->get();
        $this->asistenciasClaseRecientes = AsistenciaClase::query()
            ->with(['socio:id,nombre,apellido', 'grupo:id,nombre'])
            ->whereDate('fecha', $hoy)
            ->latest('hora_marcado')
            ->limit(8)
            ->get();
        $this->solicitudesRecientes = Solicitud::query()
            ->with('socio:id,nombre,apellido')
            ->when(!$this->esDuena, fn ($query) => $query->where('solicitante_id', auth()->id()))
            ->latest()
            ->limit(5)
            ->get(['id', 'socio_id', 'solicitante_id', 'tipo', 'estado', 'created_at', 'fecha_decision']);
    }

    #[On('gym-realtime-updated')]
    public function refreshRealtimeStats(): void
    {
        $this->updateStats();
    }

    public function render()
    {
        return view('livewire.dashboard')->layout('layouts.app');
    }

    private function inscripcionesConCoberturaVigente(string $fecha): int
    {
        return Inscripcion::query()
            ->where('estado', Inscripcion::ESTADO_APROBADA)
            ->whereHas('pagoDetalles', fn ($query) => $query
                ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
                ->whereDate('fecha_inicio_cobertura', '<=', $fecha)
                ->whereDate('fecha_fin_cobertura', '>=', $fecha)
                ->whereHas('pago', fn ($pago) => $pago->where('estado', Pago::ESTADO_ACTIVO)))
            ->count();
    }

    private function inscripcionesConCoberturaVencida(string $fecha): int
    {
        return Inscripcion::query()
            ->where('estado', Inscripcion::ESTADO_APROBADA)
            ->whereHas('pagoDetalles', fn ($query) => $query
                ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
                ->whereDate('fecha_fin_cobertura', '<', $fecha)
                ->whereHas('pago', fn ($pago) => $pago->where('estado', Pago::ESTADO_ACTIVO)))
            ->whereDoesntHave('pagoDetalles', fn ($query) => $query
                ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
                ->whereDate('fecha_inicio_cobertura', '<=', $fecha)
                ->whereDate('fecha_fin_cobertura', '>=', $fecha)
                ->whereHas('pago', fn ($pago) => $pago->where('estado', Pago::ESTADO_ACTIVO)))
            ->count();
    }
}
