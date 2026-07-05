<?php

namespace App\Livewire\AptosFisicos;

use App\Models\AptoFisico;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class EditarAptoFisico extends Component
{
    use WithFileUploads;

    public AptoFisico $aptoFisico;

    public $fecha_emision = '';
    public $fecha_vencimiento = '';
    public $estado = '';
    public $archivo_cargado;

    /**
     * Valida permisos e inicializa los campos.
     */
    public function mount(AptoFisico $aptoFisico)
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }

        $this->aptoFisico = $aptoFisico;
        $this->fecha_emision = $aptoFisico->fecha_emision ? $aptoFisico->fecha_emision->format('Y-m-d') : '';
        $this->fecha_vencimiento = $aptoFisico->fecha_vencimiento ? $aptoFisico->fecha_vencimiento->format('Y-m-d') : '';
        $this->estado = $aptoFisico->estado;
    }

    /**
     * Reglas de validación.
     */
    protected function rules()
    {
        return [
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'required|date|after_or_equal:fecha_emision',
            'estado' => 'required|in:vigente,vencido',
            'archivo_cargado' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096', // Máx 4MB (opcional)
        ];
    }

    /**
     * Mensajes de validación.
     */
    protected $messages = [
        'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
        'fecha_emision.date' => 'La fecha de emisión debe ser una fecha válida.',
        'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
        'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
        'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la de emisión.',
        'estado.required' => 'El estado es obligatorio.',
        'estado.in' => 'El estado seleccionado es inválido.',
        'archivo_cargado.file' => 'Debe cargar un archivo válido.',
        'archivo_cargado.mimes' => 'El certificado debe ser de tipo: PDF, JPG, JPEG o PNG.',
        'archivo_cargado.max' => 'El tamaño máximo permitido es de 4 MB.',
    ];

    /**
     * Actualiza el certificado médico.
     */
    public function actualizar()
    {
        $this->validate();

        $datos = [
            'fecha_emision' => $this->fecha_emision,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'estado' => $this->estado,
        ];

        // Reemplazo opcional del archivo privado
        if ($this->archivo_cargado) {
            // 1. Borramos el archivo viejo si existe
            if ($this->aptoFisico->archivo && Storage::disk('local')->exists($this->aptoFisico->archivo)) {
                Storage::disk('local')->delete($this->aptoFisico->archivo);
            }

            // 2. Guardamos el nuevo archivo en la misma ruta privada
            $path = $this->archivo_cargado->store('secure/aptos_fisicos', 'local');
            $datos['archivo'] = $path;
        }

        $this->aptoFisico->update($datos);

        session()->flash('message', 'Certificado médico actualizado correctamente.');
        return redirect()->route('socios.show', $this->aptoFisico->socio_id);
    }

    public function render()
    {
        return view('livewire.aptos-fisicos.editar-apto-fisico')
            ->layout('layouts.app');
    }
}
