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

        foreach ($socios as $socio) {
            $tokenAnterior = $socio->token;
            $tokenNuevo = str_pad($numero, 4, '0', STR_PAD_LEFT);
            
            $socio->update(['token' => $tokenNuevo]);

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
