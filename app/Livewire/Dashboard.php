<?php

namespace App\Livewire;

use App\Models\Socio;
use App\Models\Pago;
use App\Models\Asistencia;
use Livewire\Component;

class Dashboard extends Component
{
    public int $totalSocios = 0;
    public int $sociosActivos = 0;
    public int $sociosVencidos = 0;
    public float $ingresosMes = 0.0;
    public int $sociosEnSala = 0;
    public $ultimosSocios = [];
    public $ultimasAsistencias = [];

    /**
     * Calcula dinámicamente las estadísticas según el rol del usuario autenticado
     */
    public function mount()
    {
        $this->updateStats();
    }

    /**
     * Actualiza las estadísticas en tiempo real
     */
    public function updateStats()
    {
        if (auth()->check() && auth()->user()->rol === 'admin') {
            // El administrador visualiza estadísticas completas del gimnasio
            $this->totalSocios = Socio::count();
            $this->sociosActivos = Socio::where('estado', 'activo')->count();
            $this->sociosVencidos = Socio::whereNotNull('fecha_vencimiento')
                ->where('fecha_vencimiento', '<', now())
                ->count();
            
            $this->ingresosMes = Pago::whereMonth('fecha_pago', today()->month)
                ->whereYear('fecha_pago', today()->year)
                ->sum('importe');

            $this->sociosEnSala = Asistencia::whereNull('hora_salida')
                ->where('fecha', today())
                ->count();

            $this->ultimosSocios = Socio::with('membresia')
                ->latest()
                ->take(5)
                ->get();

            $this->ultimasAsistencias = Asistencia::with('socio')
                ->latest()
                ->take(5)
                ->get();
        } else {
            // El recepcionista visualiza información orientada a la operación diaria
            $this->sociosActivos = Socio::where('estado', 'activo')->count();
            
            $this->sociosEnSala = Asistencia::whereNull('hora_salida')
                ->where('fecha', today())
                ->count();

            $this->ultimasAsistencias = Asistencia::with('socio')
                ->latest()
                ->take(5)
                ->get();
        }
    }

    public function render()
    {
        $this->updateStats();
        return view('livewire.dashboard')->layout('layouts.app');
    }
}
