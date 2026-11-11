<?php

namespace Tests\Feature;

use App\Livewire\Socios\VerSocio;
use App\Events\GymRealtimeUpdate;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use App\Services\CertificadoMedicoService;
use App\Services\ControlAccesoService;
use App\Services\CredencialService;
use App\Services\HorarioGimnasioService;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Carbon\Carbon;
use Database\Seeders\FenixDemostracionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FenixDemostracionSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $reception;
    private Membresia $historicMembership;
    private Socio $historicMember;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'America/Argentina/Buenos_Aires',
            'fenix_demo.allow_local_mysql' => false,
            'fenix_demo.prices' => [
                'Krav Maga Kids' => 21001,
                'Kapap Krav Maga' => 22002,
                'Kickboxing Muay Thai' => 23003,
                'Funcional' => 24004,
            ],
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', config('app.timezone')));
        Event::fake();
        Storage::fake('local');

        $this->owner = User::create([
            'name' => 'Administradora Original de Prueba',
            'email' => 'owner-original@test.com',
            'password' => 'original-password-owner',
            'rol' => 'admin',
        ]);
        $this->reception = User::create([
            'name' => 'Recepcionista Original de Prueba',
            'email' => 'reception-original@test.com',
            'password' => 'original-password-reception',
            'rol' => 'recepcionista',
        ]);

        foreach (Disciplina::PLANES_FENIX as $name) {
            Disciplina::create([
                'nombre' => $name,
                'descripcion' => 'Disciplina existente de prueba.',
                'precio' => null,
                'estado' => 'activo',
            ]);
        }

        $this->historicMembership = Membresia::create([
            'nombre' => 'Membresía histórica preservada',
            'precio' => 15000,
            'duracion_dias' => 30,
            'estado' => 'activo',
        ]);
        $this->historicMember = Socio::create([
            'membresia_id' => $this->historicMembership->id,
            'apellido' => 'Histórico Preservado',
            'nombre' => 'Socio',
            'dni' => '35123456',
            'sexo' => 'M',
            'correo' => 'historico-preservado@example.test',
            'celular' => null,
            'foto' => null,
            'token' => 'historic-token-preserved',
            'qr' => null,
            'fecha_alta' => '2026-05-01',
            'fecha_vencimiento' => null,
            'estado' => 'activo',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_seeder_creates_the_eight_groups_and_exactly_sixty_six_confirmed_demo_enrollments(): void
    {
        $this->runDemoSeeder();

        $this->assertSame(4, Disciplina::query()->whereIn('nombre', Disciplina::PLANES_FENIX)->count());
        $this->assertSame(8, Grupo::query()->where('nombre', 'like', 'DEMO FENIX - Grupo %')->count());
        $this->assertSame(66, Inscripcion::query()->whereHas('grupo', fn ($query) => $query
            ->where('nombre', 'like', 'DEMO FENIX - Grupo %'))
            ->where('estado', Inscripcion::ESTADO_APROBADA)
            ->count());
        $this->assertSame(66, Socio::query()->where('correo', 'like', 'fenix-demo-%@gymcontrol.test')->count());

        $expectedCounts = [
            'Krav Maga Kids:A' => 15,
            'Krav Maga Kids:B' => 1,
            'Kapap Krav Maga:A' => 15,
            'Kapap Krav Maga:B' => 2,
            'Kickboxing Muay Thai:A' => 15,
            'Kickboxing Muay Thai:B' => 1,
            'Funcional:A' => 15,
            'Funcional:B' => 2,
        ];

        foreach ($expectedCounts as $key => $expected) {
            [$disciplineName, $letter] = explode(':', $key);
            $group = $this->demoGroup($disciplineName, $letter);
            $this->assertSame($expected, $group->inscripciones()->where('estado', Inscripcion::ESTADO_APROBADA)->count());
            $this->assertSame(15, $group->cupo_maximo);
            $this->assertLessThanOrEqual(15, $group->alumnosInscriptosCount());
            $this->assertTrue($group->estaHabilitado());
            $this->assertCount(1, $group->horarios);
        }
    }

    public function test_demo_schedules_are_opening_hour_valid_and_have_no_coach_conflicts(): void
    {
        $this->runDemoSeeder();

        $schedules = Grupo::query()
            ->where('nombre', 'like', 'DEMO FENIX - Grupo %')
            ->with('horarios')
            ->get()
            ->flatMap(fn (Grupo $group) => $group->horarios);

        $this->assertCount(8, $schedules);
        foreach ($schedules as $schedule) {
            $this->assertNull(HorarioGimnasioService::validarColeccionHorarios([[
                'dia_semana' => $schedule->dia_semana,
                'hora_inicio' => $schedule->hora_inicio,
                'hora_fin' => $schedule->hora_fin,
            ]]));
            $this->assertTrue(HorarioGimnasioService::estaDentroDelHorarioApertura(
                $schedule->hora_inicio,
                $schedule->hora_fin
            ));
        }

        for ($left = 0; $left < $schedules->count(); $left++) {
            for ($right = $left + 1; $right < $schedules->count(); $right++) {
                $first = $schedules->values()[$left];
                $second = $schedules->values()[$right];
                $this->assertFalse($first->seSuperponeCon(
                    $second->dia_semana,
                    $second->hora_inicio,
                    $second->hora_fin
                ));
            }
        }
    }

    public function test_member_profile_shows_their_group_schedule_to_reception(): void
    {
        $this->runDemoSeeder();

        Livewire::actingAs($this->reception)
            ->test(VerSocio::class, ['socio' => $this->demoMember(1)])
            ->assertSee('Grupos y horarios')
            ->assertSee('Lunes')
            ->assertSee('08:00')
            ->assertSee('09:00');
    }

    public function test_demo_cases_use_current_access_medical_and_credential_rules(): void
    {
        $this->runDemoSeeder();
        $control = app(ControlAccesoService::class);
        $credentialService = app(CredencialService::class);
        $medicalService = app(CertificadoMedicoService::class);

        foreach ([1, 16, 4] as $number) {
            $member = $this->demoMember($number);
            $this->assertTrue($member->tieneCredencialesActivas());
            $this->assertTrue($credentialService->evaluarRequisitosEmision($member)['habilitado']);
            $result = $control->evaluarAcceso(
                $member->pin,
                ControlAccesoService::METODO_PIN,
                $member->inscripciones()->firstOrFail()->grupo_id,
                $this->futureClassTime($member)
            );
            $this->assertTrue($result['autorizado'], "Demo member {$number} should be allowed.");
        }

        $approvedCertificateMember = $this->demoMember(16);
        $approvedCertificate = $approvedCertificateMember->aptosFisicos()->firstOrFail();
        $this->assertSame('vigente', $approvedCertificate->estado);
        Storage::disk('local')->assertExists($approvedCertificate->archivo);
        $this->assertStringContainsString('FICTICIO', Storage::disk('local')->get($approvedCertificate->archivo));

        $issuedMembers = Socio::query()->where('correo', 'like', 'fenix-demo-%@gymcontrol.test')
            ->where('estado_credencial', CredencialService::ESTADO_ACTIVO)
            ->get();
        foreach ($issuedMembers as $member) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $member->pin);
            $this->assertSame(64, strlen($member->qr_token));
        }
        $this->assertSame($issuedMembers->count(), $issuedMembers->pluck('pin')->unique()->count());
        $this->assertSame($issuedMembers->count(), $issuedMembers->pluck('qr_token')->unique()->count());

        foreach ([2, 17, 34] as $number) {
            $member = $this->demoMember($number);
            $this->assertSame(CredencialService::ESTADO_SIN_EMITIR, $member->estado_credencial);
            $this->assertNull($member->pin);
            $this->assertNull($member->qr_token);
            $this->assertFalse($credentialService->evaluarRequisitosEmision($member)['habilitado']);
        }
        $this->assertFalse($medicalService->evaluarRequisitoMedico($this->demoMember(2))['cumple']);
        $this->assertSame('C', $medicalService->evaluarRequisitoMedico($this->demoMember(2))['caso']);
        $expiredCertificateEvaluation = $medicalService->evaluarRequisitoMedico($this->demoMember(17));
        $this->assertFalse($expiredCertificateEvaluation['cumple']);
        $this->assertSame('E', $expiredCertificateEvaluation['caso']);
        $this->assertSame('vencido', $this->demoMember(17)->aptosFisicos()->firstOrFail()->estado);
        $this->assertSame('pendiente', $this->demoMember(34)->decision_medica);
        $this->assertSame('vigente', $this->demoMember(32)->aptosFisicos()->firstOrFail()->estado);

        foreach ([32, 49, 51] as $number) {
            $member = $this->demoMember($number);
            $this->assertTrue($member->tieneCredencialesActivas());
            $result = $control->evaluarAcceso(
                $member->pin,
                ControlAccesoService::METODO_PIN,
                $member->inscripciones()->firstOrFail()->grupo_id,
                $this->futureClassTime($member)
            );
            $this->assertSame('cuota_vencida', $result['codigo']);
        }

        $changedMedicalMember = $this->demoMember(50);
        $this->assertTrue($changedMedicalMember->tieneCredencialesActivas());
        $this->assertSame('requerido', $changedMedicalMember->decision_medica);
        $changedMedicalResult = $control->evaluarAcceso(
            $changedMedicalMember->pin,
            ControlAccesoService::METODO_PIN,
            $changedMedicalMember->inscripciones()->firstOrFail()->grupo_id,
            $this->futureClassTime($changedMedicalMember)
        );
        $this->assertFalse($changedMedicalResult['autorizado']);
        $this->assertSame('certificado_faltante', $changedMedicalResult['codigo']);

        $revokedMember = $this->demoMember(52);
        $this->assertSame(CredencialService::ESTADO_REVOCADO, $revokedMember->estado_credencial);
        $this->assertNull($revokedMember->pin);
        $this->assertNull($revokedMember->qr_token);
        $this->assertNotNull($revokedMember->pin_revocado_hash);
        $this->assertNotNull($revokedMember->qr_token_revocado_hash);

        $this->assertSame('activo', Disciplina::query()->where('nombre', 'Funcional')->value('estado'));
        $this->assertSame(24004.0, (float) Disciplina::query()->where('nombre', 'Funcional')->value('precio'));
    }

    public function test_member_cannot_use_another_discipline_or_enter_outside_their_own_schedule(): void
    {
        $this->runDemoSeeder();
        $member = $this->demoMember(19);
        $enrollment = $member->inscripciones()->firstOrFail();
        $anotherDisciplineGroup = $this->demoGroup('Kickboxing Muay Thai', 'A');

        $wrongDiscipline = app(ControlAccesoService::class)->evaluarAcceso(
            $member->pin,
            ControlAccesoService::METODO_PIN,
            $anotherDisciplineGroup->id,
            $this->futureClassTime($member)
        );
        $this->assertFalse($wrongDiscipline['autorizado']);
        $this->assertSame('inscripcion_inexistente', $wrongDiscipline['codigo']);

        $outsideHours = $this->futureClassTime($member)->setTime(12, 30);
        $wrongTime = app(ControlAccesoService::class)->evaluarAcceso(
            $member->pin,
            ControlAccesoService::METODO_PIN,
            $enrollment->grupo_id,
            $outsideHours
        );
        $this->assertFalse($wrongTime['autorizado']);
        $this->assertSame('fuera_de_horario', $wrongTime['codigo']);
    }

    public function test_full_group_accepts_existing_member_but_rejects_an_additional_enrollment(): void
    {
        $this->runDemoSeeder();
        $fullGroup = $this->demoGroup('Krav Maga Kids', 'A');
        $existingMember = $this->demoMember(4);
        $result = app(ControlAccesoService::class)->evaluarAcceso(
            $existingMember->pin,
            ControlAccesoService::METODO_PIN,
            $fullGroup->id,
            $this->futureClassTime($existingMember)
        );
        $this->assertTrue($result['autorizado']);

        $aspirant = $this->demoMember(16);
        try {
            app(InscripcionService::class)->solicitarInscripcion($aspirant, $fullGroup, $this->reception);
            $this->fail('A full group must reject a sixteenth confirmed enrollment.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('está completo', $exception->getMessage());
        }

        $this->assertSame(66, Inscripcion::query()->whereHas('grupo', fn ($query) => $query
            ->where('nombre', 'like', 'DEMO FENIX - Grupo %'))
            ->where('estado', Inscripcion::ESTADO_APROBADA)
            ->count());
        $this->assertDatabaseMissing('inscripciones', [
            'socio_id' => $aspirant->id,
            'grupo_id' => $fullGroup->id,
        ]);
    }

    public function test_reception_payment_restores_access_without_regenerating_existing_credentials(): void
    {
        $this->runDemoSeeder();
        $member = $this->demoMember(51);
        $originalPin = $member->pin;
        $originalQr = $member->qr_token;
        $enrollment = $member->inscripciones()->firstOrFail();
        $discipline = $enrollment->grupo->disciplina;
        $start = Carbon::now(config('app.timezone'))->toDateString();
        $end = Carbon::now(config('app.timezone'))->endOfMonth()->toDateString();

        app(PagoService::class)->registrarPago(
            $member,
            [[
                'inscripcion_id' => $enrollment->id,
                'concepto' => 'Renovación ficticia de prueba',
                'fecha_inicio' => $start,
                'fecha_fin' => $end,
                'subtotal' => $discipline->precio,
            ]],
            'efectivo',
            $this->reception,
            'RECEPCION-DEMO-RENOVACION',
            $start
        );

        $member->refresh();
        $this->assertSame($originalPin, $member->pin);
        $this->assertSame($originalQr, $member->qr_token);
        $result = app(ControlAccesoService::class)->evaluarAcceso(
            $member->pin,
            ControlAccesoService::METODO_PIN,
            $enrollment->grupo_id,
            $this->futureClassTime($member)
        );
        $this->assertTrue($result['autorizado']);

        $revoked = $this->demoMember(52);
        $this->assertSame(CredencialService::ESTADO_REVOCADO, $revoked->estado_credencial);
        $revokedEnrollment = $revoked->inscripciones()->firstOrFail();
        app(PagoService::class)->registrarPago(
            $revoked,
            [[
                'inscripcion_id' => $revokedEnrollment->id,
                'concepto' => 'Renovación ficticia de prueba',
                'fecha_inicio' => $start,
                'fecha_fin' => $end,
                'subtotal' => $revokedEnrollment->grupo->disciplina->precio,
            ]],
            'efectivo',
            $this->reception,
            'RECEPCION-DEMO-RENOVACION-REVOCADO',
            $start
        );
        $this->assertSame(CredencialService::ESTADO_REVOCADO, $revoked->fresh()->estado_credencial);
        $this->assertNull($revoked->fresh()->pin);
        $this->assertNull($revoked->fresh()->qr_token);
    }

    public function test_seeder_is_idempotent_and_preserves_original_accounts_and_historical_records(): void
    {
        $usersBefore = User::query()->orderBy('id')->get(['id', 'name', 'email', 'password', 'rol'])->toArray();
        $historicMemberBefore = $this->historicMember->fresh()->getAttributes();
        $historicMembershipBefore = $this->historicMembership->fresh()->getAttributes();

        $this->runDemoSeeder();
        $countsAfterFirstRun = $this->demoCounts();
        $this->runDemoSeeder();

        $this->assertSame($countsAfterFirstRun, $this->demoCounts());
        $this->assertSame($usersBefore, User::query()->orderBy('id')->get(['id', 'name', 'email', 'password', 'rol'])->toArray());
        $this->assertSame($historicMemberBefore, $this->historicMember->fresh()->getAttributes());
        $this->assertSame($historicMembershipBefore, $this->historicMembership->fresh()->getAttributes());
        $this->assertSame(2, User::query()->count());
        $this->assertSame(1, Membresia::query()->count());
        Event::assertNotDispatched(GymRealtimeUpdate::class);
    }

    public function test_mid_run_failure_rolls_back_demo_rows_and_private_certificate_files(): void
    {
        $paymentService = app(PagoService::class);
        $paymentCalls = 0;
        $mock = \Mockery::mock($paymentService)->makePartial();
        $mock->shouldReceive('registrarPago')->andReturnUsing(
            function (...$arguments) use ($paymentService, &$paymentCalls) {
                $paymentCalls++;
                if ($paymentCalls === 17) {
                    throw new \RuntimeException('Synthetic isolated test failure.');
                }

                return $paymentService->registrarPago(...$arguments);
            }
        );
        $this->app->instance(PagoService::class, $mock);

        try {
            $this->runDemoSeeder();
            $this->fail('A mid-run error must abort the complete fixture transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic isolated test failure.', $exception->getMessage());
        }

        $this->assertSame(17, $paymentCalls);
        $this->assertSame(0, Socio::query()->where('correo', 'like', 'fenix-demo-%@gymcontrol.test')->count());
        $this->assertSame(0, Grupo::query()->where('nombre', 'like', 'DEMO FENIX - Grupo %')->count());
        $this->assertSame(0, Pago::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('secure/aptos_fisicos'));
    }

    public function test_audits_do_not_contain_demo_credentials_or_medical_document_paths(): void
    {
        $this->runDemoSeeder();

        $auditPayload = json_encode(
            \App\Models\Auditoria::query()->get(['datos_antes', 'datos_despues'])->toArray(),
            JSON_THROW_ON_ERROR
        );
        $members = Socio::query()->where('correo', 'like', 'fenix-demo-%@gymcontrol.test')->get();

        foreach ($members as $member) {
            if ($member->pin) {
                $this->assertStringNotContainsString($member->pin, $auditPayload);
            }
            if ($member->qr_token) {
                $this->assertStringNotContainsString($member->qr_token, $auditPayload);
            }
        }
        $this->assertStringNotContainsString('secure/aptos_fisicos', $auditPayload);
        $this->assertStringNotContainsString('diagnóstico', mb_strtolower($auditPayload));
        $this->assertStringNotContainsString('observaciones clínicas', mb_strtolower($auditPayload));
    }

    public function test_missing_prices_stop_before_any_demo_rows_or_price_updates(): void
    {
        config(['fenix_demo.prices.Funcional' => null]);

        try {
            $this->runDemoSeeder();
            $this->fail('The demo seeder must require all four prices.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('FENIX_DEMO_PRICE_FUNCIONAL', $exception->getMessage());
        }

        $this->assertSame(0, Socio::query()->where('correo', 'like', 'fenix-demo-%@gymcontrol.test')->count());
        $this->assertSame(0, Grupo::query()->where('nombre', 'like', 'DEMO FENIX - Grupo %')->count());
        $this->assertSame(0, Pago::query()->count());
        $this->assertNull(Disciplina::query()->where('nombre', 'Funcional')->value('precio'));
    }

    public function test_alternative_connection_targets_are_rejected_before_writes(): void
    {
        foreach (['url' => 'sqlite:///unapproved.sqlite', 'read' => ['host' => 'remote.test'],
            'write' => ['host' => 'remote.test'], 'unix_socket' => '/tmp/mysql.sock'] as $key => $value) {
            config(["database.connections.sqlite.{$key}" => $value]);
            try {
                $this->runDemoSeeder();
                $this->fail('Alternative targets must be rejected before writes.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Destino rechazado', $exception->getMessage());
            } finally {
                config(["database.connections.sqlite.{$key}" => null]);
            }
        }

        $this->assertSame(0, Grupo::query()->count());
        $this->assertSame(1, Socio::query()->count());
        $this->assertSame(0, Pago::query()->count());
    }
    public function test_prices_that_round_to_zero_are_rejected_before_writes(): void
    {
        config(['fenix_demo.prices.Funcional' => '0.001']);

        try {
            $this->runDemoSeeder();
            $this->fail('A price that rounds to zero must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('FENIX_DEMO_PRICE_FUNCIONAL', $exception->getMessage());
        }

        $this->assertSame(0, Grupo::query()->count());
        $this->assertSame(1, Socio::query()->count());
        $this->assertSame(0, Pago::query()->count());
        $this->assertNull(Disciplina::query()->where('nombre', 'Funcional')->value('precio'));
    }
    public function test_seeder_rejects_production_and_mysql_testing_targets_before_writes(): void
    {
        config(['app.env' => 'production']);
        try {
            $this->runDemoSeeder();
            $this->fail('Production must reject the demo seeder.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('solo se permite en local, demo o testing', $exception->getMessage());
        }

        config([
            'app.env' => 'testing',
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => 'gymcontrol',
            'fenix_demo.allow_local_mysql' => true,
        ]);
        try {
            $this->runDemoSeeder();
            $this->fail('The isolated testing environment must never use MySQL.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Destino rechazado', $exception->getMessage());
        } finally {
            config(['database.default' => 'sqlite', 'app.env' => 'testing']);
        }

        $this->assertSame(0, Socio::query()->where('correo', 'like', 'fenix-demo-%@gymcontrol.test')->count());
    }

    private function runDemoSeeder(): void
    {
        app(FenixDemostracionSeeder::class)->run();
    }

    private function demoMember(int $number): Socio
    {
        return Socio::query()->where('correo', sprintf('fenix-demo-%03d@gymcontrol.test', $number))->firstOrFail();
    }

    private function demoGroup(string $discipline, string $letter): Grupo
    {
        return Grupo::query()
            ->where('nombre', "DEMO FENIX - Grupo {$letter}")
            ->whereHas('disciplina', fn ($query) => $query->where('nombre', $discipline))
            ->with(['disciplina', 'horarios'])
            ->firstOrFail();
    }

    private function futureClassTime(Socio $member): Carbon
    {
        $schedule = $member->inscripciones()->firstOrFail()->grupo->horarios()->firstOrFail();
        $weekdays = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
        $targetDay = array_search($schedule->dia_semana, $weekdays, true) + 1;
        $today = Carbon::now(config('app.timezone'));
        $daysUntil = ($targetDay - (int) $today->format('N') + 7) % 7;
        $daysUntil = $daysUntil === 0 ? 7 : $daysUntil;

        return $today->copy()
            ->startOfDay()
            ->addDays($daysUntil)
            ->setTimeFromTimeString($schedule->hora_inicio)
            ->addMinutes(15);
    }

    /**
     * @return array{members: int, groups: int, enrollments: int, payments: int, certificates: int}
     */
    private function demoCounts(): array
    {
        return [
            'members' => Socio::query()->where('correo', 'like', 'fenix-demo-%@gymcontrol.test')->count(),
            'groups' => Grupo::query()->where('nombre', 'like', 'DEMO FENIX - Grupo %')->count(),
            'enrollments' => Inscripcion::query()->whereHas('grupo', fn ($query) => $query
                ->where('nombre', 'like', 'DEMO FENIX - Grupo %'))
                ->where('estado', Inscripcion::ESTADO_APROBADA)
                ->count(),
            'payments' => Pago::query()->where('referencia', 'like', 'FENIX-DEMO-%')->count(),
            'certificates' => \App\Models\AptoFisico::query()->whereHas('socio', fn ($query) => $query
                ->where('correo', 'like', 'fenix-demo-%@gymcontrol.test'))->count(),
        ];
    }
}
