<?php

namespace App\Http\Controllers;

use App\Models\Socio;
use Illuminate\Support\Facades\Gate;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrController extends Controller
{
    /**
     * Genera el QR a partir del token persistente de la credencial activa.
     */
    public function show(Socio $socio)
    {
        Gate::authorize('view', $socio);

        abort_unless($socio->tieneCredencialesActivas(), 404, 'El socio no tiene una credencial activa.');

        $contenido = QrCode::format('svg')
            ->size(200)
            ->generate(config('app.url') . '/terminal/scan/' . $socio->qr_token);

        return response($contenido, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
