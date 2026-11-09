<?php

namespace App\Http\Controllers\Api;

use App\Events\GymRealtimeUpdate;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocioApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'buscar' => ['sometimes', 'string', 'max:100'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        $socios = Socio::query()
            ->select([
                'id', 'membresia_id', 'nombre', 'apellido', 'dni', 'sexo',
                'correo', 'celular', 'fecha_alta', 'fecha_vencimiento', 'estado',
            ])
            ->with('membresia:id,nombre')
            ->when(isset($validated['buscar']), function ($query) use ($validated) {
                $buscar = $validated['buscar'];
                $query->where(fn ($subquery) => $subquery
                    ->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('dni', 'like', "%{$buscar}%"));
            })
            ->when(isset($validated['estado']), fn ($query) => $query->where('estado', $validated['estado']))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(15)
            ->through(fn (Socio $socio) => $this->presentar($socio));

        return response()->json(['success' => true, 'data' => $socios]);
    }

    public function show(int $id)
    {
        $socio = Socio::query()
            ->select([
                'id', 'membresia_id', 'nombre', 'apellido', 'dni', 'sexo',
                'correo', 'celular', 'fecha_alta', 'fecha_vencimiento', 'estado',
            ])
            ->with('membresia:id,nombre')
            ->find($id);

        if (!$socio) {
            return response()->json(['success' => false, 'message' => 'Socio no encontrado.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->presentar($socio)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'apellido' => ['required', 'string', 'min:2', 'max:255'],
            'dni' => ['required', 'digits_between:7,10', 'unique:socios,dni'],
            'sexo' => ['required', 'in:M,F'],
            'correo' => ['nullable', 'email', 'unique:socios,correo', 'max:255'],
            'celular' => ['nullable', 'string', 'max:50'],
            'membresia_id' => ['required', 'exists:membresias,id'],
            'fecha_alta' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_alta'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        do {
            $token = (string) random_int(10000000, 99999999);
        } while (Socio::query()->where('token', $token)->exists());

        $socio = DB::transaction(function () use ($validated, $token, $request) {
            $socio = Socio::create([
                'membresia_id' => $validated['membresia_id'],
                'nombre' => trim($validated['nombre']),
                'apellido' => trim($validated['apellido']),
                'dni' => $validated['dni'],
                'sexo' => $validated['sexo'],
                'correo' => isset($validated['correo']) ? trim($validated['correo']) : null,
                'celular' => isset($validated['celular']) ? trim($validated['celular']) : null,
                'token' => $token,
                'fecha_alta' => $validated['fecha_alta'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'estado' => $validated['estado'] ?? 'activo',
                'estado_credencial' => 'sin_emitir',
                'decision_medica' => 'pendiente',
            ]);

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'alta_socio',
                entidad: $socio,
                datosDespues: [
                    'id' => $socio->id,
                    'nombre' => $socio->nombre,
                    'apellido' => $socio->apellido,
                    'estado' => $socio->estado,
                ],
                userId: $request->user()->id
            );
            event(GymRealtimeUpdate::operationalChange('member-created'));

            return $socio;
        });

        return response()->json([
            'success' => true,
            'data' => $this->presentar($socio->load('membresia:id,nombre')),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $socio = Socio::query()->find($id);
        if (!$socio) {
            return response()->json(['success' => false, 'message' => 'Socio no encontrado.'], 404);
        }

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'dni' => ['sometimes', 'required', 'digits_between:7,10', 'unique:socios,dni,' . $socio->id],
            'sexo' => ['sometimes', 'required', 'in:M,F'],
            'correo' => ['sometimes', 'nullable', 'email', 'unique:socios,correo,' . $socio->id, 'max:255'],
            'celular' => ['sometimes', 'nullable', 'string', 'max:50'],
            'membresia_id' => ['sometimes', 'required', 'exists:membresias,id'],
            'estado' => ['sometimes', 'required', 'in:activo,inactivo'],
        ]);

        DB::transaction(function () use ($socio, $validated, $request) {
            $antes = $socio->only(array_keys($validated));
            $socio->update($validated);
            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'socio_actualizado_api',
                entidad: $socio,
                datosAntes: $antes,
                datosDespues: $socio->only(array_keys($validated)),
                userId: $request->user()->id
            );
            event(GymRealtimeUpdate::operationalChange('member-updated'));
        });

        return response()->json([
            'success' => true,
            'data' => $this->presentar($socio->fresh('membresia:id,nombre')),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $socio = Socio::query()->find($id);
        if (!$socio) {
            return response()->json(['success' => false, 'message' => 'Socio no encontrado.'], 404);
        }
        if ($socio->estado === 'inactivo') {
            return response()->json(['success' => false, 'message' => 'El socio ya se encuentra inactivo.'], 409);
        }

        DB::transaction(function () use ($socio, $request) {
            $socio->update(['estado' => 'inactivo']);
            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'socio_desactivado_api',
                entidad: $socio,
                datosAntes: ['estado' => 'activo'],
                datosDespues: ['estado' => 'inactivo'],
                userId: $request->user()->id
            );
            event(GymRealtimeUpdate::operationalChange('member-deactivated'));
        });

        return response()->json([
            'success' => true,
            'message' => 'Socio desactivado correctamente (baja lógica).',
        ]);
    }

    private function presentar(Socio $socio): array
    {
        return [
            'id' => $socio->id,
            'nombre' => $socio->nombre,
            'apellido' => $socio->apellido,
            'dni' => $socio->dni,
            'sexo' => $socio->sexo,
            'correo' => $socio->correo,
            'celular' => $socio->celular,
            'fecha_alta' => $socio->fecha_alta?->toDateString(),
            'fecha_vencimiento' => $socio->fecha_vencimiento?->toDateString(),
            'estado' => $socio->estado,
            'membresia' => $socio->membresia ? [
                'id' => $socio->membresia->id,
                'nombre' => $socio->membresia->nombre,
            ] : null,
        ];
    }
}
