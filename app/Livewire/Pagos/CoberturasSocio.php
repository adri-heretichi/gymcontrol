<?php

namespace App\Livewire\Pagos;

use App\Models\Pago;
use App\Models\Socio;
use App\Services\PagoService;
use Livewire\Component;

/**
 * CoberturasSocio – Consulta de estado económico por inscripción.
 *
 * Disponible para dueña y recepcionista.
 * Muestra cada inscripción del socio con su cobertura vigente o vencida.
 */
class CoberturasSocio extends Component
{
    public Socio $socio;
    public array $coberturas = [];

    public function mount(Socio $socio): void
    {
        $this->authorize('viewAny', Pago::class);
        $this->authorize('view', $socio);

        $this->socio = $socio;
        $this->actualizarCoberturas();
    }

    public function actualizarCoberturas(): void
    {
        $service = app(PagoService::class);
        $this->coberturas = $service->consultarCoberturasSocio($this->socio)->all();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pagos.coberturas-socio', [
            'socio'     => $this->socio,
            'coberturas'=> $this->coberturas,
        ])->layout('layouts.app');
    }
}
