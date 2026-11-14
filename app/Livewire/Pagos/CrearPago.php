<?php

namespace App\Livewire\Pagos;

use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Socio;
use App\Services\PagoService;
use Carbon\Carbon;
use Livewire\Component;

/**
 * CrearPago – Fase 5.
 *
 * Permite registrar un pago con uno o varios conceptos (detalles) por inscripción.
 * Utiliza PagoService como única fuente de lógica de negocio.
 *
 * FLUJO:
 *   1. Operador busca/selecciona socio.
 *   2. Sistema muestra inscripciones activas del socio con su estado de cobertura.
 *   3. Operador selecciona inscripción(es), indica período y monto por cada una.
 *   4. Sistema calcula el total y valida todo en servidor.
 *   5. Se guarda mediante PagoService (transaccional).
 *   6. Mensaje de confirmación.
 *
 * RESTRICCIONES:
 *   - No aprueba inscripciones.
 *   - No emite credenciales.
 *   - No modifica fecha_vencimiento global del socio.
 */
class CrearPago extends Component
{
    // ── Datos del pago ──────────────────────────────────────────────────────────
    public ?int   $socio_id       = null;
    public string $buscarSocio    = '';
    public string $fecha_pago     = '';
    public string $metodo_pago    = 'efectivo';
    public string $referencia     = '';

    // ── Detalles multi-concepto ─────────────────────────────────────────────────
    // Cada elemento: ['inscripcion_id', 'concepto', 'fecha_inicio', 'fecha_fin', 'subtotal']
    public array $detalles = [];

    // ── Estado UI ───────────────────────────────────────────────────────────────
    public bool   $deshabilitarSocio     = false;
    public ?Socio $socioPreseleccionado  = null;
    public array  $inscripcionesDisponibles = [];
    public array  $coberturaActual          = [];

    // ── Errores UI ──────────────────────────────────────────────────────────────
    public ?string $errorGeneral = null;

    public function mount(?Socio $socio = null): void
    {
        $this->authorize('create', Pago::class);

        $this->fecha_pago = Carbon::now(config('app.timezone'))->format('Y-m-d');
        $this->detalles   = [$this->detalleVacio()];

        if ($socio && $socio->exists) {
            $this->socio_id             = $socio->id;
            $this->socioPreseleccionado = $socio;
            $this->deshabilitarSocio    = true;
            $this->buscarSocio          = "{$socio->apellido}, {$socio->nombre} (DNI {$socio->dni})";
            $this->cargarInscripciones();

            $inscripcionId = (int) request()->query('inscripcion', 0);
            if ($inscripcionId && collect($this->inscripcionesDisponibles)->contains('id', $inscripcionId)) {
                $this->detalles[0]['inscripcion_id'] = (string) $inscripcionId;
                $this->actualizarFechasDetalle(0);
            }
        }
    }

    // ── Reactivos ───────────────────────────────────────────────────────────────

    public function updatedSocioId($value): void
    {
        $this->cargarInscripciones();
        $this->detalles = [$this->detalleVacio()];
        $this->errorGeneral = null;
    }

    public function seleccionarSocio(int $id): void
    {
        $socio = Socio::findOrFail($id);
        $this->socio_id = $socio->id;
        $this->buscarSocio = "{$socio->apellido}, {$socio->nombre} (DNI {$socio->dni})";
        $this->cargarInscripciones();
        $this->detalles = [$this->detalleVacio()];
        $this->errorGeneral = null;
    }

    public function limpiarSocio(): void
    {
        $this->socio_id = null;
        $this->buscarSocio = '';
        $this->inscripcionesDisponibles = [];
        $this->detalles = [$this->detalleVacio()];
        $this->errorGeneral = null;
    }

    public function updatedDetalles($value, ?string $key = null): void
    {
        $this->errorGeneral = null;

        if ($key && str_ends_with($key, '.inscripcion_id')) {
            $index = (int) explode('.', $key)[0];
            $this->actualizarFechasDetalle($index);
        }
    }

    // ── Métodos públicos ────────────────────────────────────────────────────────

    /** Agrega una fila de detalle vacía al formulario. */
    public function agregarDetalle(): void
    {
        $this->detalles[] = $this->detalleVacio();
    }

    /** Elimina una fila de detalle por índice. */
    public function quitarDetalle(int $index): void
    {
        if (count($this->detalles) > 1) {
            array_splice($this->detalles, $index, 1);
            $this->detalles = array_values($this->detalles);
        }
    }

