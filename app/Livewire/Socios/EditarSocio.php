<?php

namespace App\Livewire\Socios;

use App\Models\Auditoria;
use App\Models\Socio;
use Illuminate\Support\Facades\DB;
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
    public $fecha_alta = '';
    
    // El estado del socio
    public $estado = '';
    
    // Propiedad foto preparada
    public $foto = null;

    /**
     * Inicializa y carga los datos del socio en los campos del formulario.
     */
    public function mount(Socio $socio)
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }
        $this->socio = $socio;
        // Hidratamos las variables del formulario de forma defensiva
        $this->nombre = $socio->nombre;
        $this->apellido = $socio->apellido;
        $this->dni = $socio->dni;
        $this->sexo = $socio->sexo;
        $this->correo = $socio->correo;
        $this->celular = $socio->celular;
        $this->fecha_alta = $socio->fecha_alta ? $socio->fecha_alta->format('Y-m-d') : '';
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
            'fecha_alta' => 'required|date',
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
        'fecha_alta.required' => 'La fecha de alta es obligatoria.',
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

        // 3. Modificamos el registro en la base de datos sin alterar el QR ni el PIN.
        $datos = [
            'nombre' => $nombreNormalizado,
            'apellido' => $apellidoNormalizado,
            'dni' => $this->dni,
            'sexo' => $this->sexo,
            'correo' => $correoNormalizado,
            'celular' => $celularNormalizado,
            'fecha_alta' => $this->fecha_alta,
            'estado' => $this->estado,
        ];
        $camposAuditables = ['nombre', 'apellido', 'correo', 'celular', 'fecha_alta', 'estado'];
        DB::transaction(function () use ($datos, $camposAuditables) {
            $socio = Socio::query()->lockForUpdate()->findOrFail($this->socio->id);
            $antes = array_intersect_key($socio->getAttributes(), array_flip($camposAuditables));
            $socio->update($datos);
            $despues = array_intersect_key($socio->fresh()->getAttributes(), array_flip($camposAuditables));
            if ($antes !== $despues) {
                Auditoria::registrar(
                    tipoEvento: 'administrativo',
                    accion: 'socio_actualizado',
                    entidad: $socio,
                    datosAntes: $antes,
                    datosDespues: $despues,
                    userId: auth()->id()
                );
            }
        }, 3);

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
