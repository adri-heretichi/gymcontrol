<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Socio;
use Illuminate\Http\Request;

class SocioApiController extends Controller
{
    /**
     * Lista los socios con filtros opcionales de búsqueda y estado.
     */
    public function index(Request $request)
    {
        $query = Socio::with('membresia');

        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $socios = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $socios
        ], 200);
    }

    /**
     * Muestra la ficha de un socio específico.
     */
    public function show($id)
    {
        $socio = Socio::with(['membresia', 'pagos', 'asistencias', 'aptosFisicos'])->find($id);

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Socio no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $socio
        ], 200);
    }

    /**
     * Crea un nuevo socio en el sistema.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|min:2|max:255',
            'apellido' => 'required|string|min:2|max:255',
            'dni' => 'required|digits_between:7,10|unique:socios,dni',
            'sexo' => 'required|in:M,F',
            'correo' => 'nullable|email|unique:socios,correo|max:255',
            'celular' => 'nullable|string|max:50',
            'membresia_id' => 'required|exists:membresias,id',
            'fecha_alta' => 'required|date',
            'fecha_vencimiento' => 'required|date|after_or_equal:fecha_alta',
        ]);

        // Generar token único de 8 dígitos de forma segura
        do {
            $token = (string) random_int(10000000, 99999999);
        } while (Socio::where('token', $token)->exists());

        $socio = Socio::create([
            'membresia_id' => $validated['membresia_id'],
            'nombre' => trim($validated['nombre']),
            'apellido' => trim($validated['apellido']),
            'dni' => $validated['dni'],
            'sexo' => $validated['sexo'],
            'correo' => $request->filled('correo') ? trim($validated['correo']) : null,
            'celular' => $request->filled('celular') ? trim($validated['celular']) : null,
            'foto' => null,
            'token' => $token,
            'qr' => null,
            'fecha_alta' => $validated['fecha_alta'],
            'fecha_vencimiento' => $validated['fecha_vencimiento'],
            'estado' => $request->input('estado', 'activo'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $socio
        ], 201);
    }

    /**
     * Actualiza los datos de un socio existente.
     */
    public function update(Request $request, $id)
    {
        $socio = Socio::find($id);

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Socio no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|min:2|max:255',
            'apellido' => 'sometimes|required|string|min:2|max:255',
            'dni' => 'sometimes|required|digits_between:7,10|unique:socios,dni,' . $socio->id,
            'sexo' => 'sometimes|required|in:M,F',
            'correo' => 'nullable|email|unique:socios,correo,' . $socio->id . '|max:255',
            'celular' => 'nullable|string|max:50',
            'membresia_id' => 'sometimes|required|exists:membresias,id',
            'fecha_alta' => 'sometimes|required|date',
            'fecha_vencimiento' => 'sometimes|required|date|after_or_equal:fecha_alta',
            'estado' => 'sometimes|required|in:activo,inactivo',
        ]);

        $socio->update($validated);

        return response()->json([
            'success' => true,
            'data' => $socio
        ], 200);
    }

    /**
     * Aplica baja lógica al socio cambiando su estado a 'inactivo'.
     * No realiza borrado físico.
     */
    public function destroy($id)
    {
        $socio = Socio::find($id);

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Socio no encontrado'
            ], 404);
        }

        if ($socio->estado === 'inactivo') {
            return response()->json([
                'success' => false,
                'message' => 'El socio ya se encuentra inactivo'
            ], 400);
        }

        $socio->update(['estado' => 'inactivo']);

        return response()->json([
            'success' => true,
            'message' => 'Socio desactivado correctamente (Baja Lógica)'
        ], 200);
    }
}
