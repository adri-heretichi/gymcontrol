<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AptoFisico;
use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AptoFisicoApiController extends Controller
{
    /**
     * Lista los registros de aptos físicos.
     */
    public function index(Request $request)
    {
        $query = AptoFisico::with('socio');

        if ($request->filled('socio_id')) {
            $query->where('socio_id', $request->input('socio_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $aptos = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $aptos
        ], 200);
    }

    /**
     * Muestra el detalle de un apto físico.
     */
    public function show($id)
    {
        $apto = AptoFisico::with('socio')->find($id);

        if (!$apto) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de apto físico no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $apto
        ], 200);
    }

    /**
     * Registra un nuevo certificado médico de apto físico (con carga de archivo).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'socio_id' => 'required|exists:socios,id',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'required|date|after_or_equal:fecha_emision',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096', // Max 4MB
        ]);

        // Cargar el archivo de forma segura y privada
        $path = $request->file('archivo')->store('secure/aptos_fisicos', 'local');

        // Determinar estado dinámicamente
        $estado = Carbon::parse($validated['fecha_vencimiento'])->isPast() ? 'vencido' : 'vigente';

        $apto = AptoFisico::create([
            'socio_id' => $validated['socio_id'],
            'archivo' => $path,
            'fecha_emision' => $validated['fecha_emision'],
            'fecha_vencimiento' => $validated['fecha_vencimiento'],
            'estado' => $estado,
        ]);

        return response()->json([
            'success' => true,
            'data' => $apto
        ], 201);
    }

    /**
     * Descarga de forma segura el archivo del certificado médico.
     */
    public function descargar($id)
    {
        $apto = AptoFisico::find($id);

        if (!$apto) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de apto físico no encontrado'
            ], 404);
        }

        if (!$apto->archivo || !Storage::disk('local')->exists($apto->archivo)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo físico del certificado no existe en el servidor'
            ], 404);
        }

        return Storage::disk('local')->response($apto->archivo);
    }
}
