<?php

namespace App\Livewire\AptosFisicos;

use App\Models\AptoFisico;
use Livewire\Component;
use Livewire\WithPagination;

class ListarAptosFisicos extends Component
{
    use WithPagination;

    // Propiedad para el buscador
    public $buscar = '';

    // Filtro por estado médico
    public $filtroEstado = '';

    // Paginación tema Tailwind
    protected $paginationTheme = 'tailwind';

    // Mantiene filtros en URL
    protected $queryString = [
        'buscar' => ['except' => ''],
        'filtroEstado' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', AptoFisico::class);
    }

    public function updatingBuscar()
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado()
    {
        $this->resetPage();
    }

    public function render()
    {
        // Consulta optimizada con eager loading de socio
        $query = AptoFisico::with('socio');

        // Búsqueda por DNI, nombre, apellido, correo o celular del socio
        if ($this->buscar !== '') {
            $query->whereHas('socio', function ($q) {
                $q->where('nombre', 'like', '%' . $this->buscar . '%')
                  ->orWhere('apellido', 'like', '%' . $this->buscar . '%')
                  ->orWhere('dni', 'like', '%' . $this->buscar . '%')
                  ->orWhere('correo', 'like', '%' . $this->buscar . '%');
            });
        }

        // Filtro por estado
        if ($this->filtroEstado !== '') {
            if ($this->filtroEstado === 'vigente') {
                $query->where('estado', 'vigente')
                      ->where('fecha_vencimiento', '>=', today());
            } elseif ($this->filtroEstado === 'vencido') {
                $query->where(function ($q) {
                    $q->where('estado', 'vencido')
                      ->orWhere('fecha_vencimiento', '<', today());
                });
            } elseif (in_array($this->filtroEstado, ['pendiente_revision', 'rechazado'], true)) {
                $query->where('estado', $this->filtroEstado);
            }
        }

        $aptos = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.aptos-fisicos.listar-aptos-fisicos', [
            'aptos' => $aptos
        ])->layout('layouts.app');
    }
}
