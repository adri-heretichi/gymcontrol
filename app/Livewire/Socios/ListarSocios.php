<?php

namespace App\Livewire\Socios;

use App\Models\Socio;
use Livewire\Component;
use Livewire\WithPagination;

class ListarSocios extends Component
{
    use WithPagination;

    // Propiedad enlazada al input del buscador
    public $buscar = '';

    // Propiedades preparadas para el futuro ordenamiento
    public $ordenarPor = 'apellido';
    public $ordenarDireccion = 'asc';

    // Fuerza a Livewire a usar el tema de paginación de Tailwind CSS
    protected $paginationTheme = 'tailwind';

    // Mantiene el filtro de búsqueda en la URL del navegador
    protected $queryString = [
        'buscar' => ['except' => ''],
        'ordenarPor' => ['except' => 'apellido'],
        'ordenarDireccion' => ['except' => 'asc'],
    ];

    // Reinicia a la página 1 al escribir una nueva búsqueda
    public function updatingBuscar()
    {
        $this->resetPage();
    }

    /**
     * Inactiva o activa lógicamente a un socio.
     * Acción restringida únicamente para Administradores.
     */
    public function alternarEstado($socioId)
    {
        // 1. Validación de sesión activa (Seguridad)
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        // 2. Validación estricta de rol en backend
        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes permisos para modificar el estado del socio.');
            return;
        }

        // 3. Modificación del registro
        $socio = Socio::findOrFail($socioId);
        $socio->estado = ($socio->estado === 'activo') ? 'inactivo' : 'activo';
        $socio->save();

        session()->flash('message', "El estado del socio {$socio->nombre} {$socio->apellido} ha sido actualizado a: " . strtoupper($socio->estado));
    }

    public function render()
    {
        // Consulta optimizada con buscador y búsqueda ampliada (celular agregada)
        $socios = Socio::with('membresia')
            ->where(function ($query) {
                $query->where('nombre', 'like', '%' . $this->buscar . '%')
                      ->orWhere('apellido', 'like', '%' . $this->buscar . '%')
                      ->orWhere('dni', 'like', '%' . $this->buscar . '%')
                      ->orWhere('correo', 'like', '%' . $this->buscar . '%')
                      ->orWhere('celular', 'like', '%' . $this->buscar . '%')
                      ->orWhere('token', 'like', '%' . $this->buscar . '%');
            })
            // Estructura de ordenación dinámica (lista para el futuro)
            ->orderBy($this->ordenarPor, $this->ordenarDireccion)
            ->paginate(10);

        return view('livewire.socios.listar-socios', [
            'socios' => $socios
        ])->layout('layouts.app');
    }
}
