<?php

namespace App\Livewire\Asistencias;

use App\Models\Asistencia;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class ListarAsistencias extends Component
{
    use WithPagination;

    // Filtros de búsqueda
    public $search = '';
    public $fechaDesde = '';
    public $fechaHasta = '';
    public $estado = 'todos'; // 'todos', 'en_sala', 'finalizados'

    // Propiedades para modal de edición (Solo Admin)
    public $showEditModal = false;
    public $editingId = null;
    public $fecha = '';
    public $hora_ingreso = '';
    public $hora_salida = '';
    public $socioNombre = '';

    // Propiedades para la consulta del clima
    public $temperatura;
    public $humedad;
    public $viento;
    public $iconoClima;
    public $descripcionClima;
    public $climaCargado = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'fechaDesde' => ['except' => ''],
        'fechaHasta' => ['except' => ''],
        'estado' => ['except' => 'todos'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFechaDesde()
    {
        $this->resetPage();
    }

    public function updatingFechaHasta()
    {
        $this->resetPage();
    }

    public function updatingEstado()
    {
        $this->resetPage();
    }

    public function mount()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $this->cargarClima();
    }

    /**
     * Consulta la API del clima de Open-Meteo para Formosa, Argentina.
     */
    public function cargarClima()
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get('https://api.open-meteo.com/v1/forecast?latitude=-26.18&longitude=-58.18&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=America%2FArgentina%2FSalta');

            if ($response->successful()) {
                $data = $response->json();
                $current = $data['current'] ?? null;

                if ($current) {
                    $this->temperatura = $current['temperature_2m'] ?? null;
                    $this->humedad = $current['relative_humidity_2m'] ?? null;
                    $this->viento = $current['wind_speed_10m'] ?? null;
                    $code = $current['weather_code'] ?? null;

                    $this->mapearClima($code);
                    $this->climaCargado = true;
                } else {
                    $this->climaCargado = false;
                }
            } else {
                $this->climaCargado = false;
            }
        } catch (\Exception $e) {
            $this->climaCargado = false;
        }
    }

    /**
     * Mapea el código del clima (weather_code) a su respectivo ícono y descripción.
     */
    private function mapearClima($code)
    {
        if ($code === 0) {
            $this->iconoClima = '☀️';
            $this->descripcionClima = 'Despejado';
        } elseif (in_array($code, [1, 2, 3])) {
            $this->iconoClima = '🌤️';
            $this->descripcionClima = 'Parcialmente nublado';
        } elseif (in_array($code, [45, 48])) {
            $this->iconoClima = '🌫️';
            $this->descripcionClima = 'Neblina';
        } elseif (in_array($code, [51, 53, 55, 61, 63, 65])) {
            $this->iconoClima = '🌧️';
            $this->descripcionClima = 'Lluvia';
        } elseif (in_array($code, [71, 73, 75])) {
            $this->iconoClima = '🌨️';
            $this->descripcionClima = 'Nieve';
        } elseif (in_array($code, [80, 81, 82])) {
            $this->iconoClima = '🌦️';
            $this->descripcionClima = 'Lluvias dispersas';
        } elseif (in_array($code, [95, 96, 99])) {
            $this->iconoClima = '⛈️';
            $this->descripcionClima = 'Tormenta';
        } else {
            $this->iconoClima = '🌡️';
            $this->descripcionClima = 'Variable';
        }
    }

    public function edit(Asistencia $asistencia)
    {
        // Bloqueo defensivo por rol
        abort_if(auth()->user()->rol !== 'admin', 403);

        $this->editingId = $asistencia->id;
        $this->fecha = $asistencia->fecha->format('Y-m-d');
        $this->hora_ingreso = $asistencia->hora_ingreso;
        $this->hora_salida = $asistencia->hora_salida;
        $this->socioNombre = $asistencia->socio->nombre . ' ' . $asistencia->socio->apellido;
        $this->showEditModal = true;
    }

    public function closeEdit()
    {
        $this->showEditModal = false;
        $this->reset(['editingId', 'fecha', 'hora_ingreso', 'hora_salida', 'socioNombre']);
        $this->resetValidation();
    }

    public function save()
    {
        // Bloqueo defensivo por rol
        abort_if(auth()->user()->rol !== 'admin', 403);

        $this->validate([
            'fecha' => 'required|date|before_or_equal:today',
            'hora_ingreso' => 'required',
            'hora_salida' => 'nullable',
        ]);

        if ($this->hora_salida) {
            try {
                $ingreso = Carbon::parse($this->fecha . ' ' . $this->hora_ingreso);
                $salida = Carbon::parse($this->fecha . ' ' . $this->hora_salida);
                if ($salida->lte($ingreso)) {
                    $this->addError('hora_salida', 'La hora de salida debe ser posterior a la hora de ingreso.');
                    return;
                }
                $tiempo_permanencia = (int) $ingreso->diffInMinutes($salida);
            } catch (\Exception $e) {
                $this->addError('hora_salida', 'Formato de hora inválido.');
                return;
            }
        } else {
            $tiempo_permanencia = null;
        }

        $asistencia = Asistencia::findOrFail($this->editingId);
        $asistencia->update([
            'fecha' => $this->fecha,
            'hora_ingreso' => Carbon::parse($this->hora_ingreso)->format('H:i:s'),
            'hora_salida' => $this->hora_salida ? Carbon::parse($this->hora_salida)->format('H:i:s') : null,
            'tiempo_permanencia' => $tiempo_permanencia,
        ]);

        $this->showEditModal = false;
        $this->reset(['editingId', 'fecha', 'hora_ingreso', 'hora_salida', 'socioNombre']);
        session()->flash('message', 'Asistencia actualizada correctamente.');
    }

    public function render()
    {
        $query = Asistencia::query()->with(['socio']);

        // Filtro por búsqueda de socio (Nombre, Apellido o DNI)
        if (!empty($this->search)) {
            $query->whereHas('socio', function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('apellido', 'like', '%' . $this->search . '%')
                  ->orWhere('dni', 'like', '%' . $this->search . '%');
            });
        }

        // Filtro por fecha desde
        if (!empty($this->fechaDesde)) {
            $query->where('fecha', '>=', $this->fechaDesde);
        }

        // Filtro por fecha hasta
        if (!empty($this->fechaHasta)) {
            $query->where('fecha', '<=', $this->fechaHasta);
        }

        // Filtro por estado
        if ($this->estado === 'en_sala') {
            $query->whereNull('hora_salida');
        } elseif ($this->estado === 'finalizados') {
            $query->whereNotNull('hora_salida');
        }

        // Ordenar cronológicamente descendente
        $query->orderBy('fecha', 'desc')
              ->orderBy('hora_ingreso', 'desc');

        return view('livewire.asistencias.listar-asistencias', [
            'asistencias' => $query->paginate(10)
        ])->layout('layouts.app');
    }
}
