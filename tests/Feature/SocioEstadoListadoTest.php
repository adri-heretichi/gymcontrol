<?php

namespace Tests\Feature;

use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocioEstadoListadoTest extends TestCase
{
    use RefreshDatabase;

    private User $duena;
    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duena = User::factory()->create(['rol' => 'admin']);
        $disciplina = Disciplina::create(['nombre' => 'Funcional', 'estado' => 'activa']);
        $this->grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => 'Grupo de prueba',
            'cupo_maximo' => 10,
            'estado' => 'activo',
        ]);
    }

    public function test_listado_distingue_estado_administrativo_de_las_etapas_de_acceso(): void
    {
        $preliminar = $this->crearSocio('Preliminar');
        $pendienteInscripcion = $this->crearSocio('Inscripción pendiente');
        $pendientePago = $this->crearSocio('Pago pendiente', 'no_requerido');
        $decisionPendiente = $this->crearSocio('Decisión pendiente');
        $documentacionPendiente = $this->crearSocio('Documento pendiente', 'requerido');
        $requisitosCompletos = $this->crearSocio('Requisitos completos', 'no_requerido');
        $inactivo = $this->crearSocio('Inactivo', 'no_requerido', 'inactivo');

        $this->crearInscripcion($pendienteInscripcion, Inscripcion::ESTADO_PENDIENTE);
        $this->crearInscripcion($pendientePago, Inscripcion::ESTADO_APROBADA);

        $this->crearInscripcionConPago($decisionPendiente);
        $this->crearInscripcionConPago($documentacionPendiente);
        $this->crearInscripcionConPago($requisitosCompletos);

        $this->actingAs($this->duena)
            ->get('/socios')
            ->assertOk()
            ->assertSee('Estado administrativo')
            ->assertSee('Situación de acceso')
            ->assertSee('Activo')
            ->assertSee('Administrativamente inactivo')
            ->assertSee('Registro preliminar')
            ->assertSee('Inscripción pendiente')
            ->assertSee('Pendiente de pago/cobertura')
            ->assertSee('Pendiente de decisión médica')
            ->assertSee('Documentación médica pendiente')
            ->assertSee('Requisitos previos completos')
            ->assertSee('todavía no puede ingresar')
            ->assertSee('credencial activa, grupo y horario');

        $this->assertSame('activo', $preliminar->fresh()->estado);
        $this->assertSame('inactivo', $inactivo->fresh()->estado);
    }

    private function crearSocio(string $nombre, string $decision = 'pendiente', string $estado = 'activo'): Socio
    {
        static $numero = 0;
        $numero++;

        return Socio::create([
            'nombre' => $nombre,
            'apellido' => 'Prueba',
            'dni' => str_pad((string) $numero, 8, '0', STR_PAD_LEFT),
            'sexo' => 'F',
            'token' => 'ESTADO-' . $numero,
            'fecha_alta' => today()->toDateString(),
            'estado' => $estado,
            'decision_medica' => $decision,
        ]);
    }

    private function crearInscripcion(Socio $socio, string $estado): Inscripcion
    {
        return Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupo->id,
            'fecha_inscripcion' => today()->toDateString(),
            'estado' => $estado,
            'aprobado_por' => $estado === Inscripcion::ESTADO_APROBADA ? $this->duena->id : null,
            'aprobado_at' => $estado === Inscripcion::ESTADO_APROBADA ? now() : null,
        ]);
    }

    private function crearInscripcionConPago(Socio $socio): void
    {
        $inscripcion = $this->crearInscripcion($socio, Inscripcion::ESTADO_APROBADA);
        $pago = Pago::create([
            'socio_id' => $socio->id,
            'operador_id' => $this->duena->id,
            'fecha_pago' => today()->toDateString(),
            'importe' => 10000,
            'metodo_pago' => 'efectivo',
            'estado' => Pago::ESTADO_ACTIVO,
        ]);

        PagoDetalle::create([
            'pago_id' => $pago->id,
            'inscripcion_id' => $inscripcion->id,
            'concepto' => 'Cobertura de prueba',
            'fecha_inicio_cobertura' => today()->startOfMonth()->toDateString(),
            'fecha_fin_cobertura' => today()->endOfMonth()->toDateString(),
            'subtotal' => 10000,
            'estado_cobertura' => PagoDetalle::COBERTURA_VIGENTE,
        ]);
    }
}
