<?php

namespace App\Http\Controllers;

use App\Models\Socio;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrController extends Controller
{
    /**
     * Sirve el código QR del socio en formato SVG desde el disco privado.
     * Si por algún motivo el archivo no existe, lo regenera dinámicamente.
     */
    public function show(Socio $socio)
    {
        // 1. Verificación de sesión
        if (!auth()->check()) {
            abort(403, 'Sesión no válida.');
        }

        $qrDir = storage_path('app/private/qrs');
        $qrPath = $qrDir . '/qr_' . $socio->id . '.svg';

        // 2. Regenerar en caso de que no exista
        if (!file_exists($qrPath)) {
            if (!file_exists($qrDir)) {
                mkdir($qrDir, 0755, true);
            }
            QrCode::format('svg')
                ->size(200)
                ->generate(config('app.url') . '/terminal/scan/' . $socio->token, $qrPath);
        }

        // 3. Servir el archivo como respuesta de tipo image/svg+xml
        return response()->file($qrPath, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
