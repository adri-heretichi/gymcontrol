<?php

namespace App\Livewire\Operadores;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class CrearOperador extends Component
{
    public $name = '';
    public $email = '';
    public $password = '';
    public $rol = 'recepcionista'; // Por defecto Recepcionista

    /**
     * Valida permisos administrativos.
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
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'rol' => 'required|in:admin,recepcionista',
        ];
    }

    /**
     * Mensajes de error en español.
     */
    protected $messages = [
        'name.required' => 'El nombre es obligatorio.',
        'name.min' => 'El nombre debe tener al menos 2 caracteres.',
        'email.required' => 'El correo electrónico es obligatorio.',
        'email.email' => 'El formato del correo electrónico es inválido.',
        'email.unique' => 'Este correo electrónico ya se encuentra registrado.',
        'password.required' => 'La contraseña es obligatoria.',
        'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        'rol.required' => 'Debe seleccionar un rol de permisos.',
        'rol.in' => 'El rol seleccionado es inválido.',
    ];

    /**
     * Guarda el nuevo operador.
     */
    public function guardar()
    {
        $this->validate();

        User::create([
            'name' => trim($this->name),
            'email' => trim($this->email),
            'password' => Hash::make($this->password),
            'rol' => $this->rol,
        ]);

        session()->flash('message', 'Operador registrado correctamente.');
        return redirect()->route('operadores.index');
    }

    public function render()
    {
        return view('livewire.operadores.crear-operador')
            ->layout('layouts.app');
    }
}
