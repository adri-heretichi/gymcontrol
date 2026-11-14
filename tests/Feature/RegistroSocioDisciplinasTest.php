<?php

namespace Tests\Feature;

use App\Livewire\Inscripciones\CrearInscripcion;
use App\Livewire\Pagos\CrearPago;
use App\Livewire\Socios\CrearSocio;
use App\Livewire\Socios\VerSocio;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use App\Services\CertificadoMedicoService;
use App\Services\ControlAccesoService;
use App\Services\CredencialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistroSocioDisciplinasTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $reception;
    private Grupo $kids;
    private Grupo $functional;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', config('app.timezone')));
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->reception = User::factory()->create(['rol' => 'recepcionista']);
        $this->kids = $this->group('Krav Maga Kids', 30000, 'lunes', '09:00:00', '10:00:00');
        $this->functional = $this->group('Funcional', 35000, 'jueves', '09:00:00', '10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function group(string $name, int $price, string $day, string $start, string $end): Grupo
    {
        $discipline = Disciplina::create(['nombre' => $name, 'precio' => $price, 'estado' => 'activo']);
        $group = Grupo::create(['disciplina_id' => $discipline->id, 'nombre' => $name . ' B', 'cupo_maximo' => 15, 'estado' => 'activo']);
        GrupoHorario::create(['grupo_id' => $group->id, 'dia_semana' => $day, 'hora_inicio' => $start, 'hora_fin' => $end]);

        return $group;
    }

    private function selection(Grupo $group, bool $paid = false, string $end = '2026-11-30'): array
    {
        return ['disciplina_id' => (string) $group->disciplina_id, 'grupo_id' => (string) $group->id,
            'confirmar_cobro' => $paid, 'fecha_inicio' => '2026-11-13', 'fecha_fin' => $end];
    }

    private function form(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->reception)->test(CrearSocio::class)
            ->set('nombre', 'Participante')->set('apellido', 'Prueba')->set('dni', '45000123')
            ->set('sexo', 'F')->set('correo', 'registro-multiple@example.test');
    }

    public static function roles(): array
    {
        return [['admin'], ['recepcionista']];
    }

    #[DataProvider('roles')]
    public function test_real_form_creates_one_member_two_enrollments_and_two_separate_confirmed_payments(string $role): void
    {
        $user = $role === 'admin' ? $this->owner : $this->reception;
        $this->actingAs($user)->get(route('socios.create'))->assertOk()
            ->assertSeeLivewire(CrearSocio::class)->assertSee('Agregar otra disciplina');
        $form = $this->form($user)->call('agregarInscripcion')
            ->set('inscripciones.0.disciplina_id', (string) $this->kids->disciplina_id)
            ->set('inscripciones.0.grupo_id', (string) $this->kids->id)
            ->set('inscripciones.0.confirmar_cobro', true)
            ->set('inscripciones.0.fecha_inicio', '2026-11-13')
            ->set('inscripciones.0.fecha_fin', '2026-11-30')
            ->set('inscripciones.1.disciplina_id', (string) $this->functional->disciplina_id)
            ->set('inscripciones.1.grupo_id', (string) $this->functional->id)
            ->set('inscripciones.1.confirmar_cobro', true)
            ->set('inscripciones.1.fecha_inicio', '2026-11-13')
            ->set('inscripciones.1.fecha_fin', '2026-12-12')
            ->assertSee('$30.000,00')->assertSee('$35.000,00')
            ->call('guardar')->assertHasNoErrors();
        $member = Socio::sole();
        $form->assertRedirect(route('socios.show', $member->id));
        $this->assertSame(2, $member->inscripciones()->where('estado', Inscripcion::ESTADO_APROBADA)->count());
        $this->assertSame(2, Pago::count());
        $this->assertSame(2, PagoDetalle::count());
        $this->assertSame(['30000.00', '35000.00'], Pago::orderBy('id')->pluck('importe')->all());
        foreach (Pago::with('detalles')->get() as $payment) {
            $this->assertCount(1, $payment->detalles);
            $this->assertSame($member->id, $payment->socio_id);
        }
        $this->assertSame(['2026-11-30', '2026-12-12'], PagoDetalle::orderBy('id')->get()->map(fn ($row) => $row->fecha_fin_cobertura->toDateString())->all());
        $this->assertSame('pendiente', $member->decision_medica);
        $this->assertSame('sin_emitir', $member->estado_credencial);
        $this->assertNull($member->pin);
        $this->assertNull($member->qr_token);
        $this->assertNull($member->fecha_vencimiento);
        $this->assertNull($member->membresia_id);
        $this->assertDatabaseCount('membresias', 0);
    }

    public function test_unconfirmed_receipts_remain_unpaid_and_cannot_issue_credentials(): void
    {
        $this->form($this->owner)->set('decision_medica', 'no_requerido')
            ->set('inscripciones', [$this->selection($this->kids), $this->selection($this->functional)])
            ->call('guardar')->assertHasNoErrors();
        $member = Socio::sole();
        $this->assertDatabaseCount('pagos', 0);
        $this->assertSame(2, $member->inscripciones()->count());
        $this->assertSame('sin_emitir', $member->estado_credencial);
        $this->assertFalse(app(CredencialService::class)->evaluarRequisitosEmision($member)['habilitado']);
    }

    public function test_one_paid_discipline_enables_only_its_access_and_a_later_payment_preserves_shared_credentials(): void
    {
        $this->form($this->owner)->set('decision_medica', 'no_requerido')
            ->set('inscripciones', [$this->selection($this->kids, true), $this->selection($this->functional)])
            ->call('guardar')->assertHasNoErrors();
        $member = Socio::sole();
        $this->assertSame('activo', $member->estado_credencial);
        $control = app(ControlAccesoService::class);
        $allowed = $control->evaluarAcceso($member->pin, ControlAccesoService::METODO_PIN, $this->kids->id, Carbon::parse('2026-11-16 09:15:00', config('app.timezone')));
        $denied = $control->evaluarAcceso($member->qr_token, ControlAccesoService::METODO_QR, $this->functional->id, Carbon::parse('2026-11-19 09:15:00', config('app.timezone')));
        $this->assertTrue($allowed['autorizado']);
        $this->assertFalse($denied['autorizado']);
        $this->assertSame('sin_cobertura', $denied['codigo']);
        $credentialDigest = hash('sha256', $member->pin . '|' . $member->qr_token);
        $enrollment = $member->inscripciones()->where('grupo_id', $this->functional->id)->sole();
        Livewire::actingAs($this->reception)->test(CrearPago::class, ['socio' => $member])
            ->set('detalles.0.inscripcion_id', $enrollment->id)->assertSet('detalles.0.subtotal', '35000.00')
            ->call('guardar')->assertHasNoErrors()->assertRedirect(route('pagos.index'));
        $member->refresh();
        $this->assertSame($credentialDigest, hash('sha256', $member->pin . '|' . $member->qr_token));
        $this->assertSame(1, Auditoria::where('accion', 'credencial_emitida')->count());
        $this->assertSame(2, Pago::count());
        $this->assertTrue($control->evaluarAcceso($member->qr_token, ControlAccesoService::METODO_QR, $this->functional->id, Carbon::parse('2026-11-19 09:15:00', config('app.timezone')))['autorizado']);
    }

    public function test_required_certificate_keeps_access_blocked_after_payment_until_owner_approval(): void
    {
        $this->form($this->owner)->set('decision_medica', 'requerido')
            ->set('inscripciones', [$this->selection($this->kids, true)])
            ->call('guardar')->assertHasNoErrors();
        $member = Socio::sole();
        $this->assertSame('sin_emitir', $member->estado_credencial);
        $this->assertFalse(app(CredencialService::class)->evaluarRequisitosEmision($member)['habilitado']);
        app(CertificadoMedicoService::class)->registrarCertificado($member, '2026-11-01', '2027-11-01', $this->owner,
            UploadedFile::fake()->create('certificado-ficticio.pdf', 1, 'application/pdf'));
        Livewire::actingAs($this->owner)->test(VerSocio::class, ['socio' => $member])
            ->call('emitirCredenciales')->assertHasNoErrors();
        $this->assertSame('activo', $member->fresh()->estado_credencial);
    }

    public function test_reception_cannot_choose_medical_exemption_by_manipulating_the_form(): void
    {
        $this->form()->set('decision_medica', 'no_requerido')->set('inscripciones', [$this->selection($this->kids, true)])
            ->call('guardar')->assertHasErrors('decision_medica');
        $this->assertDatabaseCount('socios', 0);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_overlapping_choices_roll_back_the_member_enrollments_receipts_and_audits(): void
    {
        $overlap = $this->group('Otra disciplina', 10000, 'lunes', '09:30:00', '10:30:00');
        $this->form()->set('inscripciones', [$this->selection($this->kids, true), $this->selection($overlap, true)])
            ->call('guardar')->assertHasErrors('inscripciones.1.grupo_id');
        foreach (['socios', 'inscripciones', 'pagos', 'pago_detalles', 'auditorias'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_duplicate_groups_are_rejected_before_creating_personal_or_financial_records(): void
    {
        $this->form()->set('inscripciones', [$this->selection($this->kids, true), $this->selection($this->kids, true)])
            ->call('guardar')->assertHasErrors('inscripciones.1.grupo_id');
        $this->assertDatabaseCount('socios', 0);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_mismatched_discipline_and_group_are_rejected(): void
    {
        $row = $this->selection($this->kids, true);
        $row['grupo_id'] = (string) $this->functional->id;
        $this->form()->set('inscripciones', [$row])->call('guardar')->assertHasErrors('inscripciones.0.grupo_id');
        $this->assertDatabaseCount('socios', 0);
    }

    public function test_group_limit_is_fifteen_even_if_legacy_capacity_is_larger(): void
    {
        $this->functional->update(['cupo_maximo' => 30]);
        for ($seat = 0; $seat < 15; $seat++) {
            $member = Socio::create(['nombre' => 'Ficticio', 'apellido' => 'Ocupante', 'dni' => (string) (46000000 + $seat),
                'sexo' => 'F', 'token' => 'test-seat-' . $seat, 'fecha_alta' => today()->toDateString(), 'estado' => 'activo']);
            Inscripcion::create(['socio_id' => $member->id, 'grupo_id' => $this->functional->id,
                'fecha_inscripcion' => today()->toDateString(), 'estado' => Inscripcion::ESTADO_APROBADA]);
        }
        $this->form()->set('inscripciones', [$this->selection($this->kids, true), $this->selection($this->functional, true)])
            ->call('guardar')->assertHasErrors('inscripciones.1.grupo_id');
        $this->assertDatabaseCount('socios', 15);
        $this->assertDatabaseCount('inscripciones', 15);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseMissing('socios', ['dni' => '45000123']);
    }

    public function test_invalid_coverage_dates_do_not_create_any_record(): void
    {
        $this->form()->set('inscripciones', [$this->selection($this->kids, true, '2026-11-12')])
            ->call('guardar')->assertHasErrors('inscripciones.0.fecha_fin');
        $this->assertDatabaseCount('socios', 0);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_repeating_submit_does_not_duplicate_the_member_or_its_payments(): void
    {
        $form = $this->form()->set('inscripciones', [$this->selection($this->kids, true)])
            ->call('guardar')->assertHasNoErrors();
        $form->call('guardar')->assertHasErrors('dni');
        $this->assertDatabaseCount('socios', 1);
        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_existing_profile_can_add_another_discipline_without_changing_personal_data_or_credentials(): void
    {
        $this->form()->set('inscripciones', [$this->selection($this->kids)])->call('guardar')->assertHasNoErrors();
        $member = Socio::sole();
        $before = $member->getAttributes();
        $this->actingAs($this->reception)->get(route('socios.show', $member))->assertOk()
            ->assertSee('Agregar disciplina o grupo')->assertSee(route('inscripciones.create', ['socio_id' => $member->id]), false);
        $component = Livewire::withQueryParams(['socio_id' => $member->id])->test(CrearInscripcion::class)
            ->assertSet('socio_id', $member->id)->set('disciplina_id', $this->functional->disciplina_id)
            ->set('grupo_id', $this->functional->id)->call('guardar')->assertHasNoErrors();
        $this->assertDatabaseCount('socios', 1);
        $this->assertDatabaseCount('inscripciones', 2);
        $this->assertSame($before, $member->fresh()->getAttributes());
        $component->call('guardar')->assertHasErrors('grupo_id');
        $this->assertDatabaseCount('inscripciones', 2);
    }

    public function test_preliminary_registration_without_enrollment_is_preserved_and_tokens_do_not_collide_with_legacy_values(): void
    {
        Socio::create(['nombre' => 'Histórico', 'apellido' => 'Ficticio', 'dni' => '47000001', 'sexo' => 'F',
            'token' => '0001', 'fecha_alta' => today()->toDateString(), 'estado' => 'activo']);
        $this->form()->call('guardar')->assertHasNoErrors();
        $this->assertDatabaseCount('socios', 2);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_changing_a_discipline_clears_its_previous_group_and_both_roles_can_open_registration(): void
    {
        $this->form()->set('inscripciones.0.disciplina_id', (string) $this->kids->disciplina_id)
            ->set('inscripciones.0.grupo_id', (string) $this->kids->id)
            ->set('inscripciones.0.confirmar_cobro', true)
            ->set('inscripciones.0.disciplina_id', (string) $this->functional->disciplina_id)
            ->assertSet('inscripciones.0.grupo_id', '')
            ->assertSet('inscripciones.0.confirmar_cobro', false);
        foreach ([$this->owner, $this->reception] as $user) {
            $this->actingAs($user)->get(route('socios.index'))->assertOk()->assertSee('Registrar Socio');
        }
    }
}
