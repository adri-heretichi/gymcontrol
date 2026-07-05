<?php

namespace App\Http\Controllers;

use App\Models\Socio;
use App\Models\Asistencia;
use App\Models\Pago;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteController extends Controller
{
    /**
     * Genera el PDF con la Ficha de Socio completa.
     */
    public function fichaSocio(Socio $socio)
    {
        // Cargar relaciones necesarias de manera optimizada
        $socio->load([
            'membresia',
            'pagos' => function ($query) {
                $query->orderBy('fecha_pago', 'desc')->take(10);
            },
            'asistencias' => function ($query) {
                $query->orderBy('fecha', 'desc')->orderBy('hora_ingreso', 'desc')->take(15);
            },
            'aptosFisicos' => function ($query) {
                $query->orderBy('fecha_emision', 'desc');
            }
        ]);

        $pdf = Pdf::loadView('reportes.ficha-socio', compact('socio'));
        
        return $pdf->stream("ficha_socio_{$socio->dni}.pdf");
    }

    /**
     * Genera el PDF con el listado filtrado de Asistencias.
     */
    public function asistencias(Request $request)
    {
        $query = Asistencia::with('socio.membresia');

        // Búsqueda por Socio
        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->whereHas('socio', function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        // Control y definición de fechas
        $fechaDesdeInput = $request->input('fecha_desde');
        $fechaHastaInput = $request->input('fecha_hasta');

        try {
            $fechaDesde = $fechaDesdeInput ? Carbon::parse($fechaDesdeInput)->startOfDay() : now()->subDays(30)->startOfDay();
            $fechaHasta = $fechaHastaInput ? Carbon::parse($fechaHastaInput)->endOfDay() : now()->endOfDay();
        } catch (\Exception $e) {
            return back()->with('error', 'Formato de fecha inválido.');
        }

        // Validaciones de rango
        if ($fechaDesde->isAfter($fechaHasta)) {
            return back()->with('error', 'La fecha desde no puede ser posterior a la fecha hasta.');
        }

        if ($fechaDesde->diffInDays($fechaHasta) > 366) {
            return back()->with('error', 'El rango máximo de búsqueda para reportes no puede superar 1 año.');
        }

        $query->whereBetween('fecha', [$fechaDesde, $fechaHasta]);

        // Filtrado por Estado
        if ($request->filled('estado')) {
            $estado = $request->input('estado');
            if ($estado === 'en_sala') {
                $query->whereNull('hora_salida');
            } elseif ($estado === 'finalizados') {
                $query->whereNotNull('hora_salida');
            }
        }

        $asistencias = $query->orderBy('fecha', 'desc')
                             ->orderBy('hora_ingreso', 'desc')
                             ->get();

        $pdf = Pdf::loadView('reportes.asistencias', [
            'asistencias' => $asistencias,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'filtros' => $request->only(['buscar', 'estado'])
        ]);

        return $pdf->stream('reporte_asistencias.pdf');
    }

    /**
     * Genera el PDF con el listado filtrado de Pagos (Solo para Administradores).
     */
    public function pagos(Request $request)
    {
        // Defensa de seguridad de rol (adicional al middleware)
        if (auth()->user()->rol !== 'admin') {
            abort(403, 'Acceso denegado a reportes financieros.');
        }

        $query = Pago::with('socio.membresia');

        // Búsqueda por Socio
        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->whereHas('socio', function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        // Control y definición de fechas
        $fechaDesdeInput = $request->input('fecha_desde');
        $fechaHastaInput = $request->input('fecha_hasta');

        try {
            $fechaDesde = $fechaDesdeInput ? Carbon::parse($fechaDesdeInput)->startOfDay() : now()->subDays(30)->startOfDay();
            $fechaHasta = $fechaHastaInput ? Carbon::parse($fechaHastaInput)->endOfDay() : now()->endOfDay();
        } catch (\Exception $e) {
            return back()->with('error', 'Formato de fecha inválido.');
        }

        // Validaciones de rango
        if ($fechaDesde->isAfter($fechaHasta)) {
            return back()->with('error', 'La fecha desde no puede ser posterior a la fecha hasta.');
        }

        if ($fechaDesde->diffInDays($fechaHasta) > 366) {
            return back()->with('error', 'El rango máximo de búsqueda para reportes no puede superar 1 año.');
        }

        $query->whereBetween('fecha_pago', [$fechaDesde, $fechaHasta]);

        // Filtrado por Método de Pago
        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->input('metodo_pago'));
        }

        $pagos = $query->orderBy('fecha_pago', 'desc')->get();
        $sumaTotal = $pagos->sum('importe');

        // Calcular distribución por método para resumen ejecutivo
        $metodos = [
            'efectivo' => $pagos->where('metodo_pago', 'efectivo')->sum('importe'),
            'tarjeta' => $pagos->where('metodo_pago', 'tarjeta')->sum('importe'),
            'transferencia' => $pagos->where('metodo_pago', 'transferencia')->sum('importe'),
        ];

        $pdf = Pdf::loadView('reportes.pagos', [
            'pagos' => $pagos,
            'sumaTotal' => $sumaTotal,
            'metodos' => $metodos,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'filtros' => $request->only(['buscar', 'metodo_pago'])
        ]);

        return $pdf->stream('reporte_pagos.pdf');
    }

    /**
     * Genera la tarjeta imprimible del socio (PDF CR80: 85mm x 54mm).
     * Muestra únicamente el nombre/apellido en grande y el código QR centrado.
     */
    public function tarjeta(Socio $socio)
    {
        $qrPath = storage_path('app/private/qrs/qr_' . $socio->id . '.svg');

        // Regenerar el QR si por algún motivo no existe en el disco
        if (!file_exists($qrPath)) {
            $qrDir = storage_path('app/private/qrs');
            if (!file_exists($qrDir)) {
                mkdir($qrDir, 0755, true);
            }
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(200)
                ->generate(config('app.url') . '/terminal/scan/' . $socio->token, $qrPath);
        }

        // Codificar la imagen en base64 para que DomPDF la renderice correctamente desde memoria
        $svgContenido = file_get_contents($qrPath);
        $svgData = 'data:image/svg+xml;base64,' . base64_encode($svgContenido);

        $pdf = Pdf::loadView('reportes.tarjeta', compact('socio', 'svgData'));
        $pdf->setPaper([0, 0, 153.07, 243.78], 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('dpi', 96);
        $pdf->setOption('margin-top', 0);
        $pdf->setOption('margin-right', 0);
        $pdf->setOption('margin-bottom', 0);
        $pdf->setOption('margin-left', 0);
        return $pdf->stream('tarjeta-socio.pdf');
    }
}
