<?php

namespace App\Livewire\Socios;

use App\Models\Membresia;
use App\Models\Socio;
use Carbon\Carbon;
use Livewire\Component;

class CrearSocio extends Component
{
    // Propiedades vinculadas al formulario
    public $nombre = '';
    public $apellido = '';
    public $dni = '';
    public $sexo = 'M'; // Por defecto Masculino
    public $correo = '';
    public $celular = '';
    public $membresia_id = null; // Debe ser seleccionada por el operador
    public $fecha_alta = '';
    public $fecha_vencimiento = ''; // Obligatoriamente seleccionada de forma manual por el operador
    public $estado = 'activo'; // Activo por defecto al registrarse
    
    // Propiedad foto preparada para futura carga de archivos (temporalmente nula)
    public $foto = null;

    // Listado de membresías activas para la lista desplegable
    public $membresias = [];

    /**
     * Inicializa la fecha de alta por defecto y carga las membresías activas.
     */
    public function mount()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }
        $this->fecha_alta = Carbon::today()->format('Y-m-d');
        $this->membresias = Membresia::where('estado', 'activo')->get();
    }

    /**
     * Reglas de validación aplicadas al formulario.
     */
    protected function rules()
    {
        return [
            'nombre' => 'required|string|min:2|max:255',
            'apellido' => 'required|string|min:2|max:255',
            'dni' => 'required|digits_between:7,10|unique:socios,dni',
            'sexo' => 'required|in:M,F',
            'correo' => 'nullable|email|unique:socios,correo|max:255',
            'celular' => 'nullable|string|max:50',
            'membresia_id' => 'required|exists:membresias,id',
            'fecha_alta' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'foto' => 'nullable', // Regla preparada para futuras integraciones de uploads
        ];
    }

    /**
     * Mensajes de error en español para feedback del usuario.
     */
    protected $messages = [
        'nombre.required' => 'El nombre es obligatorio.',
        'apellido.required' => 'El apellido es obligatorio.',
        'dni.required' => 'El DNI es obligatorio.',
        'dni.digits_between' => 'El DNI debe contener entre 7 y 10 números.',
        'dni.unique' => 'Este DNI ya se encuentra registrado.',
        'sexo.required' => 'Debe seleccionar el sexo.',
        'correo.email' => 'El formato del correo electrónico es inválido.',
        'correo.unique' => 'Este correo electrónico ya está registrado.',
        'membresia_id.required' => 'Debe seleccionar una membresía.',
        'fecha_alta.required' => 'La fecha de alta es obligatoria.',
        'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria y debe ser seleccionada manualmente.',
        'fecha_vencimiento.after_or_equal' => 'El vencimiento no puede ser anterior al alta.',
    ];

    /**
     * Procesa el guardado del nuevo socio y normaliza sus campos con trim().
     */
    public function guardar()
    {
        // Ejecutamos la validación
        $this->validate();

        // 1. Generamos un token secuencial con ceros a la izquierda
        $ultimoSocio = Socio::orderBy('id', 'desc')->first();
        if ($ultimoSocio) {
            $ultimoToken = (int) $ultimoSocio->token;
            $nuevoToken = $ultimoToken + 1;
        } else {
            $nuevoToken = 1;
        }
        $token = str_pad($nuevoToken, 4, '0', STR_PAD_LEFT);

        // 2. Normalizamos los datos eliminando espacios sobrantes
        $nombreNormalizado = trim($this->nombre);
        $apellidoNormalizado = trim($this->apellido);
        $correoNormalizado = $this->correo ? trim($this->correo) : null;
        $celularNormalizado = $this->celular ? trim($this->celular) : null;

        // 3. Registramos el socio en la base de datos
        // Guardamos la foto como null y el QR como null de acuerdo a tus directivas
        $socioCreado = Socio::create([
            'membresia_id' => $this->membresia_id,
            'nombre' => $nombreNormalizado,
            'apellido' => $apellidoNormalizado,
            'dni' => $this->dni,
            'sexo' => $this->sexo,
            'correo' => $correoNormalizado,
            'celular' => $celularNormalizado,
            'foto' => null, // Preparado
            'token' => $token,
            'qr' => null, // QR temporalmente nulo
            'fecha_alta' => $this->fecha_alta,
            'fecha_vencimiento' => null,
            'estado' => $this->estado,
        ]);

        // Generar y guardar el QR del socio en formato SVG en disco privado (Problema 2)
        $qrDir = storage_path('app/private/qrs');
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        $qrPath = $qrDir . '/qr_' . $socioCreado->id . '.svg';
        
        \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(200)
            ->generate(config('app.url') . '/terminal/scan/' . $socioCreado->token, $qrPath);

        // Redireccionamos directamente a la ficha del socio recién creado con mensaje flash (Problema 4)
        session()->flash('message', 'Socio registrado correctamente. Token de acceso: ' . $token);
        return redirect()->route('socios.show', $socioCreado->id);
    }

    public function render()
    {
        return view('livewire.socios.crear-socio')
            ->layout('layouts.app');
    }
}
