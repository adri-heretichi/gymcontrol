<?php

namespace App\Livewire\Pagos;

use App\Models\Pago;
use Livewire\Component;
use Livewire\WithPagination;

class ListarPagos extends Component
{
    use WithPagination;

    // Propiedad para el buscador
    public $buscar = '';

    // Filtro por método de pago ('', 'efectivo', 'tarjeta', 'transferencia')
    public $filtroMetodo = '';

    // Paginación tema Tailwind
    protected $paginationTheme = 'tailwind';

    // Mantiene filtros en URL
    protected $queryString = [
        'buscar' => ['except' => ''],
        'filtroMetodo' => ['except' => ''],
    ];

    public function updatingBuscar()
    {
        $this->resetPage();
    }

    public function updatingFiltroMetodo()
    {
        $this->resetPage();
    }

    public function render()
    {
        // Consulta optimizada con eager loading de socio y su membresía
        $query = Pago::with(['socio.membresia']);

        // Búsqueda por DNI, nombre, apellido o correo del socio
        if ($this->buscar !== '') {
            $query->whereHas('socio', function ($q) {
                $q->where('nombre', 'like', '%' . $this->buscar . '%')
                  ->orWhere('apellido', 'like', '%' . $this->buscar . '%')
                  ->orWhere('dni', 'like', '%' . $this->buscar . '%');
            });
        }

        // Filtro por método de pago
        if ($this->filtroMetodo !== '') {
            $query->where('metodo_pago', $this->filtroMetodo);
        }

        // Calcular la sumatoria total del importe de la consulta filtrada (antes de paginar)
        $sumaTotal = (float) $query->sum('importe');

        $pagos = $query->orderBy('fecha_pago', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('livewire.pagos.listar-pagos', [
            'pagos' => $pagos,
            'sumaTotal' => $sumaTotal
        ])->layout('layouts.app');
    }
}
