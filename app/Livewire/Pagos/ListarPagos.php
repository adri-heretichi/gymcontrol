<?php

namespace App\Livewire\Pagos;

use App\Models\Pago;
use App\Models\Socio;
use App\Services\PagoService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ListarPagos – Fase 5.
 *
 * Pantalla de pagos adaptada para coberturas independientes por disciplina.
 *
 * Dueña (admin): ve recaudación general, puede filtrar, ver detalles y anular.
 * Recepcionista: puede filtrar, registrar pagos y consultar coberturas. NO ve recaudación total.
 */
class ListarPagos extends Component
{
    use WithPagination;

    public string $buscar       = '';
    public string $filtroMetodo = '';
    public string $filtroEstado = '';  // '' | 'activo' | 'anulado'
    public string $filtroDesde  = '';
    public string $filtroHasta  = '';

    // Anulación modal (solo dueña)
    public bool   $modalAnular   = false;
    public ?int   $pagoAnularId  = null;
    public string $motivoAnulacion = '';
    public ?string $errorAnulacion  = null;

    protected $paginationTheme = 'tailwind';

    protected $queryString = [
        'buscar'       => ['except' => ''],
        'filtroMetodo' => ['except' => ''],
        'filtroEstado' => ['except' => ''],
    ];

    public function updatingBuscar():       void { $this->resetPage(); }
    public function updatingFiltroMetodo(): void { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }

    // ── Anulación ───────────────────────────────────────────────────────────────

    public function abrirModalAnular(int $pagoId): void
    {
        $this->authorize('anular', Pago::findOrFail($pagoId));
        $this->pagoAnularId    = $pagoId;
        $this->motivoAnulacion = '';
        $this->errorAnulacion  = null;
        $this->modalAnular     = true;
    }

    public function cerrarModalAnular(): void
    {
        $this->modalAnular     = false;
        $this->pagoAnularId    = null;
        $this->motivoAnulacion = '';
        $this->errorAnulacion  = null;
    }

    public function confirmarAnulacion(): void
    {
        $this->validate([
            'motivoAnulacion' => 'required|string|min:10|max:500',
        ], [
            'motivoAnulacion.required' => 'El motivo de anulación es obligatorio.',
            'motivoAnulacion.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        $pago = Pago::findOrFail($this->pagoAnularId);
        $this->authorize('anular', $pago);

        try {
            $service = app(PagoService::class);
            $service->anularPago($pago, auth()->user(), $this->motivoAnulacion);
            session()->flash('message', "Pago #{$pago->id} anulado internamente.");
            $this->cerrarModalAnular();
        } catch (\InvalidArgumentException $e) {
            $this->errorAnulacion = $e->getMessage();
        }
    }

    // ── Render ──────────────────────────────────────────────────────────────────

    public function render(): \Illuminate\View\View
    {
        $user = auth()->user();

        $query = Pago::with([
            'socio',
            'operador',
            'detalles.inscripcion.grupo.disciplina',
            'anuladoPor',
        ]);

        if ($this->buscar !== '') {
            $query->whereHas('socio', function ($q) {
                $q->where('nombre', 'like', "%{$this->buscar}%")
                  ->orWhere('apellido', 'like', "%{$this->buscar}%")
                  ->orWhere('dni', 'like', "%{$this->buscar}%");
            });
        }

        if ($this->filtroMetodo !== '') {
            $query->where('metodo_pago', $this->filtroMetodo);
        }

        if ($this->filtroEstado !== '') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroDesde !== '') {
            $query->where('fecha_pago', '>=', $this->filtroDesde);
        }

        if ($this->filtroHasta !== '') {
            $query->where('fecha_pago', '<=', $this->filtroHasta);
        }

        // Recaudación: solo pagos activos, solo la dueña puede ver el total general
        $sumaTotal = null;
        if ($user->can('verRecaudacion', Pago::class)) {
            $sumaTotal = (float) (clone $query)->where('estado', Pago::ESTADO_ACTIVO)->sum('importe');
        }

        $pagos = $query->orderByDesc('fecha_pago')->orderByDesc('id')->paginate(15);

        return view('livewire.pagos.listar-pagos', [
            'pagos'      => $pagos,
            'sumaTotal'  => $sumaTotal,
            'mediosPago' => Pago::MEDIOS_PAGO,
            'esDuena'    => $user->esDuena(),
        ])->layout('layouts.app');
    }
}
