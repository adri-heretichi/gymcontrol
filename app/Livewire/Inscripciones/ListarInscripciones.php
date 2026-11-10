<?php

namespace App\Livewire\Inscripciones;

use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Solicitud;
use App\Services\InscripcionService;
use App\Services\SolicitudService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithPagination;

class ListarInscripciones extends Component
{
    use WithPagination;

    public $tab = 'pendientes'; // pendientes, aprobadas, espera, historial
    public $buscar = '';
    public $disciplinaId = '';
    public $grupoId = '';

    // Modales de acción
    public $mostrarModalRechazar = false;
    public $inscripcionIdSeleccionada = null;
    public $motivoRechazo = '';

    public $mostrarModalBaja = false;
    public $motivoBaja = '';

    public $mostrarModalCambio = false;
    public $grupoDestinoId = '';
    public $motivoCambio = '';

    protected $paginationTheme = 'tailwind';

    protected $queryString = [
        'tab' => ['except' => 'pendientes'],
        'buscar' => ['except' => ''],
        'disciplinaId' => ['except' => ''],
        'grupoId' => ['except' => ''],
    ];

    public function updatingBuscar()
    {
        $this->resetPage();
    }

    public function updatingTab()
    {
        $this->resetPage();
    }

    public function updatingDisciplinaId()
    {
        $this->grupoId = '';
        $this->resetPage();
    }

    public function cambiarTab(string $tab)
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    /**
     * Aprueba una inscripción pendiente (Exclusivo Dueña).
     */
    public function aprobar(int $inscripcionId)
    {
        if (!auth()->check()) {
            abort(403);
        }

        try {
            if (!auth()->user()->esDuena()) {
                throw new AuthorizationException('Solo la dueña del gimnasio tiene permisos para aprobar inscripciones.');
            }
            $inscripcion = Inscripcion::findOrFail($inscripcionId);
            $solicitud = Solicitud::query()
                ->where('inscripcion_id', $inscripcion->id)
                ->where('tipo', Solicitud::TIPO_INSCRIPCION)
                ->where('estado', Solicitud::ESTADO_PENDIENTE)
                ->latest('id')
                ->first();
            if ($solicitud) {
                app(SolicitudService::class)->resolver(
                    $solicitud,
                    auth()->user(),
                    Solicitud::ESTADO_APROBADA,
                    'Inscripción aprobada por la dueña.'
                );
            } else {
                app(InscripcionService::class)->aprobarInscripcion($inscripcion, auth()->user());
            }

            session()->flash('message', "Inscripción de '{$inscripcion->socio->nombre_completo}' aprobada exitosamente en el grupo '{$inscripcion->grupo->nombre}'. Ocupa 1 plaza.");
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        } catch (AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Error al aprobar: ' . $e->getMessage());
        }
    }

    public function abrirModalRechazar(int $inscripcionId)
    {
        $this->inscripcionIdSeleccionada = $inscripcionId;
        $this->motivoRechazo = '';
        $this->mostrarModalRechazar = true;
    }

    public function cerrarModalRechazar()
    {
        $this->mostrarModalRechazar = false;
        $this->inscripcionIdSeleccionada = null;
        $this->motivoRechazo = '';
    }

