<?php

namespace App\Console\Commands;

use App\Models\Socio;
use Illuminate\Console\Command;

class NormalizarTokens extends Command
{
    /**
     * El nombre y firma del comando Artisan.
     *
     * @var string
     */
    protected $signature = 'tokens:normalizar';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Normaliza los tokens de todos los socios a formato secuencial (0001, 0002, etc.).';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $socios = Socio::orderBy('id', 'asc')->get();
        $this->info("Iniciando normalización de tokens para " . $socios->count() . " socios...");

        $numero = 1;
        $headers = ['ID', 'Nombre', 'Token Anterior', 'Token Nuevo'];
        $rows = [];

        $qrDir = storage_path('app/private/qrs');
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0755, true);
        }

        foreach ($socios as $socio) {
            $tokenAnterior = $socio->token;
            $tokenNuevo = str_pad($numero, 4, '0', STR_PAD_LEFT);
            
            $socio->update(['token' => $tokenNuevo]);

            // Regenerar el QR con el nuevo token secuencial (SVG)
            $qrPath = $qrDir . '/qr_' . $socio->id . '.svg';
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(200)
                ->generate(config('app.url') . '/terminal/scan/' . $tokenNuevo, $qrPath);
            
            $rows[] = [
                $socio->id,
                $socio->nombre . ' ' . $socio->apellido,
                $tokenAnterior,
                $tokenNuevo
            ];

            $numero++;
        }

        $this->table($headers, $rows);
        $this->info("¡Normalización finalizada con éxito y códigos QR actualizados!");
    }
}