    /** Calcula el total sumando los subtotales de los detalles. */
    public function getImporteTotalProperty(): float
    {
        return collect($this->detalles)->sum(fn ($d) => (float) ($d['subtotal'] ?? 0));
    }

    /**
     * Cuando el operador selecciona una inscripción en un detalle,
     * precarga la próxima fecha sugerida de cobertura.
     */
    public function actualizarFechasDetalle(int $index): void
    {
        $inscripcionId = $this->detalles[$index]['inscripcion_id'] ?? null;
        if (!$inscripcionId) {
            return;
        }

        $inscripcion = Inscripcion::query()
            ->with('grupo.disciplina')
            ->where('socio_id', $this->socio_id)
            ->whereIn('estado', [Inscripcion::ESTADO_APROBADA, Inscripcion::ESTADO_PENDIENTE])
            ->find($inscripcionId);
        if (!$inscripcion) {
            return;
        }

        $service = app(PagoService::class);
        $inicio  = $service->obtenerProximaFechaInicio($inscripcion);
        // Fin sugerido: último día del mes del inicio (guía, no regla fija)
        $finSugerido = Carbon::parse($inicio)->endOfMonth()->toDateString();

        $this->detalles[$index]['fecha_inicio'] = $inicio;
        $this->detalles[$index]['fecha_fin']    = $finSugerido;
        $this->detalles[$index]['subtotal'] = $inscripcion->grupo?->disciplina?->precio ?? '';
        $disciplina = $inscripcion->grupo?->disciplina?->nombre ?? 'Membresía';
        $this->detalles[$index]['concepto'] = 'Cuota ' . Carbon::parse($inicio)->format('m/Y') . ' - ' . $disciplina;

        // Mostrar cobertura actual de esta inscripción
        $service = app(PagoService::class);
        $this->coberturaActual[$inscripcionId] =
            $service->consultarCoberturaInscripcion($inscripcion);
    }

