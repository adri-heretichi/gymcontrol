<?php

namespace App\Livewire\Membresias;

use App\Models\Membresia;
use Livewire\Component;

class EditarMembresia extends Component
{
    public Membresia $membresia;
    public $nombre = '';
    public $precio = '';
    public $horas_mensuales = '';
    public $estado = '';

    /**
     * Valida rol e inicializa propiedades.
     */
    public function mount(Membresia $membresia)
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }

        $this->membresia = $membresia;
        $this->nombre = $membresia->nombre;
        $this->precio = $membresia->precio;
        $this->horas_mensuales = $membresia->horas_mensuales;
        $this->estado = $membresia->estado;
    }

    /**
     * Reglas de validación.
     */
    protected function rules()
    {
        return [
            'nombre' => 'required|string|min:2|max:255|unique:membresias,nombre,' . $this->membresia->id,
            'precio' => 'required|numeric|min:0|max:999999.99',
            'horas_mensuales' => 'nullable|integer|min:1|max:9999',
            'estado' => 'required|in:activo,inactivo',
        ];
    }

    /**
     * Mensajes de error.
     */
    protected $messages = [
        'nombre.required' => 'El nombre del plan es obligatorio.',
        'nombre.unique' => 'Ya existe otro plan con ese nombre.',
        'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        'precio.required' => 'El precio es obligatorio.',
        'precio.numeric' => 'El precio debe ser un número válido.',
        'precio.min' => 'El precio no puede ser negativo.',
        'horas_mensuales.integer' => 'Las horas mensuales deben ser un número entero.',
        'horas_mensuales.min' => 'Las horas mensuales deben ser al menos 1 hora.',
        'estado.required' => 'El estado es obligatorio.',
        'estado.in' => 'El estado seleccionado es inválido.',
    ];

    /**
     * Actualiza el plan de membresía.
     */
    public function actualizar()
    {
        $this->validate();

        $this->membresia->update([
            'nombre' => trim($this->nombre),
            'precio' => $this->precio,
            'horas_mensuales' => $this->horas_mensuales ? (int)$this->horas_mensuales : null,
            'estado' => $this->estado,
        ]);

        session()->flash('message', 'Plan de membresía actualizado correctamente.');
        return redirect()->route('membresias.index');
    }

    public function render()
    {
        return view('livewire.membresias.editar-membresia')
            ->layout('layouts.app');
    }
}
