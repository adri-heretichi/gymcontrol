<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Livewire\Pagos\ListarPagos;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use App\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RealtimePaymentPresentationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $reception;

    private Pago $payment;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('La regresión requiere SQLite en memoria.');
        }
        Event::fake([GymStateChanged::class]);
        Http::fake();
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->reception = User::factory()->create(['rol' => 'recepcionista']);
        $member = Socio::create(['nombre' => 'Persona', 'apellido' => 'Ficticia', 'dni' => '97777777', 'sexo' => 'F', 'token' => 'payment-presentation-test', 'fecha_alta' => today(), 'estado' => 'activo', 'decision_medica' => 'pendiente']);
        $this->payment = Pago::create(['socio_id' => $member->id, 'fecha_pago' => today(), 'importe' => 35000, 'metodo_pago' => 'efectivo', 'estado' => 'activo', 'user_id' => $this->reception->id]);
        PagoDetalle::create(['pago_id' => $this->payment->id, 'concepto' => 'Cuota ficticia', 'fecha_inicio_cobertura' => today(), 'fecha_fin_cobertura' => today()->addMonth(), 'subtotal' => 35000, 'estado_cobertura' => 'vigente']);
    }

    public function test_reception_sees_payment_state_without_cancellation_actions(): void
    {
        Livewire::actingAs($this->reception)->test(ListarPagos::class)
            ->assertSee('Estado')->assertSee('Activo')->assertSee('Vigente')
            ->assertDontSee('Acciones')->assertDontSee('Anular');
    }

    public function test_realtime_refresh_displays_cancelled_payment_and_suspended_coverage_to_reception(): void
    {
        $screen = Livewire::actingAs($this->reception)->test(ListarPagos::class);
        app(PagoService::class)->anularPago($this->payment, $this->owner, 'Corrección de comprobante ficticio para regresión.');
        $screen->call('syncGymState', ['payments'])
            ->assertSee('Anulado')->assertSee('Suspendida')->assertDontSee('Vigente')
            ->assertDontSee('Confirmar anulación');
        $this->assertDatabaseHas('pagos', ['id' => $this->payment->id, 'estado' => 'anulado']);
        $this->assertDatabaseHas('pago_detalles', ['pago_id' => $this->payment->id, 'estado_cobertura' => 'suspendida']);
    }

    public function test_owner_also_sees_suspended_coverage_after_cancellation(): void
    {
        app(PagoService::class)->anularPago($this->payment, $this->owner, 'Corrección de comprobante ficticio para regresión.');
        Livewire::actingAs($this->owner)->test(ListarPagos::class)
            ->assertSee('Anulado')->assertSee('Suspendida')->assertDontSee('Vigente');
    }
}
