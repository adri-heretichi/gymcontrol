<?php

namespace App\Livewire\Operadores;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class ListarOperadores extends Component
{
    use WithPagination;

    // Propiedad para el buscador
    public $buscar = '';

    // Filtro por rol ('', 'admin', 'recepcionista')
    public $filtroRol = '';

    // Paginación tema Tailwind
    protected $paginationTheme = 'tailwind';

    // Query strings en URL
    protected $queryString = [
        'buscar' => ['except' => ''],
        'filtroRol' => ['except' => ''],
    ];

    /**
     * Valida permisos de administrador al montar.
     */
    public function mount()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }
    }

    // Reinicia página al actualizar filtros
    public function updatingBuscar()
    {
        $this->resetPage();
    }

    public function updatingFiltroRol()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = User::query();

        // Aplicamos buscador por nombre o email
        if ($this->buscar !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->buscar . '%')
                  ->orWhere('email', 'like', '%' . $this->buscar . '%');
            });
        }

        // Aplicamos filtro de rol si está seleccionado
        if ($this->filtroRol !== '') {
            $query->where('rol', $this->filtroRol);
        }

        $operadores = $query->orderBy('name', 'asc')->paginate(10);

        return view('livewire.operadores.listar-operadores', [
            'operadores' => $operadores
        ])->layout('layouts.app');
    }
}
