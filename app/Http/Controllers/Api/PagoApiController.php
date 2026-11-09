<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Socio;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PagoApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'socio_id' => ['sometimes', 'integer', 'exists:socios,id'],
            'metodo_pago' => ['sometimes', 'string', 'max:30'],
        ]);

        $pagos = Pago::query()
            ->with('socio:id,nombre,apellido')
            ->when(isset($validated['socio_id']), fn ($query) => $query->where('socio_id', $validated['socio_id']))
            ->when(isset($validated['metodo_pago']), fn ($query) => $query->where('metodo_pago', $validated['metodo_pago']))
            ->orderByDesc('fecha_pago')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $pagos]);
    }

    public function show(int $id)
    {
        $pago = Pago::query()->with(['socio:id,nombre,apellido', 'detalles'])->find($id);
        if (!$pago) {
            return response()->json(['success' => false, 'message' => 'Pago no encontrado.'], 404);
        }

        return response()->json(['success' => true, 'data' => $pago]);
    }

    public function store(Request $request, PagoService $pagoService)
    {
        Gate::authorize('registrar-pago');
        $validated = $request->validate([
            'socio_id' => ['required', 'integer', 'exists:socios,id'],
            'fecha_pago' => ['sometimes', 'date', 'before_or_equal:today'],
            'metodo_pago' => ['required', 'in:' . implode(',', array_merge(array_keys(Pago::MEDIOS_PAGO), ['tarjeta', 'transferencia']))],
            'referencia' => ['nullable', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1', 'max:20'],
            'detalles.*.inscripcion_id' => ['required', 'integer', 'exists:inscripciones,id'],
            'detalles.*.concepto' => ['required', 'string', 'max:255'],
            'detalles.*.fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'detalles.*.fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:detalles.*.fecha_inicio'],
            'detalles.*.subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $inscripcionesIds = collect($validated['detalles'])->pluck('inscripcion_id')->unique();
        $inscripcionesValidas = Inscripcion::query()
            ->where('socio_id', $validated['socio_id'])
            ->whereIn('id', $inscripcionesIds)
            ->count();
        if ($inscripcionesValidas !== $inscripcionesIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Cada detalle debe corresponder a una inscripción del socio indicado.',
            ], 422);
        }

        try {
            $pago = $pagoService->registrarPago(
                Socio::query()->findOrFail($validated['socio_id']),
                $validated['detalles'],
                $validated['metodo_pago'],
                $request->user(),
                $validated['referencia'] ?? null,
                $validated['fecha_pago'] ?? null
            );
        } catch (\InvalidArgumentException|\DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $pago->id,
                'socio_id' => $pago->socio_id,
                'fecha_pago' => $pago->fecha_pago?->toDateString(),
                'importe' => $pago->importe,
                'metodo_pago' => $pago->metodo_pago,
                'estado' => $pago->estado,
                'detalles' => $pago->detalles->map(fn ($detalle) => [
                    'inscripcion_id' => $detalle->inscripcion_id,
                    'concepto' => $detalle->concepto,
                    'fecha_inicio_cobertura' => $detalle->fecha_inicio_cobertura?->toDateString(),
                    'fecha_fin_cobertura' => $detalle->fecha_fin_cobertura?->toDateString(),
                    'subtotal' => $detalle->subtotal,
                ]),
            ],
        ], 201);
    }

    public function recaudacion(Request $request)
    {
        Gate::authorize('ver-recaudacion');
        $validated = $request->validate([
            'fecha_desde' => ['sometimes', 'date_format:Y-m-d'],
            'fecha_hasta' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $fechaDesde = isset($validated['fecha_desde'])
            ? Carbon::parse($validated['fecha_desde'])->startOfDay()
            : now()->subDays(30)->startOfDay();
        $fechaHasta = isset($validated['fecha_hasta'])
            ? Carbon::parse($validated['fecha_hasta'])->endOfDay()
            : now()->endOfDay();

        if ($fechaDesde->isAfter($fechaHasta)) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha desde no puede ser posterior a la fecha hasta.',
            ], 422);
        }
        if ($fechaDesde->diffInDays($fechaHasta) > 366) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo para consultas financieras no puede superar 1 año.',
            ], 422);
        }

        $totalesPorMetodo = Pago::activos()
            ->whereDate('fecha_pago', '>=', $fechaDesde->toDateString())
            ->whereDate('fecha_pago', '<=', $fechaHasta->toDateString())
            ->select('metodo_pago', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(importe) as total'))
            ->groupBy('metodo_pago')
            ->get()
            ->keyBy('metodo_pago');
        $consulta = Pago::activos()
            ->whereDate('fecha_pago', '>=', $fechaDesde->toDateString())
            ->whereDate('fecha_pago', '<=', $fechaHasta->toDateString());

        return response()->json([
            'success' => true,
            'data' => [
                'fecha_desde' => $fechaDesde->toDateString(),
                'fecha_hasta' => $fechaHasta->toDateString(),
                'total_recaudado' => (float) (clone $consulta)->sum('importe'),
                'por_metodo' => $totalesPorMetodo->map(fn ($item) => [
                    'cantidad' => (int) $item->cantidad,
                    'total' => (float) $item->total,
                ]),
                'cantidad_pagos' => (clone $consulta)->count(),
            ],
        ]);
    }
}
