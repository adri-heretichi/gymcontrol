<?php

namespace App\Http\Controllers;

use App\Models\AptoFisico;
use Illuminate\Support\Facades\Storage;

class AptoFisicoController extends Controller
{
    /**
     * Descarga de forma segura un archivo de apto físico.
     * Acceso restringido por el middleware 'auth' en las rutas.
     */
    public function descargar(AptoFisico $aptoFisico)
    {
        // 1. Verificación defensiva de sesión
        if (!auth()->check()) {
            abort(403, 'Sesión no válida.');
        }

        // 2. Comprobación de existencia del archivo en el disco privado
        if (is_null($aptoFisico->archivo)) {
            abort(404, 'No se ha cargado ningún archivo para este apto físico.');
        }

        if (!Storage::disk('local')->exists($aptoFisico->archivo)) {
            abort(404, 'El archivo del apto físico no existe en el almacenamiento.');
        }

        // 3. Servimos el archivo con la respuesta nativa de Laravel (conservando el MIME type y previniendo el listado público)
        return Storage::disk('local')->response($aptoFisico->archivo);
    }
}
