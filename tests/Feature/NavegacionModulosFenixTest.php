<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Solicitudes\GestionarSolicitudes;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Solicitud;
use App\Models\Socio;
use App\Models\User;
use App\Services\InscripcionService;
use DOMDocument;
use DOMXPath;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NavegacionModulosFenixTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->reception = User::factory()->create(['rol' => 'recepcionista']);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET);

        return new DOMXPath($document);
    }

    private function moduleLabels(DOMXPath $xpath, string $menu): array
    {
        $links = $xpath->query('//*[@data-navigation-menu="' . $menu . '"]//a[@data-module]');

        return array_map(fn ($link) => trim(preg_replace('/\s+/u', ' ', $link->textContent)), iterator_to_array($links));
    }

    public function test_owner_has_exactly_the_eight_modules_in_order_on_desktop_and_mobile(): void
    {
        $this->actingAs($this->owner);
        $xpath = $this->xpath(Volt::test('layout.navigation')->html());
        $expected = ['Panel Administrativo', 'Socios', 'Membresías', 'Aptos Físicos',
            'Pagos', 'Control de Acceso', 'Operadores', 'Auditoría'];
        foreach (['desktop', 'mobile'] as $menu) {
            $this->assertSame($expected, $this->moduleLabels($xpath, $menu));
            $this->assertSame(0, $xpath->query('//*[@data-navigation-menu="' . $menu . '"]//a[@href="' . route('solicitudes.index') . '"]')->length);
        }
    }

    public function test_reception_sees_only_authorized_modules_and_cannot_open_owner_modules(): void
    {
        $this->actingAs($this->reception);
        $xpath = $this->xpath(Volt::test('layout.navigation')->html());
        foreach (['desktop', 'mobile'] as $menu) {
            $this->assertSame(['Panel Administrativo', 'Socios', 'Membresías', 'Pagos', 'Control de Acceso'], $this->moduleLabels($xpath, $menu));
        }
        foreach (['operadores.index', 'auditoria.index', 'aptos-fisicos.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_existing_secondary_routes_are_preserved_under_their_parent_module(): void
    {
        $this->actingAs($this->owner);
        foreach ([
            ['disciplinas.index', 'Membresías', 'membresias.index'],
            ['grupos.index', 'Membresías', 'membresias.index'],
            ['inscripciones.index', 'Socios', 'socios.index'],
            ['asistencias.index', 'Control de Acceso', 'control-acceso'],
        ] as [$route, $module, $parent]) {
            $response = $this->get(route($route))->assertOk()->assertSee('Secciones de ' . $module);
            $xpath = $this->xpath($response->getContent());
            $active = $xpath->query('//*[@data-navigation-menu="desktop"]//a[@data-module="' . $parent . '"]')->item(0);
            $this->assertNotNull($active);
            $this->assertStringContainsString('border-orange-600', $active->getAttribute('class'));
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Secciones de ' . $module . '"]//a[@aria-current="page"]')->length);
        }
    }

    public function test_dashboard_embeds_the_existing_request_component_for_both_roles(): void
    {
        foreach ([$this->owner, $this->reception] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSeeLivewire(GestionarSolicitudes::class)
                ->assertSee('Solicitudes pendientes e historial')
                ->assertSee('id="solicitudes"', false);
            // Existing bookmarks keep their original route and permissions.
            $this->get(route('solicitudes.index'))->assertOk()->assertSee('Panel Administrativo · Solicitudes');
        }
    }

    private function member(string $name = 'Socio'): Socio
    {
        static $number = 41000000;
        $number++;

        return Socio::create(['nombre' => $name, 'apellido' => 'Prueba', 'dni' => (string) $number,
            'sexo' => 'F', 'token' => 'test-navigation-' . $number, 'fecha_alta' => today()->toDateString(),
            'decision_medica' => 'pendiente', 'estado_credencial' => 'sin_emitir', 'estado' => 'activo']);
    }

    public function test_reception_can_only_see_its_own_requests_in_the_dashboard(): void
    {
        $other = User::factory()->create(['rol' => 'recepcionista']);
        foreach ([[$this->reception, 'Mi solicitud visible'], [$other, 'Solicitud reservada de otro operador']] as [$user, $description]) {
            Solicitud::create(['socio_id' => $this->member()->id, 'solicitante_id' => $user->id,
                'tipo' => Solicitud::TIPO_OTRO, 'descripcion' => $description, 'estado' => Solicitud::ESTADO_PENDIENTE]);
        }
        $this->actingAs($this->reception)->get(route('dashboard'))->assertOk()
            ->assertSee('Mi solicitud visible')->assertDontSee('Solicitud reservada de otro operador');
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
            ->assertSee('Mi solicitud visible')->assertSee('Solicitud reservada de otro operador');
    }

    public function test_embedded_medical_request_is_created_by_reception_and_resolved_only_by_owner(): void
    {
        $member = $this->member();
        $this->actingAs($this->reception);
        Livewire::test(GestionarSolicitudes::class, ['integrado' => true])
            ->set('socioId', (string) $member->id)->call('crearRevisionMedica')
            ->assertHasNoErrors()->assertDispatched('solicitudes-actualizadas');
        $request = Solicitud::sole();
        $this->assertSame('pendiente', $member->fresh()->decision_medica);
        Livewire::test(GestionarSolicitudes::class, ['integrado' => true])
            ->call('abrirResolucion', $request->id)->assertForbidden();
        Livewire::test(GestionarSolicitudes::class, ['integrado' => true])
            ->set('solicitudId', $request->id)->call('resolver', 'aprobada')->assertForbidden();

        $this->actingAs($this->owner);
        Livewire::test(GestionarSolicitudes::class, ['integrado' => true])
            ->call('abrirResolucion', $request->id)->set('respuesta', 'Debe presentar certificado.')
            ->set('decisionMedica', 'requerido')->call('resolver', 'aprobada')
            ->assertHasNoErrors()->assertDispatched('solicitudes-actualizadas');
        $this->assertSame('aprobada', $request->fresh()->estado);
        $this->assertSame('requerido', $member->fresh()->decision_medica);
        $this->assertDatabaseHas('auditorias', ['auditable_id' => $request->id, 'accion' => 'solicitud_aprobada']);
        Livewire::test(GestionarSolicitudes::class, ['integrado' => true])
            ->set('estadoFiltro', 'aprobada')->assertSee('Debe presentar certificado.');
    }

    public function test_embedded_rejection_keeps_history_without_changing_the_medical_decision(): void
    {
        $member = $this->member();
        $request = Solicitud::create(['socio_id' => $member->id, 'solicitante_id' => $this->reception->id,
            'tipo' => Solicitud::TIPO_EXCEPCION_MEDICA, 'descripcion' => 'Revisión pendiente.', 'estado' => 'pendiente']);
        $this->actingAs($this->owner);
        Livewire::test(GestionarSolicitudes::class, ['integrado' => true])
            ->call('abrirResolucion', $request->id)->set('respuesta', 'Se mantiene la revisión pendiente.')
            ->call('resolver', 'rechazada')->assertHasNoErrors();
        $this->assertSame('rechazada', $request->fresh()->estado);
        $this->assertSame('pendiente', $member->fresh()->decision_medica);
        $this->assertDatabaseHas('auditorias', ['auditable_id' => $request->id, 'accion' => 'solicitud_rechazada']);
        $this->actingAs($this->reception);
        Livewire::test(GestionarSolicitudes::class, ['integrado' => true])
            ->set('estadoFiltro', 'rechazada')->assertSee('La decisión fue registrada por la dueña.');
    }

    public function test_normal_enrollment_in_multiple_disciplines_does_not_require_approval_and_rejects_overlap(): void
    {
        $member = $this->member();
        $service = app(InscripcionService::class);
        foreach ([['lunes', '08:00:00', '09:00:00'], ['martes', '08:00:00', '09:00:00'], ['lunes', '08:30:00', '09:30:00']] as $index => [$day, $start, $end]) {
            $discipline = Disciplina::create(['nombre' => 'Disciplina ' . $index, 'estado' => 'activo']);
            $group = Grupo::create(['disciplina_id' => $discipline->id, 'nombre' => 'Grupo ' . $index, 'estado' => 'activo', 'cupo_maximo' => 15]);
            GrupoHorario::create(['grupo_id' => $group->id, 'dia_semana' => $day, 'hora_inicio' => $start, 'hora_fin' => $end]);
            if ($index < 2) {
                $result = $service->solicitarInscripcion($member, $group, $this->reception);
                $this->assertSame('inscripcion_confirmada', $result['tipo']);
            } else {
                try {
                    $service->solicitarInscripcion($member, $group, $this->reception);
                    $this->fail('Overlapping enrollment must still be rejected.');
                } catch (DomainException $exception) {
                    $this->assertStringContainsString('horaria', $exception->getMessage());
                }
            }
        }
        $this->assertSame(2, Inscripcion::count());
        $this->assertSame(0, Solicitud::count());
    }

    public function test_request_events_refresh_dashboard_counters(): void
    {
        $component = Livewire::actingAs($this->owner)->test(Dashboard::class)->assertSet('solicitudesPendientes', 0);
        Solicitud::create(['socio_id' => $this->member()->id, 'solicitante_id' => $this->reception->id,
            'tipo' => Solicitud::TIPO_OTRO, 'descripcion' => 'Nueva solicitud.', 'estado' => 'pendiente']);
        $component->dispatch('solicitudes-actualizadas')->assertSet('solicitudesPendientes', 1);
    }

    public function test_original_dashboard_sections_remain_before_the_additional_requests_section(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSeeInOrder([
            'Panel administrativo', 'Socios registrados', 'Recaudación del mes', 'Cupos por grupo',
            'Permanencias abiertas', 'Asistencias a clase de hoy', 'Solicitudes recientes',
            'Accesos denegados recientes', 'Socios registrados recientemente', 'Solicitudes pendientes e historial',
        ]);
        $this->actingAs($this->reception)->get(route('dashboard'))->assertOk()->assertSeeInOrder([
            'Panel de recepción', 'Buscar socio', 'Mis solicitudes pendientes', 'Cupos por grupo',
            'Permanencias abiertas', 'Asistencias a clase de hoy', 'Solicitudes recientes',
            'Últimas operaciones', 'Solicitudes pendientes e historial',
        ])->assertDontSee('Recaudación del mes');
    }
}
