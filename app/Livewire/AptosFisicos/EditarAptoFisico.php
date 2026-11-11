<?php

namespace App\Livewire\AptosFisicos;

use App\Models\AptoFisico;
use App\Models\Auditoria;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\CertificadoMedicoService;
use Illuminate\Support\Facades\DB;

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
        $this->authorize('update', $aptoFisico);

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
            'estado' => 'required|in:pendiente_revision,vigente,vencido,rechazado',
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
        $this->authorize('update', $this->aptoFisico);
        $this->validate();

        if (
            $this->estado === AptoFisico::ESTADO_VIGENTE
            && (
                $this->fecha_emision > today()->toDateString()
                || $this->fecha_vencimiento < today()->toDateString()
            )
        ) {
            $this->addError('estado', 'Solo se puede aprobar un certificado ya emitido y todavía vigente.');
            return;
        }

        if ($this->archivo_cargado) {
            app(CertificadoMedicoService::class)->registrarCertificado(
                socio: $this->aptoFisico->socio,
                fechaEmision: $this->fecha_emision,
                fechaVencimiento: $this->fecha_vencimiento,
                operador: auth()->user(),
                archivo: $this->archivo_cargado
            );

            session()->flash('message', 'El nuevo certificado se registró y el documento histórico se conservó.');
            return redirect()->route('socios.show', $this->aptoFisico->socio_id);
        }

        $datos = [
            'fecha_emision' => $this->fecha_emision,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'estado' => $this->estado,
        ];

        $datosAntes = [
            'fecha_emision' => $this->aptoFisico->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $this->aptoFisico->fecha_vencimiento?->toDateString(),
            'estado' => $this->aptoFisico->estado,
        ];

        DB::transaction(function () use ($datos, $datosAntes) {
            $this->aptoFisico->update($datos);

            Auditoria::registrar(
                tipoEvento: 'medico',
                accion: match ([$datosAntes['estado'], $datos['estado']]) {
                    ['pendiente_revision', 'vigente'] => 'certificado_medico_aprobado',
                    ['pendiente_revision', 'rechazado'] => 'certificado_medico_rechazado',
                    default => 'certificado_medico_actualizado',
                },
                entidad: $this->aptoFisico,
                datosAntes: $datosAntes,
                datosDespues: [
                    'fecha_emision' => $this->fecha_emision,
                    'fecha_vencimiento' => $this->fecha_vencimiento,
                    'estado' => $this->estado,
                ],
                userId: auth()->id()
            );
        });

        session()->flash('message', 'Certificado médico actualizado correctamente.');
        return redirect()->route('socios.show', $this->aptoFisico->socio_id);
    }

    public function render()
    {
        $this->authorize('update', $this->aptoFisico);

        return view('livewire.aptos-fisicos.editar-apto-fisico')
            ->layout('layouts.app');
    }
}
