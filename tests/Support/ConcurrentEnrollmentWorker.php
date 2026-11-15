<?php

use App\Models\Grupo;
use App\Models\Socio;
use App\Models\User;
use App\Services\InscripcionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$database = $argv[1];
$barrier = $argv[2];
$candidate = (int) $argv[3];
if (! str_starts_with(basename(dirname($database)), 'GymControlConcurrency_') || dirname($database) !== $barrier) {
    throw new RuntimeException('Base de concurrencia fuera del aislamiento');
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 5000, 'broadcasting.default' => 'null', 'queue.default' => 'sync']);
DB::purge('sqlite');
$s = Socio::findOrFail($candidate);
$g = Grupo::firstOrFail();
$u = User::firstOrFail();
file_put_contents($barrier.'/ready-'.$candidate, 'ready');
$limit = microtime(true) + 20;
while (! file_exists($barrier.'/GO')) {
    if (microtime(true) > $limit) {
        throw new RuntimeException('Barrera agotada');
    }usleep(2000);
}
$start = microtime(true);
try {
    if (($argv[4] ?? 'enroll') === 'wait') {
        app(InscripcionService::class)->registrarEnListaEspera($s, $g, $u);
        $status = 'waiting';
    } else {
        app(InscripcionService::class)->solicitarInscripcion($s, $g, $u);
        $status = 'confirmed';
    }
} catch (DomainException $e) {
    $status = 'rejected_full';
} catch (Throwable $e) {
    $status = get_class($e);
}
echo json_encode(['pid' => getmypid(), 'start' => $start, 'end' => microtime(true), 'status' => $status]);
