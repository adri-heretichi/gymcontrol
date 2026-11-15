<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Livewire\AptosFisicos\CrearAptoFisico;
use App\Livewire\AptosFisicos\EditarAptoFisico;
use App\Models\AptoFisico;
use App\Models\Auditoria;
use App\Models\Socio;
use App\Models\User;
use App\Services\CertificadoMedicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicalSubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $reception;

    private Socio $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Event::fake([GymStateChanged::class]);
        Storage::fake('local');
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->reception = User::factory()->create(['rol' => 'recepcionista']);
        $this->member = Socio::create(['nombre' => 'Prueba', 'apellido' => 'Presentación médica', 'dni' => '91555555', 'sexo' => 'F', 'token' => 'submission-test', 'fecha_alta' => today(), 'estado' => 'activo', 'decision_medica' => 'requerido']);
    }

    private function present(): AptoFisico
    {
        Livewire::actingAs($this->owner)->test(CrearAptoFisico::class, ['socio' => $this->member])->set('archivo_cargado', UploadedFile::fake()->createWithContent('ficticio.pdf', "%PDF-1.4\nFICTICIO SIN VALIDEZ MEDICA\n%%EOF"))->call('guardar')->assertHasNoErrors();

        return AptoFisico::latest('id')->firstOrFail();
    }

    public static function decisions(): array
    {
        return ['aprobación' => ['vigente', true, 'certificado_medico_aprobado'], 'rechazo' => ['rechazado', false, 'certificado_medico_rechazado']];
    }

    #[DataProvider('decisions')]
    public function test_submission_is_pending_until_explicit_owner_review(string $status, bool $allowed, string $audit): void
    {
        $doc = $this->present();
        $this->assertSame('pendiente_revision', $doc->estado);
        Storage::disk('local')->assertExists($doc->archivo);
        $this->assertFalse(app(CertificadoMedicoService::class)->evaluarRequisitoMedico($this->member->fresh())['cumple']);
        $this->assertSame('requerido', $this->member->fresh()->decision_medica);
        Livewire::actingAs($this->owner)->test(EditarAptoFisico::class, ['aptoFisico' => $doc])->set('estado', $status)->call('actualizar')->assertHasNoErrors();
        $this->assertSame($status, $doc->fresh()->estado);
        $this->assertSame($allowed, app(CertificadoMedicoService::class)->evaluarRequisitoMedico($this->member->fresh())['cumple']);
        $this->assertTrue(Auditoria::where('accion', $audit)->exists());
    }

    public function test_no_requirement_does_not_depend_on_pending_document(): void
    {
        $this->member->update(['decision_medica' => 'no_requerido']);
        $doc = $this->present();
        $this->assertSame('pendiente_revision', $doc->estado);
        $this->assertTrue(app(CertificadoMedicoService::class)->evaluarRequisitoMedico($this->member->fresh())['cumple']);
    }

    public function test_reception_cannot_present_review_or_download_document(): void
    {
        $doc = $this->present();
        Livewire::actingAs($this->reception)->test(CrearAptoFisico::class, ['socio' => $this->member])->assertForbidden();
        Livewire::actingAs($this->reception)->test(EditarAptoFisico::class, ['aptoFisico' => $doc])->assertForbidden();
        $this->actingAs($this->reception)->get(route('aptos-fisicos.download', $doc))->assertForbidden();
        $this->assertSame('pendiente_revision', $doc->fresh()->estado);
    }

    public function test_replacement_preserves_old_file_and_requires_new_review(): void
    {
        $old = $this->present();
        Livewire::actingAs($this->owner)->test(EditarAptoFisico::class, ['aptoFisico' => $old])->set('estado', 'vigente')->call('actualizar')->assertHasNoErrors();
        Livewire::actingAs($this->owner)->test(EditarAptoFisico::class, ['aptoFisico' => $old->fresh()])->set('archivo_cargado', UploadedFile::fake()->createWithContent('otro.pdf', "%PDF-1.4\nOTRO FICTICIO\n%%EOF"))->call('actualizar')->assertHasNoErrors();
        $this->assertSame(2, AptoFisico::count());
        $new = AptoFisico::latest('id')->first();
        $this->assertSame('pendiente_revision', $new->estado);
        $this->assertSame('vigente', $old->fresh()->estado);
        Storage::disk('local')->assertExists($old->archivo);
        Storage::disk('local')->assertExists($new->archivo);
    }
}
