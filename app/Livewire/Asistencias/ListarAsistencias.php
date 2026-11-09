<?php

namespace App\Livewire\Asistencias;

use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\ConfiguracionGimnasio;
use App\Models\Grupo;
use App\Services\AsistenciaClaseService;
use App\Services\ControlAccesoService;
use App\Services\SolicitudService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;

class ListarAsistencias extends Component
{
    use WithPagination;

    // Filtros de búsqueda
    public $search = '';
    public $fechaDesde = '';
    public $fechaHasta = '';
    public $estado = 'todos'; // 'todos', 'en_sala', 'finalizados'
    public string $estadoClase = 'todos';

    // Propiedades para modal de edición (Solo Admin)
    public $showEditModal = false;
    public $editingId = null;
    public $fecha = '';
    public $hora_ingreso = '';
    public $fecha_salida = '';
    public $hora_salida = '';
    public $socioNombre = '';
    public bool $showClassEditModal = false;
    public ?int $editingClassId = null;
    public string $classAttendanceStatus = AsistenciaClaseService::ESTADO_PUNTUAL;
    public ?string $classAttendanceTime = null;
    public ?int $grupoClaseSeleccionado = null;
    public ?int $grupoAusenciaSeleccionado = null;
    public string $horaInicioAusencia = '';
    public string $mensajeClase = '';
    public string $mensajeCorreccion = '';
    public string $toleranciaTardanzaMinutos = '';
    public string $tipoCorreccion = '';
    public ?int $registroCorreccionId = null;
    public string $descripcionCorreccion = '';

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
        $this->resetPage('clasesPage');
    }

    public function updatingFechaDesde()
    {
        $this->resetPage();
        $this->resetPage('clasesPage');
    }

    public function updatingFechaHasta()
    {
        $this->resetPage();
        $this->resetPage('clasesPage');
    }

    public function updatingEstado()
    {
        $this->resetPage();
    }

    public function updatingEstadoClase(): void
    {
        $this->resetPage('clasesPage');
    }

    #[On('gym-realtime-updated')]
    public function refreshRealtimeAttendance(): void
    {
    }

    public function mount()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        Gate::authorize('controlar-acceso');

        $this->toleranciaTardanzaMinutos = (string) (ConfiguracionGimnasio::query()->find(1)?->tolerancia_tardanza_minutos ?? '');
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
        Gate::authorize('gestionar-asistencias');

        $this->editingId = $asistencia->id;
        $this->fecha = $asistencia->fecha->format('Y-m-d');
        $this->hora_ingreso = $asistencia->hora_ingreso;
        $this->fecha_salida = $asistencia->hora_salida
            ? ($asistencia->fecha_salida?->format('Y-m-d') ?? $asistencia->fecha->format('Y-m-d'))
            : '';
        $this->hora_salida = $asistencia->hora_salida;
        $this->socioNombre = $asistencia->socio->nombre . ' ' . $asistencia->socio->apellido;
        $this->showEditModal = true;
    }

    public function closeEdit()
    {
        $this->showEditModal = false;
        $this->reset(['editingId', 'fecha', 'hora_ingreso', 'fecha_salida', 'hora_salida', 'socioNombre']);
        $this->resetValidation();
    }

    public function save()
    {
        Gate::authorize('gestionar-asistencias');
        if ($this->hora_salida && !$this->fecha_salida) {
            $this->fecha_salida = $this->fecha;
        }

        $this->validate([
            'fecha' => 'required|date|before_or_equal:today',
            'hora_ingreso' => 'required|date_format:H:i:s',
            'fecha_salida' => 'nullable|required_with:hora_salida|date|before_or_equal:today',
            'hora_salida' => 'nullable|required_with:fecha_salida|date_format:H:i:s',
        ]);

        $asistencia = Asistencia::findOrFail($this->editingId);
        app(ControlAccesoService::class)->corregirPermanencia(
            $asistencia,
            $this->fecha,
            $this->hora_ingreso,
            $this->fecha_salida ?: null,
            $this->hora_salida ?: null,
            auth()->user()
        );

        $this->showEditModal = false;
        $this->reset(['editingId', 'fecha', 'hora_ingreso', 'fecha_salida', 'hora_salida', 'socioNombre']);
        session()->flash('message', 'Permanencia corregida y auditada.');
    }

    public function marcarAsistenciaClase(int $asistenciaId, int $grupoId): void
    {
        Gate::authorize('registrar-asistencia-clase');
        $permanencia = Asistencia::query()
            ->whereNull('hora_salida')
            ->with('socio')
            ->findOrFail($asistenciaId);
        $grupo = Grupo::findOrFail($grupoId);
        $resultado = app(AsistenciaClaseService::class)->registrarAsistencia(
            $permanencia->socio,
            $grupo,
            auth()->user(),
            Carbon::now(config('app.timezone')),
            $permanencia,
            auth()->user()->esRecepcionista() ? 'recepcion' : 'administracion'
        );

        if (!$resultado['autorizado']) {
            $this->addError('grupoClaseSeleccionado', $resultado['mensaje']);
            return;
        }

        $this->resetErrorBag('grupoClaseSeleccionado');
        $this->mensajeClase = $resultado['mensaje'];
    }

    public function guardarTolerancia(): void
    {
        Gate::authorize('gestionar-asistencias');
        $valor = trim($this->toleranciaTardanzaMinutos);
        $validated = Validator::make(
            ['toleranciaTardanzaMinutos' => $valor === '' ? null : $valor],
            ['toleranciaTardanzaMinutos' => 'nullable|integer|min:0|max:1440']
        )->validate();
        app(AsistenciaClaseService::class)->guardarTolerancia(
            ($validated['toleranciaTardanzaMinutos'] ?? '') === ''
                ? null
                : (int) $validated['toleranciaTardanzaMinutos'],
            auth()->user()
        );
        session()->flash('message', 'Configuración de tardanza actualizada.');
    }

    public function confirmarAusencias(int $grupoId, string $horaInicio): void
    {
        Gate::authorize('gestionar-asistencias');
        $grupo = Grupo::findOrFail($grupoId);
        $cantidad = app(AsistenciaClaseService::class)->confirmarAusencias(
            $grupo,
            Carbon::now(config('app.timezone'))->toDateString(),
            $horaInicio,
            auth()->user()
        );
        session()->flash('message', "Se confirmaron {$cantidad} ausencias para la clase finalizada.");
    }

    public function editarAsistenciaClase(int $asistenciaClaseId): void
    {
        Gate::authorize('gestionar-asistencias');
        $registro = AsistenciaClase::findOrFail($asistenciaClaseId);
        $this->editingClassId = $registro->id;
        $this->classAttendanceStatus = $registro->estado;
        $this->classAttendanceTime = $registro->hora_marcado;
        $this->showClassEditModal = true;
    }

    public function guardarCorreccionClase(): void
    {
        Gate::authorize('gestionar-asistencias');
        $this->validate([
            'classAttendanceStatus' => 'required|in:puntual,tarde,pendiente,ausencia_confirmada,ausente_justificado,cancelado',
            'classAttendanceTime' => 'nullable|date_format:H:i:s',
        ]);
        $registro = AsistenciaClase::findOrFail($this->editingClassId);
        app(AsistenciaClaseService::class)->corregirAsistencia(
            $registro,
            $this->classAttendanceStatus,
            $this->classAttendanceTime,
            auth()->user()
        );
        $this->showClassEditModal = false;
        $this->reset(['editingClassId', 'classAttendanceTime']);
        session()->flash('message', 'Asistencia a clase corregida y auditada.');
    }

    public function solicitarCorreccion(string $tipo, int $registroId, string $descripcion): void
    {
        Gate::authorize('crear-solicitud');
        $validated = validator(
            compact('tipo', 'descripcion'),
            ['tipo' => 'required|in:permanencia,clase', 'descripcion' => 'required|string|min:10|max:1000']
        )->validate();

        app(SolicitudService::class)->crearCorreccion(
            $validated['tipo'],
            $registroId,
            auth()->user(),
            $validated['descripcion']
        );
        $this->mensajeCorreccion = 'La solicitud de corrección fue enviada a la dueña.';
        $this->reset(['tipoCorreccion', 'registroCorreccionId', 'descripcionCorreccion']);
    }

    public function enviarSolicitudCorreccion(): void
    {
        Gate::authorize('crear-solicitud');
        if ($this->tipoCorreccion === '' || $this->registroCorreccionId === null) {
            abort(404);
        }
        $this->solicitarCorreccion(
            $this->tipoCorreccion,
            $this->registroCorreccionId,
            $this->descripcionCorreccion
        );
    }

    public function prepararSolicitudCorreccion(string $tipo, int $registroId): void
    {
        Gate::authorize('crear-solicitud');
        abort_unless(in_array($tipo, ['permanencia', 'clase'], true), 404);
        $this->tipoCorreccion = $tipo;
        $this->registroCorreccionId = $registroId;
        $this->descripcionCorreccion = '';
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
            'asistencias' => $query->paginate(10),
            'permanenciasAbiertas' => Asistencia::query()
                ->with(['socio.inscripciones.grupo.disciplina'])
                ->whereNull('hora_salida')
                ->orderByDesc('fecha')
                ->orderByDesc('hora_ingreso')
                ->limit(30)
                ->get(),
            'asistenciasClases' => AsistenciaClase::query()
                ->with(['socio', 'grupo.disciplina', 'marcadoPor'])
                ->when($this->search !== '', function ($classQuery) {
                    $classQuery->whereHas('socio', function ($socioQuery) {
                        $socioQuery->where('nombre', 'like', '%' . $this->search . '%')
                            ->orWhere('apellido', 'like', '%' . $this->search . '%')
                            ->orWhere('dni', 'like', '%' . $this->search . '%');
                    });
                })
                ->when($this->fechaDesde !== '', fn ($classQuery) => $classQuery->whereDate('fecha', '>=', $this->fechaDesde))
                ->when($this->fechaHasta !== '', fn ($classQuery) => $classQuery->whereDate('fecha', '<=', $this->fechaHasta))
                ->when($this->estadoClase !== 'todos', fn ($classQuery) => $classQuery->where('estado', $this->estadoClase))
                ->orderByDesc('fecha')
                ->orderByDesc('hora_inicio_programada')
                ->paginate(20, ['*'], 'clasesPage'),
            'gruposActivos' => Grupo::query()
                ->with('disciplina')
                ->where('estado', 'activo')
                ->orderBy('nombre')
                ->get(),
            'toleranciaConfigurada' => ConfiguracionGimnasio::query()
                ->find(1)?->tolerancia_tardanza_minutos,
        ])->layout('layouts.app');
    }
}
