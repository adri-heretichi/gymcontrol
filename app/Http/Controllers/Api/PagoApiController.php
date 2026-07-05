<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PagoApiController extends Controller
{
    /**
     * Lista los pagos registrados.
     */
    public function index(Request $request)
    {
        $query = Pago::with('socio.membresia');

        if ($request->filled('socio_id')) {
            $query->where('socio_id', $request->input('socio_id'));
        }

        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->input('metodo_pago'));
        }

        $pagos = $query->orderBy('fecha_pago', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $pagos
        ], 200);
    }

    /**
     * Muestra el detalle de un pago específico.
     */
    public function show($id)
    {
        $pago = Pago::with('socio.membresia')->find($id);

        if (!$pago) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pago
        ], 200);
    }

    /**
     * Registra un nuevo cobro a un socio y extiende su membresía.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'socio_id' => 'required|exists:socios,id',
            'fecha_pago' => 'required|date',
            'importe' => 'required|numeric|min:0',
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia',
        ]);

        $socio = Socio::findOrFail($validated['socio_id']);

        // El socio debe estar activo
        if ($socio->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede registrar un cobro para un socio inactivo'
            ], 400);
        }

        $fechaPago = Carbon::parse($validated['fecha_pago']);

        // Calcular nueva fecha de vencimiento según reglas
        if ($socio->fecha_vencimiento && $socio->fecha_vencimiento->isFuture()) {
            $nuevaFecha = $socio->fecha_vencimiento->copy()->addMonth();
        } else {
            $nuevaFecha = $fechaPago->copy()->addMonth();
        }

        // Crear el pago
        $pago = Pago::create([
            'socio_id' => $validated['socio_id'],
            'fecha_pago' => $validated['fecha_pago'],
            'importe' => $validated['importe'],
            'metodo_pago' => $validated['metodo_pago'],
        ]);

        // Actualizar fecha de vencimiento en el socio
        $socio->update([
            'fecha_vencimiento' => $nuevaFecha,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'pago' => $pago,
                'nueva_fecha_vencimiento' => $nuevaFecha->toDateString()
            ]
        ], 201);
    }

    /**
     * Muestra la recaudación totalizada filtrada por fechas (Solo para Administradores).
     */
    public function recaudacion(Request $request)
    {
        // Defensa de rol adicional al middleware
        if ($request->user()->rol !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado a reportes financieros.'
            ], 403);
        }

        $fechaDesdeInput = $request->input('fecha_desde');
        $fechaHastaInput = $request->input('fecha_hasta');

        try {
            $fechaDesde = $fechaDesdeInput ? Carbon::parse($fechaDesdeInput)->startOfDay() : now()->subDays(30)->startOfDay();
            $fechaHasta = $fechaHastaInput ? Carbon::parse($fechaHastaInput)->endOfDay() : now()->endOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de fecha inválido.'
            ], 400);
        }

        // Validaciones de rango
        if ($fechaDesde->isAfter($fechaHasta)) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha desde no puede ser posterior a la fecha hasta.'
            ], 400);
        }

        if ($fechaDesde->diffInDays($fechaHasta) > 366) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo para consultas financieras no puede superar 1 año.'
            ], 400);
        }

        // Consulta filtrada
        $pagos = Pago::whereBetween('fecha_pago', [$fechaDesde, $fechaHasta])->get();

        $sumaTotal = $pagos->sum('importe');
        $cantidad = $pagos->count();

        $porMetodo = [
            'efectivo' => $pagos->where('metodo_pago', 'efectivo')->sum('importe'),
            'tarjeta' => $pagos->where('metodo_pago', 'tarjeta')->sum('importe'),
            'transferencia' => $pagos->where('metodo_pago', 'transferencia')->sum('importe'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'fecha_desde' => $fechaDesde->toDateString(),
                'fecha_hasta' => $fechaHasta->toDateString(),
                'total_recaudado' => $sumaTotal,
                'por_metodo' => $porMetodo,
                'cantidad_pagos' => $cantidad
            ]
        ], 200);
    }
}
