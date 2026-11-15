<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Livewire\AptosFisicos\CrearAptoFisico;
use App\Livewire\AptosFisicos\EditarAptoFisico;
use App\Livewire\Pagos\CrearPago;
use App\Livewire\Socios\CrearSocio;
use App\Livewire\Socios\VerSocio;
use App\Livewire\TerminalAcceso;
use App\Models\AptoFisico;
use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Socio;
use App\Models\User;
use App\Services\ControlAccesoService;
use App\Services\CredencialService;
use App\Services\PagoService;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GymLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function medicalCases(): array
    {
        return ['certificado requerido' => [true], 'certificado no requerido' => [false]];
    }

    #[DataProvider('medicalCases')]
    public function test_complete_lifecycle_keeps_discipline_payments_medical_privacy_and_exit_rules(bool $requiresCertificate): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Event::fake([GymStateChanged::class]);
        Http::fake();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:15:00', config('app.timezone')));
        $this->seed(UserSeeder::class);
        $owner = User::where('rol', 'admin')->sole();
        $reception = User::where('rol', 'recepcionista')->sole();
        $groups = [];
        foreach ([['Funcional', '09:00:00', '10:00:00'], ['Kickboxing Muay Thai', '10:00:00', '11:00:00']] as [$name,$start,$end]) {
            $d = Disciplina::create(['nombre' => $name, 'precio' => 35000, 'estado' => 'activo']);
            $g = Grupo::create(['disciplina_id' => $d->id, 'nombre' => $name.' prueba integral', 'cupo_maximo' => 15, 'estado' => 'activo']);
            GrupoHorario::create(['grupo_id' => $g->id, 'dia_semana' => 'lunes', 'hora_inicio' => $start, 'hora_fin' => $end]);
            $groups[] = $g;
        }
        $selections = array_map(fn ($g) => ['disciplina_id' => (string) $g->disciplina_id, 'grupo_id' => (string) $g->id, 'confirmar_cobro' => false, 'fecha_inicio' => '2026-11-16', 'fecha_fin' => '2026-11-30'], $groups);
        Livewire::actingAs($reception)->test(CrearSocio::class)->set('nombre', 'Participante')->set('apellido', 'Recorrido ficticio')->set('dni', '92999999')->set('sexo', 'F')->set('inscripciones', $selections)->call('guardar')->assertHasNoErrors();
        $member = Socio::sole();
        $this->assertSame(2, Inscripcion::where('socio_id', $member->id)->count());
        $this->assertSame(0, Pago::count());
        $enrollments = Inscripcion::orderBy('grupo_id')->get();
        $pay = function ($enrollment) use ($reception, $member) {
            Livewire::actingAs($reception)->test(CrearPago::class, ['socio' => $member])->set('detalles', [['inscripcion_id' => (string) $enrollment->id, 'concepto' => 'Cuota ficticia', 'fecha_inicio' => '2026-11-16', 'fecha_fin' => '2026-11-30', 'subtotal' => '35000']])->call('guardar')->assertHasNoErrors();

            return Pago::latest('id')->firstOrFail();
        };
        $pay($enrollments[0]);
        $this->assertTrue(app(PagoService::class)->consultarCoberturaInscripcion($enrollments[0])['vigente']);
        $this->assertFalse(app(PagoService::class)->consultarCoberturaInscripcion($enrollments[1])['vigente']);
        Livewire::actingAs($owner)->test(VerSocio::class, ['socio' => $member])->set('decision_medica_seleccionada', $requiresCertificate ? 'requerido' : 'no_requerido')->call('actualizarDecisionMedica')->assertHasNoErrors();
        if ($requiresCertificate) {
            $this->assertFalse(app(CredencialService::class)->evaluarRequisitosEmision($member->fresh())['habilitado']);
            Livewire::actingAs($owner)->test(CrearAptoFisico::class, ['socio' => $member])->set('archivo_cargado', UploadedFile::fake()->createWithContent('ficticio.pdf', "%PDF-1.4\nDocumento ficticio sin datos reales\n%%EOF"))->call('guardar')->assertHasNoErrors();
            $certificate = AptoFisico::sole();
            Storage::disk('local')->assertExists($certificate->archivo);
            $this->actingAs($reception)->get(route('aptos-fisicos.download', $certificate))->assertForbidden();
            $this->assertSame('pendiente_revision', $certificate->estado);
            $this->assertFalse(app(CredencialService::class)->evaluarRequisitosEmision($member->fresh())['habilitado']);
            Livewire::actingAs($owner)->test(EditarAptoFisico::class, ['aptoFisico' => $certificate->fresh()])->set('estado', 'vigente')->call('actualizar')->assertHasNoErrors();
        }
        Livewire::actingAs($owner)->test(VerSocio::class, ['socio' => $member->fresh()])->call('emitirCredenciales')->assertHasNoErrors();
        $member->refresh();
        $this->assertSame('activo', $member->estado_credencial);
        $pin = $member->pin;
        $qr = $member->qr_token;
        $control = app(ControlAccesoService::class);
        $this->assertTrue($control->evaluarAcceso($qr, 'qr', $groups[0]->id)['autorizado']);
        $this->assertFalse($control->evaluarAcceso($pin, 'pin', $groups[1]->id, Carbon::parse('2026-11-16 10:15:00', config('app.timezone')))['autorizado']);
        auth()->logout();
        Livewire::test(TerminalAcceso::class)->set('pin', $pin)->call('procesar')->assertSet('estadoAcceso', 'exito');
        $this->assertSame(1, Asistencia::count());
        $this->assertFalse($control->registrarIngreso($pin, 'pin', $groups[0]->id)['autorizado']);
        $this->assertSame(1, Asistencia::count());
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:40:00', config('app.timezone')));
        $this->assertTrue($control->registrarSalidaPorCredencial($pin, 'pin')['autorizado']);
        $this->assertSame(0, Asistencia::whereNull('hora_salida')->count());
        $kickPayment = $pay($enrollments[1]);
        $this->assertSame(2, Pago::count());
        $member->refresh();
        $this->assertTrue(hash_equals($pin, $member->pin) && hash_equals($qr, $member->qr_token), 'Los pagos conservan la credencial compartida.');
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:15:00', config('app.timezone')));
        $this->post(route('terminal.scan.ingreso'), ['identificador' => $qr, 'grupo_id' => $groups[1]->id])->assertOk()->assertSee('Ingreso autorizado');
        $this->assertSame(2, Asistencia::count());
        app(PagoService::class)->anularPago($kickPayment, $owner, 'Comprobante ficticio anulado después de ingreso.');
        if ($requiresCertificate) {
            Livewire::actingAs($owner)->test(EditarAptoFisico::class, ['aptoFisico' => AptoFisico::sole()])->set('estado', 'rechazado')->call('actualizar')->assertHasNoErrors();
        } else {
            Livewire::actingAs($owner)->test(VerSocio::class, ['socio' => $member])->set('decision_medica_seleccionada', 'requerido')->call('actualizarDecisionMedica')->assertHasNoErrors();
        }
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:40:00', config('app.timezone')));
        $this->post(route('terminal.scan.salida'), ['identificador' => $qr])->assertOk()->assertSee('Salida registrada');
        $this->assertSame(0, Asistencia::whereNull('hora_salida')->count());
        $this->post(route('terminal.scan.ingreso'), ['identificador' => $qr, 'grupo_id' => $groups[1]->id])->assertForbidden();
        $this->assertSame(2,Asistencia::count());
        $this->assertGreaterThan(0,Auditoria::where('tipo_evento','acceso')->count());
    }
}
