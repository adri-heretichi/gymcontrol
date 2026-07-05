<?php

namespace App\Livewire\Membresias;

use App\Models\Membresia;
use Livewire\Component;

class CrearMembresia extends Component
{
    public $nombre = '';
    public $precio = '';
    public $horas_mensuales = '';

    /**
     * Valida rol administrativo.
     */
    public function mount()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }
    }

    /**
     * Reglas de validación.
     */
    protected function rules()
    {
        return [
            'nombre' => 'required|string|min:2|max:255|unique:membresias,nombre',
            'precio' => 'required|numeric|min:0|max:999999.99',
            'horas_mensuales' => 'nullable|integer|min:1|max:9999',
        ];
    }

    /**
     * Mensajes de error en español.
     */
    protected $messages = [
        'nombre.required' => 'El nombre del plan es obligatorio.',
        'nombre.unique' => 'Ya existe un plan con ese nombre.',
        'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        'precio.required' => 'El precio es obligatorio.',
        'precio.numeric' => 'El precio debe ser un número válido.',
        'precio.min' => 'El precio no puede ser negativo.',
        'horas_mensuales.integer' => 'Las horas mensuales deben ser un número entero.',
        'horas_mensuales.min' => 'Las horas mensuales deben ser al menos 1 hora.',
    ];

    /**
     * Guarda la nueva membresía.
     */
    public function guardar()
    {
        $this->validate();

        Membresia::create([
            'nombre' => trim($this->nombre),
            'precio' => $this->precio,
            'horas_mensuales' => $this->horas_mensuales ? (int)$this->horas_mensuales : null,
            'estado' => 'activo',
        ]);

        session()->flash('message', 'Plan de membresía creado correctamente.');
        return redirect()->route('membresias.index');
    }

    public function render()
    {
        return view('livewire.membresias.crear-membresia')
            ->layout('layouts.app');
    }
}