    /** Guarda el pago usando PagoService (transaccional). */
    public function guardar(): void
    {
        $this->authorize('create', Pago::class);
        $this->errorGeneral = null;

        if ($this->socio_id && count($this->detalles)) {
            $ids = collect($this->detalles)
                ->pluck('inscripcion_id')
                ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
                ->map(fn ($id) => (int) $id)
                ->unique();
            $inscripcionesSinPrecio = Inscripcion::query()
                ->with('grupo.disciplina')
                ->where('socio_id', $this->socio_id)
                ->whereIn('id', $ids)
                ->get()
                ->first(fn (Inscripcion $inscripcion) => $inscripcion->grupo?->disciplina?->precio === null);

            if ($inscripcionesSinPrecio) {
                $this->errorGeneral = "La membresía '{$inscripcionesSinPrecio->grupo?->disciplina?->nombre}' no tiene precio configurado. Defina el precio desde Membresías antes de registrar el pago.";
                return;
            }
        }

        // Validación Livewire
        $this->validate($this->rules(), $this->messages());

        $socio = Socio::findOrFail($this->socio_id);
        $operador = auth()->user();

        try {
            $inscripcionIds = collect($this->detalles)
                ->pluck('inscripcion_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique();
            $inscripciones = Inscripcion::with('grupo.disciplina')
                ->where('socio_id', $socio->id)
                ->whereIn('id', $inscripcionIds)
                ->get();

            if ($inscripciones->count() !== $inscripcionIds->count()) {
                throw new \InvalidArgumentException('Cada detalle debe corresponder a una inscripción del socio seleccionado.');
            }

            $service = app(PagoService::class);
            $pago = $service->registrarPago(
                socio: $socio,
                detalles: $this->detalles,
                medioPago: $this->metodo_pago,
                operador: $operador,
                referencia: $this->referencia ?: null,
                fechaPago: $this->fecha_pago,
            );

            session()->flash('message',
                "✓ Pago registrado para {$socio->nombre} {$socio->apellido}. "
                . "Total: \$" . number_format($pago->importe, 2, ',', '.') . "."
            );

            $this->dispatch('pagoRegistrado');
            $this->redirect(route('pagos.index'), navigate: true);

        } catch (\InvalidArgumentException | \DomainException | \RuntimeException $e) {
            $this->errorGeneral = $e->getMessage();
        }
    }

    // ── Render ──────────────────────────────────────────────────────────────────

    public function render(): \Illuminate\View\View
    {
        $socios = [];
        if (!$this->deshabilitarSocio) {
            $termino = trim($this->buscarSocio);
            if (!$this->socio_id && mb_strlen($termino) >= 2) {
                $socios = Socio::query()
                    ->where('estado', 'activo')
                    ->where(function ($query) use ($termino) {
                        $query->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('apellido', 'like', "%{$termino}%")
                            ->orWhere('dni', 'like', "%{$termino}%");
                    })
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->limit(8)
                    ->get();
            }
        }

        return view('livewire.pagos.crear-pago', [
            'socios'       => $socios,
            'socioSeleccionado' => $this->socio_id ? Socio::find($this->socio_id) : null,
            'mediosPago'   => Pago::MEDIOS_PAGO,
            'importeTotal' => $this->importeTotal,
        ])->layout('layouts.app');
    }

    // ── Internos ────────────────────────────────────────────────────────────────

    private function detalleVacio(): array
    {
        return [
            'inscripcion_id' => '',
            'concepto'       => '',
            'fecha_inicio'   => $this->fecha_pago ?: Carbon::now(config('app.timezone'))->toDateString(),
            'fecha_fin'      => '',
            'subtotal'       => '',
        ];
    }

    private function cargarInscripciones(): void
    {
        $this->inscripcionesDisponibles = [];
        if (!$this->socio_id) {
            return;
        }

        $inscripciones = Inscripcion::with('grupo.disciplina')
            ->where('socio_id', $this->socio_id)
            ->whereIn('estado', [Inscripcion::ESTADO_APROBADA, Inscripcion::ESTADO_PENDIENTE])
            ->get();

        $service = app(PagoService::class);

        $this->inscripcionesDisponibles = $inscripciones->map(function (Inscripcion $insc) use ($service) {
            $cobertura = $service->consultarCoberturaInscripcion($insc);
            return [
                'id'         => $insc->id,
                'label'      => ($insc->grupo?->disciplina?->nombre ?? 'Sin disciplina')
                    . ' – ' . ($insc->grupo?->nombre ?? ''),
                'precio' => $insc->grupo?->disciplina?->precio,
                'estado_inscripcion' => $insc->estado,
                'cobertura_vigente'  => $cobertura['vigente'],
                'fecha_fin'          => $cobertura['fecha_fin'],
            ];
        })->toArray();
    }

    protected function rules(): array
    {
        $rules = [
            'socio_id'    => 'required|exists:socios,id',
            'fecha_pago'  => 'required|date',
            'metodo_pago' => 'required|in:efectivo,debito,credito,mercadopago,tarjeta,transferencia',
            'referencia'  => 'nullable|string|max:100',
            'detalles'    => 'required|array|min:1',
        ];

        foreach ($this->detalles as $i => $det) {
            $rules["detalles.{$i}.concepto"]       = 'required|string|max:150';
            $rules["detalles.{$i}.fecha_inicio"]   = 'required|date';
            $rules["detalles.{$i}.fecha_fin"]      = "required|date|after_or_equal:detalles.{$i}.fecha_inicio";
            $rules["detalles.{$i}.subtotal"]       = 'required|numeric|min:0';
            $rules["detalles.{$i}.inscripcion_id"] = 'required|integer|exists:inscripciones,id';
        }

        return $rules;
    }

    protected function messages(): array
    {
        $msgs = [
            'socio_id.required'   => 'Seleccioná un socio.',
            'socio_id.exists'     => 'El socio seleccionado no existe.',
            'fecha_pago.required' => 'La fecha de pago es obligatoria.',
            'fecha_pago.date'     => 'La fecha de pago no es válida.',
            'metodo_pago.required'=> 'Seleccioná un medio de pago.',
            'metodo_pago.in'      => 'Medio de pago no reconocido.',
            'detalles.required'   => 'Agregá al menos un concepto.',
            'detalles.min'        => 'Agregá al menos un concepto.',
        ];

        foreach ($this->detalles as $i => $det) {
            $n = $i + 1;
            $msgs["detalles.{$i}.concepto.required"]     = "El concepto del ítem #{$n} es obligatorio.";
            $msgs["detalles.{$i}.fecha_inicio.required"] = "La fecha de inicio del ítem #{$n} es obligatoria.";
            $msgs["detalles.{$i}.fecha_fin.required"]    = "La fecha de fin del ítem #{$n} es obligatoria.";
            $msgs["detalles.{$i}.fecha_fin.after_or_equal"] = "La fecha de fin del ítem #{$n} no puede ser anterior al inicio.";
            $msgs["detalles.{$i}.subtotal.required"]     = "El importe del ítem #{$n} es obligatorio.";
            $msgs["detalles.{$i}.subtotal.min"]          = "El importe del ítem #{$n} no puede ser negativo.";
        }

        return $msgs;
    }
}
