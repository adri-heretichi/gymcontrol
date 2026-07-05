<?php

namespace App\Livewire\AptosFisicos;

use App\Models\Socio;
use App\Models\AptoFisico;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class CrearAptoFisico extends Component
{
    use WithFileUploads;

    public Socio $socio;

    public $fecha_emision = '';
    public $fecha_vencimiento = '';
    public $archivo_cargado;

    /**
     * Inicializa datos del socio.
     */
    public function mount(Socio $socio)
    {
        // Ambos roles (admin y recepcionista) tienen permitido el acceso
        if (!auth()->check()) {
            abort(403, 'Sesión no válida.');
        }

        $this->socio = $socio;
        $this->fecha_emision = Carbon::today()->format('Y-m-d');
        $this->fecha_vencimiento = Carbon::today()->addYear()->format('Y-m-d');
    }

    /**
     * Autocompleta automáticamente sumando 1 año al actualizar la fecha de emisión.
     */
    public function updatedFechaEmision($value)
    {
        if ($value) {
            try {
                $this->fecha_vencimiento = Carbon::parse($value)->addYear()->format('Y-m-d');
            } catch (\Exception $e) {
                // Silenciar errores de casteo de fechas incompletas
            }
        }
    }

    /**
     * Reglas de validación.
     */
    protected function rules()
    {
        return [
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'required|date|after_or_equal:fecha_emision',
            'archivo_cargado' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096', // Máx 4MB
        ];
    }

    /**
     * Mensajes de validación en español.
     */
    protected $messages = [
        'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
        'fecha_emision.date' => 'La fecha de emisión debe ser una fecha válida.',
        'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
        'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
        'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la de emisión.',
        'archivo_cargado.file' => 'Debe cargar un archivo válido.',
        'archivo_cargado.mimes' => 'El certificado debe ser de tipo: PDF, JPG, JPEG o PNG.',
        'archivo_cargado.max' => 'El tamaño máximo permitido es de 4 MB.',
    ];

    /**
     * Guarda el certificado médico.
     */
    public function guardar()
    {
        $this->validate();

        // 1. Guardamos el archivo de forma segura y privada (en storage/app/secure/aptos_fisicos)
        $path = null;
        if ($this->archivo_cargado) {
            $path = $this->archivo_cargado->store('secure/aptos_fisicos', 'local');
        }

        // 2. Determinamos el estado dinámico inicial
        $estado = Carbon::parse($this->fecha_vencimiento)->isPast() ? 'vencido' : 'vigente';

        // 3. Registramos en base de datos
        AptoFisico::create([
            'socio_id' => $this->socio->id,
            'archivo' => $path,
            'fecha_emision' => $this->fecha_emision,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'estado' => $estado,
        ]);

        session()->flash('message', 'Apto físico médico registrado correctamente.');
        return redirect()->route('socios.show', $this->socio->id);
    }

    public function render()
    {
        return view('livewire.aptos-fisicos.crear-apto-fisico')
            ->layout('layouts.app');
    }
}
