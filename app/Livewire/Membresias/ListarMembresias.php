<?php

namespace App\Livewire\Membresias;

use App\Models\Membresia;
use Livewire\Component;
use Livewire\WithPagination;

class ListarMembresias extends Component
{
    use WithPagination;

    // Propiedad enlazada al input del buscador
    public $buscar = '';

    // Fuerza a Livewire a usar el tema de paginación de Tailwind CSS
    protected $paginationTheme = 'tailwind';

    // Mantiene el filtro de búsqueda en la URL del navegador
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    // Reinicia a la página 1 al escribir una nueva búsqueda
    public function updatingBuscar()
    {
        $this->resetPage();
    }

    /**
     * Inactiva o activa lógicamente una membresía.
     * Acción restringida únicamente a Administradores.
     */
    public function alternarEstado($membresiaId)
    {
        // 1. Validación de sesión activa (Seguridad)
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        // 2. Validación estricta de rol en backend
        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes permisos para modificar el estado de la membresía.');
            return;
        }

        // 3. Modificación del registro
        $membresia = Membresia::findOrFail($membresiaId);
        $membresia->estado = ($membresia->estado === 'activo') ? 'inactivo' : 'activo';
        $membresia->save();

        session()->flash('message', "El estado del plan {$membresia->nombre} ha sido actualizado a: " . strtoupper($membresia->estado));
    }

    public function render()
    {
        // Consulta con buscador
        $membresias = Membresia::where('nombre', 'like', '%' . $this->buscar . '%')
            ->orderBy('nombre', 'asc')
            ->paginate(10);

        return view('livewire.membresias.listar-membresias', [
            'membresias' => $membresias
        ])->layout('layouts.app');
    }
}
