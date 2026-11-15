<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Livewire\Dashboard;
use App\Models\AptoFisico;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicalDashboardReviewTest extends TestCase
{
    use RefreshDatabase;

    public static function documents(): array
    {
        return ['pendiente' => ['pendiente_revision', 0, 1, 1], 'rechazado' => ['rechazado', 0, 1, 0], 'emisión futura' => ['vigente', 1, 1, 0], 'aprobado vigente' => ['vigente', 0, 0, 0]];
    }

    #[DataProvider('documents')]
    public function test_indicator_requires_approved_current_document(string $state, int $future, int $missing, int $pending): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Event::fake([GymStateChanged::class]);
        $owner = User::factory()->create(['rol' => 'admin']);
        $member = Socio::create(['nombre' => 'Ficticio', 'apellido' => 'Indicador médico', 'dni' => '91333000', 'sexo' => 'F', 'token' => 'medical-indicator', 'estado' => 'activo', 'fecha_alta' => today(), 'decision_medica' => 'requerido']);
        AptoFisico::create(['socio_id' => $member->id, 'estado' => $state, 'fecha_emision' => today()->addDays($future), 'fecha_vencimiento' => today()->addYear()]);
        Livewire::actingAs($owner)->test(Dashboard::class)->assertSet('certificadosFaltantesOVencidos', $missing)->assertSet('certificadosPendientesRevision', $pending);
    }

    public function test_reception_cannot_receive_medical_indicator_even_with_tampered_public_property(): void
    {
        Event::fake([GymStateChanged::class]);
        $user = User::factory()->create(['rol' => 'recepcionista']);
        Livewire::actingAs($user)->test(Dashboard::class)->set('certificadosPendientesRevision', 99)->call('syncGymState', ['medical', 'permissions'])->assertSet('certificadosPendientesRevision', 0)->assertDontSee('Certificados pendientes de revisión');
    }
}
