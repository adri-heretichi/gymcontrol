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

    // Filtro por estado ('', 'vigente', 'vencido')
    public $filtroEstado = '';

    // Paginación tema Tailwind
    protected $paginationTheme = 'tailwind';

    // Mantiene filtros en URL
    protected $queryString = [
        'buscar' => ['except' => ''],
        'filtroEstado' => ['except' => ''],
    ];

    public function updatingBuscar()
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado()
    {
        $this->resetPage();
    }

    /**
     * Eliminación de apto físico médica (Solo administradores).
     */
    public function eliminar($id)
    {
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes los permisos necesarios para eliminar certificados.');
            return;
        }

        $apto = AptoFisico::findOrFail($id);
        
        // Eliminamos el archivo físico si existe
        if ($apto->archivo && \Storage::disk('local')->exists($apto->archivo)) {
            \Storage::disk('local')->delete($apto->archivo);
        }

        $apto->delete();

        session()->flash('message', 'Certificado eliminado correctamente.');
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
            }
        }

        $aptos = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.aptos-fisicos.listar-aptos-fisicos', [
            'aptos' => $aptos
        ])->layout('layouts.app');
    }
}
