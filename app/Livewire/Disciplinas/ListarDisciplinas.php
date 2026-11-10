<?php

namespace App\Livewire\Disciplinas;

use App\Models\Disciplina;
use App\Models\Auditoria;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class ListarDisciplinas extends Component
{
    use WithPagination;

    public $buscar = '';

    protected $paginationTheme = 'tailwind';

    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function updatingBuscar()
    {
        $this->resetPage();
    }

    /**
     * Alterna el estado activo / inactivo de la disciplina (Solo Administradora / Dueña).
     */
    public function alternarEstado($disciplinaId)
    {
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes permisos para modificar el estado de la disciplina.');
            return;
        }

        $disciplina = DB::transaction(function () use ($disciplinaId) {
            $disciplina = Disciplina::query()->lockForUpdate()->findOrFail($disciplinaId);
            $estadoAnterior = $disciplina->estado;
            $disciplina->estado = ($disciplina->estado === 'activo') ? 'inactivo' : 'activo';
            $disciplina->save();
            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'estado_disciplina_modificado',
                entidad: $disciplina,
                datosAntes: ['estado' => $estadoAnterior],
                datosDespues: ['estado' => $disciplina->estado],
                userId: auth()->id()
            );
            return $disciplina;
        }, 3);

        session()->flash('message', "El estado de la disciplina {$disciplina->nombre} ahora es: " . strtoupper($disciplina->estado));
    }

    /**
     * Elimina físicamente la disciplina solo si no posee grupos vinculados.
     */
    public function eliminar($disciplinaId)
    {
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes permisos para eliminar disciplinas.');
            return;
        }

        $nombre = DB::transaction(function () use ($disciplinaId) {
            $disciplina = Disciplina::query()->lockForUpdate()->findOrFail($disciplinaId);
            if (!$disciplina->puedeEliminarse()) {
                return null;
            }

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'disciplina_eliminada',
                entidad: $disciplina,
                datosAntes: [
                    'nombre' => $disciplina->nombre,
                    'descripcion' => $disciplina->descripcion,
                    'estado' => $disciplina->estado,
                ],
                datosDespues: ['eliminada' => true],
                userId: auth()->id()
            );
            $disciplina->delete();
            return $disciplina->nombre;
        }, 3);
        if ($nombre === null) {
            $disciplina = Disciplina::findOrFail($disciplinaId);
            session()->flash('error', "No es posible eliminar la disciplina '{$disciplina->nombre}' porque posee grupos vinculados. Se sugiere desactivarla para preservar el historial.");
            return;
        }

        session()->flash('message', "La disciplina '{$nombre}' ha sido eliminada correctamente.");
    }

    public function render()
    {
        $disciplinas = Disciplina::withCount('grupos')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->buscar . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->buscar . '%');
            })
            ->orderBy('nombre', 'asc')
            ->paginate(10);

        return view('livewire.disciplinas.listar-disciplinas', [
            'disciplinas' => $disciplinas,
        ])->layout('layouts.app');
    }
}
