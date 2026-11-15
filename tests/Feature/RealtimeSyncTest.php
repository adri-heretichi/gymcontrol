<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Livewire\Operadores\EditarOperador;
use App\Livewire\Operadores\ListarOperadores;
use App\Livewire\Socios\CrearSocio;
use App\Livewire\Socios\ListarSocios;
use App\Livewire\Socios\VerSocio;
use App\Livewire\TerminalAcceso;
use App\Models\AptoFisico;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\RealtimeChanges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RealtimeSyncTest extends TestCase
{
    private User $owner;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Estas pruebas requieren SQLite en memoria.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Http::fake();
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->reception = User::factory()->create(['rol' => 'recepcionista']);
        Event::fake([GymStateChanged::class]);
        app(RealtimeChanges::class)->flush();
        Event::fake([GymStateChanged::class]);
    }

    private function member(array $attributes = []): Socio
    {
        return Socio::create(array_merge(['nombre' => 'Prueba', 'apellido' => 'Sincronización', 'dni' => '90000001', 'sexo' => 'F', 'token' => 'test-token', 'fecha_alta' => today(), 'estado' => 'activo', 'estado_credencial' => 'sin_emitir', 'decision_medica' => 'pendiente'], $attributes));
    }

    private function drainSetup(): void
    {
        app(RealtimeChanges::class)->flush();
        Event::fake([GymStateChanged::class]);
    }

    private function channel(GymStateChanged $event): string
    {
        return $event->broadcastOn()[0]->name;
    }

    public function test_one_batch_per_audience_groups_all_changes_in_a_committed_operation(): void
    {
        DB::transaction(function () {
            $member = $this->member();
            $member->update(['apellido' => 'Apellido confirmado']);
            Auditoria::registrar('administrativo', 'alta_socio', $member);
            app(RealtimeChanges::class)->flush();
            Event::assertNotDispatched(GymStateChanged::class);
        });
        app(RealtimeChanges::class)->flush();
        Event::assertDispatchedTimes(GymStateChanged::class, 3);
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.admin' && in_array('audit', $e->resources));
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.reception' && in_array('members', $e->resources) && ! in_array('medical', $e->resources));
        app(RealtimeChanges::class)->flush();
        Event::assertDispatchedTimes(GymStateChanged::class, 3);
    }

    public function test_rollback_does_not_publish_changes(): void
    {
        DB::beginTransaction();
        $this->member();
        DB::rollBack();
        app(RealtimeChanges::class)->flush();
        Event::assertNotDispatched(GymStateChanged::class);
        $this->assertDatabaseCount('socios', 0);
    }

    public function test_nested_rollback_does_not_invalidate_rolled_back_groups(): void
    {
        DB::beginTransaction();
        $this->member();
        DB::beginTransaction();
        Disciplina::create(['nombre' => 'Grupo revertido', 'precio' => 10, 'estado' => 'activo']);
        DB::rollBack();
        DB::commit();
        app(RealtimeChanges::class)->flush();
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.admin' && in_array('members', $e->resources) && ! in_array('groups', $e->resources));
    }

    public function test_medical_update_contains_no_documents_credentials_or_private_details(): void
    {
        $member = $this->member();
        $this->drainSetup();
        $member->update(['decision_medica' => 'requerido', 'decision_medica_obs' => 'texto médico reservado']);
        AptoFisico::create(['socio_id' => $member->id, 'archivo' => 'secure/privado.pdf', 'fecha_emision' => today(), 'fecha_vencimiento' => today()->addYear(), 'estado' => 'vigente']);
        app(RealtimeChanges::class)->flush();
        Event::assertDispatched(GymStateChanged::class, function ($event) {
            $payload = $event->broadcastWith();
            $this->assertSame(['change_id', 'resources'], array_keys($payload));
            $this->assertStringNotContainsString('reservado', json_encode($payload));
            $this->assertStringNotContainsString('privado.pdf', json_encode($payload));

            return $this->channel($event) === 'private-gym.admin' && in_array('medical', $event->resources);
        });
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.reception' && ! in_array('medical', $e->resources));
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'gym.terminal' && $e->resources === ['availability']);
    }

    public function test_requests_only_notify_the_owner_and_requesting_receptionist(): void
    {
        $member = $this->member();
        $this->drainSetup();
        Solicitud::create(['socio_id' => $member->id, 'solicitante_id' => $this->reception->id, 'tipo' => Solicitud::TIPO_EXCEPCION_MEDICA, 'descripcion' => 'Contenido privado de solicitud', 'estado' => 'pendiente']);
        app(RealtimeChanges::class)->flush();
        Event::assertDispatchedTimes(GymStateChanged::class, 2);
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.reception.'.$this->reception->id && $e->resources === ['requests']);
        Event::assertNotDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.reception');
    }

    public function test_denied_attempt_notifies_staff_even_without_attendance(): void
    {
        $member = $this->member();
        $this->drainSetup();
        Auditoria::registrar('acceso', 'acceso_denegado', $member, datosDespues: ['resultado' => 'fuera_de_horario', 'metodo' => 'pin']);
        app(RealtimeChanges::class)->flush();
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.reception' && $e->resources === ['access']);
        $this->assertDatabaseCount('asistencias', 0);
        $this->actingAs($this->reception)->get('/dashboard')->assertSee('Últimos eventos de acceso')->assertSee('Denegado')->assertSee('Prueba Sincronización');
    }

    public function test_group_schedule_writes_are_combined_without_publishing_schedule_data(): void
    {
        DB::transaction(function () {
            $discipline = Disciplina::create(['nombre' => 'Funcional', 'precio' => 35000, 'estado' => 'activo']);
            $group = Grupo::create(['disciplina_id' => $discipline->id, 'nombre' => 'Grupo privado de prueba', 'estado' => 'activo', 'cupo_maximo' => 15]);
            foreach (['lunes', 'martes', 'miercoles'] as $day) {
                GrupoHorario::create(['grupo_id' => $group->id, 'dia_semana' => $day, 'hora_inicio' => '15:00', 'hora_fin' => '16:00']);
            }
        });
        app(RealtimeChanges::class)->flush();
        Event::assertDispatchedTimes(GymStateChanged::class, 3);
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.reception' && $e->resources === ['groups']);
    }

    public function test_operator_updates_are_private_and_remember_token_does_not_broadcast(): void
    {
        $this->owner->update(['remember_token' => 'remember-test']);
        app(RealtimeChanges::class)->flush();
        Event::assertNotDispatched(GymStateChanged::class);
        $this->owner->update(['name' => 'Nombre actualizado']);
        app(RealtimeChanges::class)->flush();
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.admin' && in_array('operators', $e->resources));
        Event::assertDispatched(GymStateChanged::class, fn ($e) => $this->channel($e) === 'private-gym.reception' && $e->resources === ['permissions']);
    }

    public function test_member_form_preserves_unsaved_fields_when_availability_changes(): void
    {
        $this->actingAs($this->reception);
        Livewire::test(CrearSocio::class)->set('nombre', 'Nombre en edición')->set('dni', '95555555')
            ->call('syncGymState', ['groups'])->assertSet('nombre', 'Nombre en edición')->assertSet('dni', '95555555');
        $this->assertDatabaseCount('socios', 0);
    }

    public function test_member_detail_refreshes_cached_state_without_overwriting_a_medical_draft(): void
    {
        $member = $this->member();
        $this->actingAs($this->owner);
        $component = Livewire::test(VerSocio::class, ['socio' => $member])->set('decision_medica_seleccionada', 'no_requerido')->set('decision_medica_observacion', 'Borrador local');
        $member->update(['nombre' => 'Nombre desde otra sesión', 'decision_medica' => 'requerido']);
        $component->call('syncGymState', ['members'])->assertSee('Nombre desde otra sesión')->assertSet('decision_medica_seleccionada', 'no_requerido')->assertSet('decision_medica_observacion', 'Borrador local');
    }

    public function test_list_refresh_preserves_search_and_reads_new_data(): void
    {
        $this->actingAs($this->reception);
        $component = Livewire::test(ListarSocios::class)->set('buscar', 'Sincronización');
        $this->member();
        $component->call('syncGymState', ['members'])->assertSet('buscar', 'Sincronización')->assertSee('Prueba');
    }

    public function test_role_is_revalidated_before_refreshing_owner_only_screens(): void
    {
        $this->actingAs($this->owner);
        $component = Livewire::test(ListarOperadores::class);
        User::whereKey($this->owner->id)->update(['rol' => 'recepcionista']);
        $component->call('syncGymState', ['permissions'])->assertForbidden();
    }

    public function test_public_terminal_refresh_never_registers_an_access(): void
    {
        Livewire::test(TerminalAcceso::class)->set('pin', '123456')->call('syncGymState', ['availability'])->assertSet('pin', '123456')->assertSet('datosOperativosActualizados', true);
        $this->assertDatabaseCount('asistencias', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_unrelated_change_does_not_reset_or_refresh_a_member_form(): void
    {
        $this->actingAs($this->reception);
        Livewire::test(CrearSocio::class)->set('nombre', 'Borrador')->call('syncGymState', ['audit'])->assertSet('nombre', 'Borrador');
    }

    public function test_invalid_resource_names_cannot_be_used_as_component_actions(): void
    {
        $this->actingAs($this->reception);
        Livewire::test(ListarSocios::class)->call('syncGymState', ['password'])->assertStatus(422);
    }

    public function test_operator_edit_is_audited_without_passwords_and_keeps_original_role(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(EditarOperador::class, ['user' => $this->reception])
            ->set('name', 'Nombre actualizado')->call('actualizar');
        $this->assertSame('recepcionista', $this->reception->fresh()->rol);
        $this->assertSame('Nombre actualizado', $this->reception->fresh()->name);
        $audit = Auditoria::where('accion', 'operador_actualizado')->sole();
        $this->assertArrayNotHasKey('password', $audit->datos_despues);
        $this->assertArrayNotHasKey('remember_token', $audit->datos_despues);
    }

    public function test_member_deactivation_is_audited_and_broadcasts_once_per_audience(): void
    {
        $member = $this->member();
        $this->drainSetup();
        $this->actingAs($this->owner);
        Livewire::test(ListarSocios::class)->call('alternarEstado', $member->id);
        $this->assertSame('inactivo', $member->fresh()->estado);
        $this->assertSame(1, Auditoria::where('accion', 'estado_socio_modificado')->count());
        app(RealtimeChanges::class)->flush();
        Event::assertDispatchedTimes(GymStateChanged::class, 3);
    }

    public function test_initial_terminal_catchup_does_not_claim_an_access_or_a_change(): void
    {
        Livewire::test(TerminalAcceso::class)->call('syncGymState', ['*'])->assertSet('datosOperativosActualizados', false);
        $this->assertDatabaseCount('asistencias', 0);
    }
}
