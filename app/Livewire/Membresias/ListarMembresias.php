<?php

namespace App\Livewire\Membresias;

use App\Models\Disciplina;
use App\Models\Inscripcion;
use Livewire\Component;

class ListarMembresias extends Component
{
    public $disciplina = '';

    protected $queryString = [
        'disciplina' => ['except' => ''],
    ];

    public function render()
    {
        $membresias = Disciplina::query()
            ->whereIn('nombre', Disciplina::PLANES_FENIX)
            ->with([
                'grupos' => fn ($query) => $query
                    ->with('horarios')
                    ->withCount([
                        'inscripciones as alumnos_confirmados_count' => fn ($inscripciones) => $inscripciones
                            ->where('estado', Inscripcion::ESTADO_APROBADA),
                    ])
                    ->orderBy('nombre'),
            ])
            ->orderBy('nombre')
            ->get();

        $membresiaSeleccionada = $membresias->firstWhere('id', (int) $this->disciplina)
            ?? $membresias->first();

        return view('livewire.membresias.listar-membresias', compact(
            'membresias',
            'membresiaSeleccionada'
        ))->layout('layouts.app');
    }
}
