<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SqliteConcurrentEnrollmentTest extends TestCase
{
    public function test_two_processes_compete_for_last_slot_without_overbooking_or_database_error(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Event::fake([GymStateChanged::class]);
        $dir = str_replace('\\', '/', sys_get_temp_dir()).'/GymControlConcurrency_'.bin2hex(random_bytes(6));
        mkdir($dir);
        $database = $dir.'/test.sqlite';
        touch($database);
        $original = config('database.connections.sqlite');
        $processes = [];
        try {
            config(['database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 5000]);
            DB::purge('sqlite');
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            DB::statement('PRAGMA journal_mode=WAL');
            User::factory()->create(['rol' => 'recepcionista']);
            $d = Disciplina::create(['nombre' => 'Funcional', 'precio' => 35000, 'estado' => 'activo']);
            $g = Grupo::create(['disciplina_id' => $d->id, 'nombre' => 'Concurrencia', 'cupo_maximo' => 15, 'estado' => 'activo']);
            GrupoHorario::create(['grupo_id' => $g->id, 'dia_semana' => 'lunes', 'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00']);
            for ($i = 1; $i <= 16; $i++) {
                $s = Socio::create(['nombre' => 'Prueba', 'apellido' => 'Concurrencia '.$i, 'dni' => (string) (93000000 + $i), 'sexo' => 'F', 'token' => 'parallel-'.$i, 'fecha_alta' => today(), 'estado' => 'activo', 'decision_medica' => 'pendiente']);
                if ($i <= 14) {
                    Inscripcion::create(['socio_id' => $s->id, 'grupo_id' => $g->id, 'fecha_inscripcion' => today(), 'estado' => 'aprobada', 'aprobado_at' => now()]);
                }
            }
            foreach ([15, 16] as $id) {
                $p = new Process([PHP_BINARY, base_path('tests/Support/ConcurrentEnrollmentWorker.php'), $database, $dir, (string) $id], base_path());
                $p->setTimeout(30);
                $p->start();
                $processes[] = $p;
            }
            $limit = microtime(true) + 20;
            while (! file_exists($dir.'/ready-15') || ! file_exists($dir.'/ready-16')) {
                if (microtime(true) > $limit) {
                    throw new \RuntimeException('Procesos no llegaron a la barrera');
                }usleep(10000);
            }file_put_contents($dir.'/GO', 'go');
            $results = [];
            foreach ($processes as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), $p->getErrorOutput());
                $results[] = json_decode($p->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['confirmed', 'rejected_full'], $statuses);
            $this->assertNotSame($results[0]['pid'], $results[1]['pid']);
            $this->assertTrue(max(array_column($results, 'start')) < min(array_column($results, 'end')), 'Deben solaparse las ejecuciones reales.');
            $this->assertSame(15, Inscripcion::where('estado', 'aprobada')->count());
            $this->assertSame(1, Inscripcion::whereIn('socio_id', [15, 16])->count());
        } finally {
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }DB::disconnect('sqlite');
            config(['database.connections.sqlite' => $original]);
            DB::purge('sqlite');
            foreach (glob($dir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }rmdir($dir);
        }
    }
}
