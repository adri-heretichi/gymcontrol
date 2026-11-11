<?php

namespace App\Livewire\Solicitudes;

use App\Models\Auditoria;
use App\Models\Solicitud;
use App\Models\Socio;
use App\Services\CertificadoMedicoService;
use App\Services\SolicitudService;
use DomainException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class GestionarSolicitudes extends Component
{
    use WithPagination;

    public string $estadoFiltro = Solicitud::ESTADO_PENDIENTE;
    public string $buscar = '';
    public string $socioId = '';

    public ?int $solicitudId = null;
    public string $respuesta = '';
    public string $decisionMedica = '';
    public string $correccionFechaIngreso = '';
    public string $correccionHoraIngreso = '';
    public string $correccionFechaSalida = '';
    public string $correccionHoraSalida = '';
    public string $correccionEstadoClase = '';
    public string $correccionHoraClase = '';

    protected $queryString = [
        'estadoFiltro' => ['except' => 'pendiente'],
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', Solicitud::class);
        $this->socioId = (string) request()->query('socio_id', '');
    }

    public function updatingEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function crearRevisionMedica(): void
    {
        Gate::authorize('create', Solicitud::class);
        $datos = $this->validate([
            'socioId' => ['required', 'integer', 'exists:socios,id'],
        ]);

        app(CertificadoMedicoService::class)->informarRevisionADuena(
            Socio::query()->findOrFail($datos['socioId']),
            auth()->user()
        );

        session()->flash('message', 'Solicitud de revisión médica enviada a la dueña. No modifica la decisión ni habilita el acceso.');
    }

    public function abrirResolucion(int $solicitudId): void
    {
        $solicitud = Solicitud::query()->findOrFail($solicitudId);
        Gate::authorize('resolver', $solicitud);
        abort_unless($solicitud->estaPendiente(), 409);

        $this->solicitudId = $solicitud->id;
        $this->respuesta = '';
        $this->decisionMedica = '';
        $this->correccionFechaIngreso = '';
        $this->correccionHoraIngreso = '';
        $this->correccionFechaSalida = '';
        $this->correccionHoraSalida = '';
        $this->correccionEstadoClase = '';
        $this->correccionHoraClase = '';

        $contexto = $solicitud->contextoCorreccion() ?? [];
        if (($contexto['recurso'] ?? null) === 'permanencia') {
            $registro = \App\Models\Asistencia::query()->find($contexto['registro_id'] ?? 0);
            if ($registro && $registro->socio_id === $solicitud->socio_id) {
                $this->correccionFechaIngreso = $registro->fecha?->toDateString() ?? '';
                $this->correccionHoraIngreso = $registro->hora_ingreso;
                $this->correccionFechaSalida = $registro->fecha_salida?->toDateString() ?? '';
                $this->correccionHoraSalida = $registro->hora_salida ?? '';
            }
        } elseif (($contexto['recurso'] ?? null) === 'clase') {
            $registro = \App\Models\AsistenciaClase::query()->find($contexto['registro_id'] ?? 0);
            if ($registro && $registro->socio_id === $solicitud->socio_id) {
                $this->correccionEstadoClase = $registro->estado;
                $this->correccionHoraClase = $registro->hora_marcado ?? '';
            }
        }
    }

    public function resolver(string $decision): void
    {
        abort_unless($this->solicitudId, 404);
        abort_unless(in_array($decision, [Solicitud::ESTADO_APROBADA, Solicitud::ESTADO_RECHAZADA], true), 422);
        $solicitud = Solicitud::query()->findOrFail($this->solicitudId);
        Gate::authorize('resolver', $solicitud);

        $rules = [
            'respuesta' => ['required', 'string', 'max:1000'],
            'decisionMedica' => [
                $decision === Solicitud::ESTADO_APROBADA && $solicitud->tipo === Solicitud::TIPO_EXCEPCION_MEDICA
                    ? 'required'
                    : 'nullable',
                'in:requerido,no_requerido',
            ],
        ];

        $contextoCorreccion = $solicitud->contextoCorreccion();
        $recurso = $contextoCorreccion['recurso'] ?? null;
        if ($decision === Solicitud::ESTADO_APROBADA && $recurso === 'permanencia') {
            $rules += [
                'correccionFechaIngreso' => ['required', 'date'],
                'correccionHoraIngreso' => ['required', 'date_format:H:i:s'],
                'correccionFechaSalida' => ['nullable', 'required_with:correccionHoraSalida', 'date'],
                'correccionHoraSalida' => ['nullable', 'required_with:correccionFechaSalida', 'date_format:H:i:s'],
            ];
        } elseif ($decision === Solicitud::ESTADO_APROBADA && $recurso === 'clase') {
            $rules += [
                'correccionEstadoClase' => ['required', 'in:puntual,tarde,pendiente,ausencia_confirmada,ausente_justificado,cancelado'],
                'correccionHoraClase' => [
                    in_array($this->correccionEstadoClase, ['ausencia_confirmada'], true) ? 'nullable' : 'required',
                    'date_format:H:i:s',
                ],
            ];
        }

        $validated = $this->validate($rules);
        $correccion = match ($recurso) {
            'permanencia' => [
                'fecha_ingreso' => $validated['correccionFechaIngreso'],
                'hora_ingreso' => $validated['correccionHoraIngreso'],
                'fecha_salida' => $validated['correccionFechaSalida'] ?: null,
                'hora_salida' => $validated['correccionHoraSalida'] ?: null,
            ],
            'clase' => [
                'estado' => $validated['correccionEstadoClase'],
                'hora_marcado' => $validated['correccionHoraClase'] ?: null,
            ],
            default => null,
        };

        try {
            app(SolicitudService::class)->resolver(
                $solicitud,
                auth()->user(),
                $decision,
                $validated['respuesta'],
                $validated['decisionMedica'] ?: null,
                $correccion
            );
            $this->reset([
                'solicitudId',
                'respuesta',
                'decisionMedica',
                'correccionFechaIngreso',
                'correccionHoraIngreso',
                'correccionFechaSalida',
                'correccionHoraSalida',
                'correccionEstadoClase',
                'correccionHoraClase',
            ]);
            session()->flash('message', 'La solicitud se resolvió y quedó registrada en auditoría.');
        } catch (DomainException $exception) {
            $this->addError('respuesta', $exception->getMessage());
        }
    }

    #[On('gym-realtime-updated')]
    public function refreshRealtimeRequests(): void
    {
    }

    public function render()
    {
        Gate::authorize('viewAny', Solicitud::class);

        $usuario = auth()->user();
        $query = Solicitud::query()
            ->with(['socio:id,nombre,apellido', 'solicitante:id,name', 'revisadoPor:id,name', 'grupo:id,nombre', 'grupoDestino:id,nombre'])
            ->when(!$usuario->esDuena(), fn ($q) => $q->where('solicitante_id', $usuario->id))
            ->when($this->estadoFiltro !== 'todas', fn ($q) => $q->where('estado', $this->estadoFiltro))
            ->when($this->buscar !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereHas('socio', function ($socio) {
                        $socio->where('nombre', 'like', '%' . $this->buscar . '%')
                            ->orWhere('apellido', 'like', '%' . $this->buscar . '%');
                    })->orWhere('tipo', 'like', '%' . $this->buscar . '%');
                });
            });

        $solicitudes = $query->orderByDesc('created_at')->paginate(15);
        $movimientosRecepcion = collect();
        if ($usuario->esRecepcionista()) {
            $movimientosRecepcion = Auditoria::query()
                ->where('user_id', $usuario->id)
                ->whereIn('tipo_evento', ['administrativo', 'acceso', 'asistencia', 'asistencia_fisica', 'asistencia_clase'])
                ->latest('created_at')
                ->limit(50)
                ->get(['id', 'tipo_evento', 'accion', 'created_at']);
        }

        return view('livewire.solicitudes.gestionar-solicitudes', [
            'solicitudes' => $solicitudes,
            'socios' => $usuario->esRecepcionista()
                ? Socio::query()->select(['id', 'nombre', 'apellido'])->where('estado', 'activo')->orderBy('apellido')->orderBy('nombre')->limit(300)->get()
                : collect(),
            'movimientosRecepcion' => $movimientosRecepcion,
        ])->layout('layouts.app');
    }
}
