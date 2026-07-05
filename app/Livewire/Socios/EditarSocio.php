<?php

namespace App\Livewire\Socios;

use App\Models\Membresia;
use App\Models\Socio;
use Carbon\Carbon;
use Livewire\Component;

class EditarSocio extends Component
{
    // Instancia del socio inyectada automáticamente
    public Socio $socio;

    // Propiedades del formulario
    public $nombre = '';
    public $apellido = '';
    public $dni = '';
    public $sexo = '';
    public $correo = '';
    public $celular = '';
    public $membresia_id = '';
    public $fecha_alta = '';
    public $fecha_vencimiento = '';
    
    // El estado del socio
    public $estado = '';
    
    // Propiedad foto preparada
    public $foto = null;

    // Listado de membresías activas para la lista desplegable
    public $membresias = [];

    /**
     * Inicializa y carga los datos del socio en los campos del formulario.
     */
    public function mount(Socio $socio)
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }
        $this->socio = $socio;
        $this->membresias = Membresia::where('estado', 'activo')->get();

        // Hidratamos las variables del formulario de forma defensiva
        $this->nombre = $socio->nombre;
        $this->apellido = $socio->apellido;
        $this->dni = $socio->dni;
        $this->sexo = $socio->sexo;
        $this->correo = $socio->correo;
        $this->celular = $socio->celular;
        $this->membresia_id = $socio->membresia_id;
        $this->fecha_alta = $socio->fecha_alta ? $socio->fecha_alta->format('Y-m-d') : '';
        $this->fecha_vencimiento = $socio->fecha_vencimiento ? $socio->fecha_vencimiento->format('Y-m-d') : '';
        $this->estado = $socio->estado;
    }

    /**
     * Reglas de validación dinámicas.
     */
    protected function rules()
    {
        $rules = [
            'nombre' => 'required|string|min:2|max:255',
            'apellido' => 'required|string|min:2|max:255',
            'dni' => 'required|digits_between:7,10|unique:socios,dni,' . $this->socio->id,
            'sexo' => 'required|in:M,F',
            'correo' => 'nullable|email|unique:socios,correo,' . $this->socio->id . '|max:255',
            'celular' => 'nullable|string|max:50',
            'membresia_id' => 'required|exists:membresias,id',
            'fecha_alta' => 'required|date',
            'fecha_vencimiento' => 'required|date|after_or_equal:fecha_alta',
            'foto' => 'nullable', // Preparado para futuras integraciones
        ];

        // La validación del estado se añade únicamente si el usuario autenticado es admin
        if (auth()->check() && auth()->user()->rol === 'admin') {
            $rules['estado'] = 'required|in:activo,inactivo';
        }

        return $rules;
    }

    /**
     * Mensajes de validación en español.
     */
    protected $messages = [
        'nombre.required' => 'El nombre es obligatorio.',
        'apellido.required' => 'El apellido es obligatorio.',
        'dni.required' => 'El DNI es obligatorio.',
        'dni.digits_between' => 'El DNI debe contener entre 7 y 10 números.',
        'dni.unique' => 'Este DNI ya se encuentra registrado por otro socio.',
        'sexo.required' => 'Debe seleccionar el sexo.',
        'correo.email' => 'El formato del correo electrónico es inválido.',
        'correo.unique' => 'Este correo electrónico ya está registrado por otro socio.',
        'membresia_id.required' => 'Debe seleccionar una membresía.',
        'fecha_alta.required' => 'La fecha de alta es obligatoria.',
        'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
        'fecha_vencimiento.after_or_equal' => 'El vencimiento no puede ser anterior al alta.',
        'estado.required' => 'El estado es obligatorio.',
    ];

    /**
     * Procesa la actualización del socio.
     */
    public function actualizar()
    {
        // Ejecutamos las validaciones
        $this->validate();

        // 1. Verificación de seguridad de Roles en backend
        // Si no es administrador, forzamos que mantenga su valor original de BD
        if (auth()->user()->rol !== 'admin') {
            $this->estado = $this->socio->estado;
        }

        // 2. Normalizamos textos con trim()
        $nombreNormalizado = trim($this->nombre);
        $apellidoNormalizado = trim($this->apellido);
        $correoNormalizado = $this->correo ? trim($this->correo) : null;
        $celularNormalizado = $this->celular ? trim($this->celular) : null;

        // 3. Modificamos el registro en la base de datos sin alterar el QR ni el Token
        $this->socio->update([
            'membresia_id' => $this->membresia_id,
            'nombre' => $nombreNormalizado,
            'apellido' => $apellidoNormalizado,
            'dni' => $this->dni,
            'sexo' => $this->sexo,
            'correo' => $correoNormalizado,
            'celular' => $celularNormalizado,
            'fecha_alta' => $this->fecha_alta,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'estado' => $this->estado,
        ]);

        // Redireccionamos a la ficha del socio con un mensaje flash
        session()->flash('mensaje', 'Socio actualizado correctamente.');
        return redirect()->route('socios.show', $this->socio->id);
    }

    public function render()
    {
        return view('livewire.socios.editar-socio')
            ->layout('layouts.app');
    }
}
