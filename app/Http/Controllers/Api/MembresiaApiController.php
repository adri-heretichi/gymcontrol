<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membresia;
use Illuminate\Http\Request;

class MembresiaApiController extends Controller
{
    /**
     * Lista todas las membresías del sistema.
     */
    public function index(Request $request)
    {
        $query = Membresia::query();

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $membresias = $query->get();

        return response()->json([
            'success' => true,
            'data' => $membresias
        ], 200);
    }

    /**
     * Muestra el detalle de una membresía específica.
     */
    public function show($id)
    {
        $membresia = Membresia::find($id);

        if (!$membresia) {
            return response()->json([
                'success' => false,
                'message' => 'Membresía no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $membresia
        ], 200);
    }

    /**
     * Crea una nueva membresía (Solo Admin).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|min:3|max:255|unique:membresias,nombre',
            'precio' => 'required|numeric|min:0',
            'horas_mensuales' => 'nullable|integer|min:1',
            'estado' => 'sometimes|required|in:activo,inactivo',
        ]);

        $membresia = Membresia::create([
            'nombre' => trim($validated['nombre']),
            'precio' => $validated['precio'],
            'horas_mensuales' => $validated['horas_mensuales'] ?? null,
            'estado' => $validated['estado'] ?? 'activo',
        ]);

        return response()->json([
            'success' => true,
            'data' => $membresia
        ], 201);
    }

    /**
     * Actualiza una membresía existente (Solo Admin).
     */
    public function update(Request $request, $id)
    {
        $membresia = Membresia::find($id);

        if (!$membresia) {
            return response()->json([
                'success' => false,
                'message' => 'Membresía no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|min:3|max:255|unique:membresias,nombre,' . $membresia->id,
            'precio' => 'sometimes|required|numeric|min:0',
            'horas_mensuales' => 'nullable|integer|min:1',
            'estado' => 'sometimes|required|in:activo,inactivo',
        ]);

        $membresia->update($validated);

        return response()->json([
            'success' => true,
            'data' => $membresia
        ], 200);
    }

    /**
     * Aplica baja lógica a la membresía cambiando su estado a 'inactivo'.
     * No realiza borrado físico.
     */
    public function destroy($id)
    {
        $membresia = Membresia::find($id);

        if (!$membresia) {
            return response()->json([
                'success' => false,
                'message' => 'Membresía no encontrada'
            ], 404);
        }

        if ($membresia->estado === 'inactivo') {
            return response()->json([
                'success' => false,
                'message' => 'La membresía ya se encuentra inactiva'
            ], 400);
        }

        $membresia->update(['estado' => 'inactivo']);

        return response()->json([
            'success' => true,
            'message' => 'Membresía desactivada correctamente (Baja Lógica)'
        ], 200);
    }
}
