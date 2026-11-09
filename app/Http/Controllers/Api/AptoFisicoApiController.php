<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AptoFisico;
use App\Models\Socio;
use App\Services\CertificadoMedicoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AptoFisicoApiController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AptoFisico::class);
        $validated = $request->validate([
            'socio_id' => ['sometimes', 'integer', 'exists:socios,id'],
            'estado' => ['sometimes', 'in:vigente,vencido'],
        ]);

        $aptos = AptoFisico::query()
            ->with('socio:id,nombre,apellido')
            ->when(isset($validated['socio_id']), fn ($query) => $query->where('socio_id', $validated['socio_id']))
            ->when(isset($validated['estado']), fn ($query) => $query->where('estado', $validated['estado']))
            ->latest('fecha_emision')
            ->paginate(15)
            ->through(fn (AptoFisico $apto) => $this->presentar($apto));

        return response()->json(['success' => true, 'data' => $aptos]);
    }

    public function show(AptoFisico $aptoFisico)
    {
        Gate::authorize('view', $aptoFisico);

        return response()->json([
            'success' => true,
            'data' => $this->presentar($aptoFisico->load('socio:id,nombre,apellido')),
        ]);
    }

    public function store(Request $request, CertificadoMedicoService $certificadoMedicoService)
    {
        Gate::authorize('create', AptoFisico::class);
        $validated = $request->validate([
            'socio_id' => ['required', 'integer', 'exists:socios,id'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_emision'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $apto = $certificadoMedicoService->registrarCertificado(
            Socio::query()->findOrFail($validated['socio_id']),
            $validated['fecha_emision'],
            $validated['fecha_vencimiento'],
            $request->user(),
            $request->file('archivo')
        );

        return response()->json([
            'success' => true,
            'data' => $this->presentar($apto),
        ], 201);
    }

    public function descargar(AptoFisico $aptoFisico)
    {
        Gate::authorize('view', $aptoFisico);

        if (!$aptoFisico->archivo || !Storage::disk('local')->exists($aptoFisico->archivo)) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no está disponible.',
            ], 404);
        }

        return Storage::disk('local')->response($aptoFisico->archivo);
    }

    private function presentar(AptoFisico $apto): array
    {
        return [
            'id' => $apto->id,
            'socio' => $apto->socio ? [
                'id' => $apto->socio->id,
                'nombre' => $apto->socio->nombre,
                'apellido' => $apto->socio->apellido,
            ] : null,
            'fecha_emision' => $apto->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $apto->fecha_vencimiento?->toDateString(),
            'estado' => $apto->estado,
            'tiene_archivo' => filled($apto->archivo),
        ];
    }
}