    public function confirmarRechazo()
    {
        if (empty(trim($this->motivoRechazo))) {
            $this->addError('motivoRechazo', 'Debe ingresar el motivo de rechazo.');
            return;
        }

        try {
            if (!auth()->user()->esDuena()) {
                throw new AuthorizationException('Solo la dueña del gimnasio tiene permisos para rechazar inscripciones.');
            }
            $inscripcion = Inscripcion::findOrFail($this->inscripcionIdSeleccionada);
            $solicitud = Solicitud::query()
                ->where('inscripcion_id', $inscripcion->id)
                ->where('tipo', Solicitud::TIPO_INSCRIPCION)
                ->where('estado', Solicitud::ESTADO_PENDIENTE)
                ->latest('id')
                ->first();
            if ($solicitud) {
                app(SolicitudService::class)->resolver(
                    $solicitud,
                    auth()->user(),
                    Solicitud::ESTADO_RECHAZADA,
                    $this->motivoRechazo
                );
            } else {
                app(InscripcionService::class)->rechazarInscripcion($inscripcion, auth()->user(), $this->motivoRechazo);
            }

            session()->flash('message', "Inscripción rechazada. Se registró el motivo en el historial.");
            $this->cerrarModalRechazar();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function abrirModalBaja(int $inscripcionId)
    {
        $this->inscripcionIdSeleccionada = $inscripcionId;
        $this->motivoBaja = '';
        $this->mostrarModalBaja = true;
    }

    public function cerrarModalBaja()
    {
        $this->mostrarModalBaja = false;
        $this->inscripcionIdSeleccionada = null;
        $this->motivoBaja = '';
    }

    public function confirmarBaja()
    {
        if (empty(trim($this->motivoBaja))) {
            $this->addError('motivoBaja', 'Debe indicar el motivo de la baja.');
            return;
        }

        try {
            $inscripcion = Inscripcion::findOrFail($this->inscripcionIdSeleccionada);
            $service = app(InscripcionService::class);
            $solicitud = auth()->user()->esDuena()
                ? Solicitud::query()
                    ->where('inscripcion_id', $inscripcion->id)
                    ->where('tipo', Solicitud::TIPO_BAJA_INSCRIPCION)
                    ->where('estado', Solicitud::ESTADO_PENDIENTE)
                    ->latest('id')
                    ->first()
                : null;
            if ($solicitud) {
                app(SolicitudService::class)->resolver(
                    $solicitud,
                    auth()->user(),
                    Solicitud::ESTADO_APROBADA,
                    $this->motivoBaja
                );
                $resultado = ['mensaje' => 'Solicitud de baja aprobada y aplicada.'];
            } else {
                $resultado = $service->registrarBaja($inscripcion, auth()->user(), $this->motivoBaja);
            }

            session()->flash('message', $resultado['mensaje']);
            $this->cerrarModalBaja();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function abrirModalCambio(int $inscripcionId)
    {
        $this->inscripcionIdSeleccionada = $inscripcionId;
        $this->grupoDestinoId = '';
        $this->motivoCambio = '';
        $this->mostrarModalCambio = true;
    }

    public function cerrarModalCambio()
    {
        $this->mostrarModalCambio = false;
        $this->inscripcionIdSeleccionada = null;
        $this->grupoDestinoId = '';
        $this->motivoCambio = '';
    }

    public function confirmarCambio()
    {
        if (!$this->grupoDestinoId) {
            $this->addError('grupoDestinoId', 'Debe seleccionar el grupo de destino.');
            return;
        }

        try {
            $inscripcion = Inscripcion::findOrFail($this->inscripcionIdSeleccionada);
            $grupoDestino = Grupo::findOrFail($this->grupoDestinoId);
            $service = app(InscripcionService::class);

            $solicitud = auth()->user()->esDuena()
                ? Solicitud::query()
                    ->where('inscripcion_id', $inscripcion->id)
                    ->where('tipo', Solicitud::TIPO_CAMBIO_GRUPO)
                    ->where('grupo_destino_id', $grupoDestino->id)
                    ->where('estado', Solicitud::ESTADO_PENDIENTE)
                    ->latest('id')
                    ->first()
                : null;
            $resultado = $solicitud
                ? [
                    'mensaje' => app(SolicitudService::class)->resolver(
                        $solicitud,
                        auth()->user(),
                        Solicitud::ESTADO_APROBADA,
                        $this->motivoCambio ?: 'Cambio de grupo aprobado por la dueña.'
                    )->respuesta_duena,
                ]
                : $service->solicitarCambioGrupo($inscripcion, $grupoDestino, auth()->user(), $this->motivoCambio);

            session()->flash('message', $resultado['mensaje']);
            $this->cerrarModalCambio();
        } catch (DomainException $e) {
            $this->addError('grupoDestinoId', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Incorpora a un alumno desde la Lista de Espera (Exclusivo Dueña).
     */
    public function incorporarDeEspera(int $esperaId)
    {
        if (!auth()->check()) {
            abort(403);
        }

        try {
            $espera = ListaEspera::findOrFail($esperaId);
            $service = app(InscripcionService::class);
            $service->incorporarDesdeListaEspera($espera, auth()->user());

            session()->flash('message', "Socio '{$espera->socio->nombre_completo}' incorporado exitosamente al grupo '{$espera->grupo->nombre}' desde la lista de espera.");
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Cancela un turno de Lista de Espera.
     */
    public function cancelarEspera(int $esperaId)
    {
        try {
            $espera = ListaEspera::findOrFail($esperaId);
            $service = app(InscripcionService::class);
            $service->cancelarListaEspera($espera, auth()->user(), 'Cancelado desde interfaz');

            session()->flash('message', "Registro de lista de espera cancelado. Se reordenaron las posiciones restantes.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $disciplinas = Disciplina::orderBy('nombre')->get();
        $gruposFiltro = $this->disciplinaId ? Grupo::where('disciplina_id', $this->disciplinaId)->orderBy('nombre')->get() : collect();

        // Grupos disponibles para modal de cambio (excluyendo el grupo actual)
        $gruposDestinoDisponibles = collect();
        if ($this->inscripcionIdSeleccionada) {
            $inscripcionActual = Inscripcion::find($this->inscripcionIdSeleccionada);
            if ($inscripcionActual) {
                $gruposDestinoDisponibles = Grupo::where('id', '!=', $inscripcionActual->grupo_id)
                    ->where('estado', 'activo')
                    ->whereHas('disciplina', fn ($query) => $query->where('estado', 'activo'))
                    ->whereHas('horarios')
                    ->with(['disciplina', 'horarios', 'inscripciones'])
                    ->orderBy('disciplina_id')
                    ->get();
            }
        }

        // Conteo de items para las pestañas
        $conteoPendientes = Inscripcion::where('estado', Inscripcion::ESTADO_PENDIENTE)->count();
        $conteoAprobadas = Inscripcion::where('estado', Inscripcion::ESTADO_APROBADA)->count();
        $conteoEspera = ListaEspera::where('estado', ListaEspera::ESTADO_ESPERANDO)->count();
        $conteoHistorial = Inscripcion::whereIn('estado', [Inscripcion::ESTADO_BAJA, Inscripcion::ESTADO_RECHAZADA])->count();

        // Consulta de datos según la pestaña activa
        $datos = null;

        if ($this->tab === 'espera') {
            $datos = ListaEspera::with(['socio', 'grupo.disciplina', 'grupo.horarios'])
                ->where('estado', ListaEspera::ESTADO_ESPERANDO)
                ->when($this->grupoId, fn($q) => $q->where('grupo_id', $this->grupoId))
                ->when($this->disciplinaId, fn($q) => $q->whereHas('grupo', fn($g) => $g->where('disciplina_id', $this->disciplinaId)))
                ->when($this->buscar, function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereHas('socio', function ($s) {
                            $s->where('nombre', 'like', '%' . $this->buscar . '%')
                              ->orWhere('apellido', 'like', '%' . $this->buscar . '%')
                              ->orWhere('dni', 'like', '%' . $this->buscar . '%');
                        })->orWhereHas('grupo', function ($g) {
                            $g->where('nombre', 'like', '%' . $this->buscar . '%');
                        });
                    });
                })
                ->orderBy('grupo_id', 'asc')
                ->orderBy('posicion', 'asc')
                ->paginate(10);
        } else {
            $query = Inscripcion::with(['socio', 'grupo.disciplina', 'grupo.horarios', 'aprobadoPor', 'bajaPor', 'rechazadoPor'])
                ->when($this->grupoId, fn($q) => $q->where('grupo_id', $this->grupoId))
                ->when($this->disciplinaId, fn($q) => $q->whereHas('grupo', fn($g) => $g->where('disciplina_id', $this->disciplinaId)))
                ->when($this->buscar, function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereHas('socio', function ($s) {
                            $s->where('nombre', 'like', '%' . $this->buscar . '%')
                              ->orWhere('apellido', 'like', '%' . $this->buscar . '%')
                              ->orWhere('dni', 'like', '%' . $this->buscar . '%');
                        })->orWhereHas('grupo', function ($g) {
                            $g->where('nombre', 'like', '%' . $this->buscar . '%');
                        });
                    });
                });

            if ($this->tab === 'pendientes') {
                $query->where('estado', Inscripcion::ESTADO_PENDIENTE)->orderBy('fecha_inscripcion', 'asc');
            } elseif ($this->tab === 'aprobadas') {
                $query->where('estado', Inscripcion::ESTADO_APROBADA)->orderBy('aprobado_at', 'desc');
            } else { // historial
                $query->whereIn('estado', [Inscripcion::ESTADO_BAJA, Inscripcion::ESTADO_RECHAZADA])->orderBy('updated_at', 'desc');
            }

            $datos = $query->paginate(10);
        }

        $primerasEsperas = collect();
        if ($this->tab === 'espera') {
            $gruposEnPagina = $datos->getCollection()->pluck('grupo_id')->unique();
            $primerasEsperas = ListaEspera::query()
                ->where('estado', ListaEspera::ESTADO_ESPERANDO)
                ->whereIn('grupo_id', $gruposEnPagina)
                ->orderBy('grupo_id')
                ->orderBy('posicion')
                ->orderBy('fecha_solicitud')
                ->orderBy('id')
                ->get(['id', 'grupo_id'])
                ->groupBy('grupo_id')
                ->map(fn ($esperas) => $esperas->first()->id);
        }

        return view('livewire.inscripciones.listar-inscripciones', [
            'datos' => $datos,
            'disciplinas' => $disciplinas,
            'gruposFiltro' => $gruposFiltro,
            'gruposDestinoDisponibles' => $gruposDestinoDisponibles,
            'conteoPendientes' => $conteoPendientes,
            'conteoAprobadas' => $conteoAprobadas,
            'conteoEspera' => $conteoEspera,
            'conteoHistorial' => $conteoHistorial,
            'primerasEsperas' => $primerasEsperas,
        ])->layout('layouts.app');
    }
}
