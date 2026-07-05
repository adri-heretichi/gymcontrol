<?php

namespace App\Livewire\Operadores;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class EditarOperador extends Component
{
    public User $user;
    public $name = '';
    public $email = '';
    public $rol = '';
    public $password = ''; // Opcional para restablecer la contraseña

    /**
     * Inicializa datos del operador y valida rol de administrador.
     */
    public function mount(User $user)
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->rol = $user->rol;
    }

    /**
     * Reglas de validación.
     */
    protected function rules()
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $this->user->id,
            'rol' => 'required|in:admin,recepcionista',
            'password' => 'nullable|string|min:8',
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
        'email.unique' => 'Este correo electrónico ya se encuentra registrado por otro operador.',
        'rol.required' => 'Debe seleccionar un rol de permisos.',
        'rol.in' => 'El rol seleccionado es inválido.',
        'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
    ];

    /**
     * Actualiza el operador.
     */
    public function actualizar()
    {
        $this->validate();

        $datos = [
            'name' => trim($this->name),
            'email' => trim($this->email),
            'rol' => $this->rol,
        ];

        // Solo se modifica la contraseña si el administrador completó el campo opcional
        if ($this->password !== '') {
            $datos['password'] = Hash::make($this->password);
        }

        $this->user->update($datos);

        session()->flash('message', 'Operador actualizado correctamente.');
        return redirect()->route('operadores.index');
    }

    public function render()
    {
        return view('livewire.operadores.editar-operador')
            ->layout('layouts.app');
    }
}
