<?php

namespace App\Livewire\Grupos;

use App\Models\Disciplina;
use App\Models\Auditoria;
use App\Models\Grupo;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class ListarGrupos extends Component
{
    use WithPagination;

    public $buscar = '';
    public $disciplinaId = '';

    protected $paginationTheme = 'tailwind';

    protected $queryString = [
        'buscar' => ['except' => ''],
        'disciplinaId' => ['except' => ''],
    ];

    public function updatingBuscar()
    {
        $this->resetPage();
    }

    public function updatingDisciplinaId()
    {
        $this->resetPage();
    }

    /**
     * Alterna el estado activo / inactivo de un grupo (Solo Administradora / Dueña).
     */
    public function alternarEstado($grupoId)
    {
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes permisos para modificar el estado del grupo.');
            return;
        }

        $grupo = DB::transaction(function () use ($grupoId) {
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($grupoId);
            $estadoAnterior = $grupo->estado;
            $grupo->estado = ($grupo->estado === 'activo') ? 'inactivo' : 'activo';
            $grupo->save();
            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'estado_grupo_modificado',
                entidad: $grupo,
                datosAntes: ['estado' => $estadoAnterior],
                datosDespues: ['estado' => $grupo->estado],
                userId: auth()->id()
            );
            return $grupo;
        }, 3);

        session()->flash('message', "El estado del grupo '{$grupo->nombre}' ahora es: " . strtoupper($grupo->estado));
    }

    /**
     * Elimina físicamente el grupo solo si no posee inscripciones ni asistencias históricas.
     */
    public function eliminar($grupoId)
    {
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes permisos para eliminar grupos.');
            return;
        }

        $nombre = DB::transaction(function () use ($grupoId) {
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($grupoId);
            if (!$grupo->puedeEliminarse()) {
                return null;
            }

            $datosAntes = [
                'disciplina_id' => $grupo->disciplina_id,
                'nombre' => $grupo->nombre,
                'profesor' => $grupo->profesor,
                'cupo_maximo' => $grupo->cupo_maximo,
                'estado' => $grupo->estado,
                'horarios' => $grupo->horarios()->get(['dia_semana', 'hora_inicio', 'hora_fin'])->toArray(),
            ];
            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'grupo_eliminado',
                entidad: $grupo,
                datosAntes: $datosAntes,
                datosDespues: ['eliminado' => true],
                userId: auth()->id()
            );
            $grupo->horarios()->delete();
            $grupo->delete();
            return $grupo->nombre;
        }, 3);
        if ($nombre === null) {
            $grupo = Grupo::findOrFail($grupoId);
            session()->flash('error', "No es posible eliminar el grupo '{$grupo->nombre}' porque registra inscripciones o asistencias históricas. Se sugiere desactivarlo para conservar el historial.");
            return;
        }

        session()->flash('message', "El grupo '{$nombre}' ha sido eliminado correctamente.");
    }

    public function render()
    {
        $disciplinas = Disciplina::orderBy('nombre')->get();

        $grupos = Grupo::with(['disciplina', 'horarios', 'inscripciones'])
            ->when($this->disciplinaId, function ($q) {
                $q->where('disciplina_id', $this->disciplinaId);
            })
            ->when($this->buscar, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nombre', 'like', '%' . $this->buscar . '%')
                        ->orWhere('profesor', 'like', '%' . $this->buscar . '%')
                        ->orWhereHas('disciplina', function ($d) {
                            $d->where('nombre', 'like', '%' . $this->buscar . '%');
                        });
                });
            })
            ->orderBy('disciplina_id', 'asc')
            ->orderBy('nombre', 'asc')
            ->paginate(10);

        return view('livewire.grupos.listar-grupos', [
            'grupos' => $grupos,
            'disciplinas' => $disciplinas,
        ])->layout('layouts.app');
    }
}
