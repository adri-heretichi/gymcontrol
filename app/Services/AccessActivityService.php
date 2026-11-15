<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Socio;

class AccessActivityService
{
    public function recent()
    {
        return Auditoria::where('tipo_evento', 'acceso')->orderByDesc('created_at')->orderByDesc('id')->limit(12)
            ->with(['auditable' => fn ($query) => $query->select(['id', 'nombre', 'apellido'])])
            ->get(['id', 'accion', 'auditable_type', 'auditable_id', 'datos_despues', 'created_at'])
            ->map(function (Auditoria $evento) {
                $datos = $evento->datos_despues ?? [];
                $salida = ($datos['metodo'] ?? '') === 'salida';

                return ['id' => $evento->id, 'socio' => $evento->auditable instanceof Socio ? trim($evento->auditable->nombre.' '.$evento->auditable->apellido) : 'Credencial no identificada',
                    'momento' => $evento->created_at, 'operacion' => $salida ? 'Salida' : ($evento->accion === 'acceso_autorizado' ? 'Autorizado' : 'Denegado'),
                    'metodo' => match ($datos['metodo'] ?? '') {
                        'pin' => 'PIN', 'qr' => 'QR', 'salida' => 'Salida', default => 'Acceso'
                    },
                    'motivo' => $evento->accion === 'acceso_autorizado' ? ($salida ? 'Salida registrada.' : 'Acceso autorizado.') : ControlAccesoService::mensajeOperativo((string) ($datos['resultado'] ?? ''))];
            });
    }
}
