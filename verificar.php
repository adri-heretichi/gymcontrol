<?php

// Incluir el cargador automático y arrancar la aplicación Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== SHOW TABLES ===\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $table) {
    $array = (array)$table;
    echo "- " . reset($array) . "\n";
}

$tablas = ['membresias', 'socios', 'aptos_fisicos', 'asistencias', 'pagos'];

foreach ($tablas as $tabla) {
    echo "\n=== DESCRIBE $tabla ===\n";
    echo sprintf("%-20s | %-15s | %-5s | %-5s | %-10s | %s\n", "Campo", "Tipo", "Null", "Clave", "Defecto", "Extra");
    echo str_repeat("-", 80) . "\n";
    $columns = DB::select("DESCRIBE $tabla");
    foreach ($columns as $col) {
        echo sprintf(
            "%-20s | %-15s | %-5s | %-5s | %-10s | %s\n",
            $col->Field,
            $col->Type,
            $col->Null,
            $col->Key,
            $col->Default ?? 'NULL',
            $col->Extra
        );
    }
}
