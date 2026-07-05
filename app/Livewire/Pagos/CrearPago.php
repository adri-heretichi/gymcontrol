<?php

namespace App\Livewire\Pagos;

use App\Models\Pago;
use App\Models\Socio;
use Carbon\Carbon;
use Livewire\Component;

class CrearPago extends Component
{
    public $socio_id;
    public $fecha_pago;
    public $importe;
    public $metodo_pago = 'efectivo';
    
    // Indica si el socio está bloqueado por venir desde su perfil
    public $deshabilitarSocio = false;
    public $socioPreseleccionado = null;

    protected function rules()
    {
        return [
            'socio_id' => 'required|exists:socios,id',
            'fecha_pago' => 'required|date',
            'importe' => 'required|numeric|min:0',
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia',
        ];
    }

    protected $validationAttributes = [
        'socio_id' => 'socio',
        'fecha_pago' => 'fecha de pago',
        'importe' => 'importe',
        'metodo_pago' => 'método de pago',
    ];

    public function mount(?Socio $socio = null)
    {
        $this->fecha_pago = Carbon::today()->format('Y-m-d');

        if ($socio && $socio->exists) {
            $this->socio_id = $socio->id;
            $this->socioPreseleccionado = $socio;
            $this->deshabilitarSocio = true;
            $this->importe = $socio->membresia?->precio ?? '';
        }
    }

    public function updatedSocioId($value)
    {
        if ($value) {
            $socio = Socio::find($value);
            $this->importe = $socio?->membresia?->precio ?? '';
        } else {
            $this->importe = '';
        }
    }

    public function guardar()
    {
        $this->validate();

        $socio = Socio::findOrFail($this->socio_id);
        $hoy = Carbon::today();
        $vencimientoActual = $socio->fecha_vencimiento;

        // Si la membresía está vigente sumar al vencimiento actual
        // Si está vencida o es nula, sumar desde hoy
        $nuevaFecha = ($vencimientoActual && $vencimientoActual->gt($hoy))
            ? $vencimientoActual->copy()->addMonth()
            : $hoy->copy()->addMonth();

        // Crear el pago
        Pago::create([
            'socio_id' => $this->socio_id,
            'fecha_pago' => $this->fecha_pago,
            'importe' => $this->importe,
            'metodo_pago' => $this->metodo_pago,
        ]);

        // Actualizar vencimiento y estado del socio
        $socio->update([
            'fecha_vencimiento' => $nuevaFecha->toDateString(),
            'estado' => 'activo',
        ]);

        session()->flash('message', "Pago registrado con éxito para {$socio->nombre} {$socio->apellido}. Vencimiento extendido al " . $nuevaFecha->format('d/m/Y') . ".");

        return redirect()->route('pagos.index');
    }

    public function render()
    {
        $socios = [];
        if (!$this->deshabilitarSocio) {
            // Solo listamos socios activos
            $socios = Socio::where('estado', 'activo')
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->get();
        }

        return view('livewire.pagos.crear-pago', [
            'socios' => $socios
        ])->layout('layouts.app');
    }
}
