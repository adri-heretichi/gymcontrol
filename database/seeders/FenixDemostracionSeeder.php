<?php

namespace Database\Seeders;

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
use App\Services\CredencialService;
use App\Services\FenixDemoSafety;
use App\Services\HorarioGimnasioService;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FenixDemostracionSeeder extends Seeder
{
    private const MANIFEST_ACTION = 'fenix_demo_created_v1';

    private const GROUPS = [
        ['discipline' => 'Krav Maga Kids', 'letter' => 'A', 'count' => 15, 'day' => 'lunes', 'start' => '08:00:00', 'end' => '09:00:00'],
        ['discipline' => 'Krav Maga Kids', 'letter' => 'B', 'count' => 1, 'day' => 'lunes', 'start' => '09:00:00', 'end' => '10:00:00'],
        ['discipline' => 'Kapap Krav Maga', 'letter' => 'A', 'count' => 15, 'day' => 'martes', 'start' => '08:00:00', 'end' => '09:00:00'],
        ['discipline' => 'Kapap Krav Maga', 'letter' => 'B', 'count' => 2, 'day' => 'martes', 'start' => '09:00:00', 'end' => '10:00:00'],
        ['discipline' => 'Kickboxing Muay Thai', 'letter' => 'A', 'count' => 15, 'day' => 'miercoles', 'start' => '08:00:00', 'end' => '09:00:00'],
        ['discipline' => 'Kickboxing Muay Thai', 'letter' => 'B', 'count' => 1, 'day' => 'miercoles', 'start' => '09:00:00', 'end' => '10:00:00'],
        ['discipline' => 'Funcional', 'letter' => 'A', 'count' => 15, 'day' => 'jueves', 'start' => '08:00:00', 'end' => '09:00:00'],
        ['discipline' => 'Funcional', 'letter' => 'B', 'count' => 2, 'day' => 'jueves', 'start' => '09:00:00', 'end' => '10:00:00'],
    ];

    private const SPECIAL_CASES = [
        1 => ['initial' => 'no_requerido', 'final' => 'no_requerido'],
        2 => ['initial' => 'requerido', 'final' => 'requerido'],
        16 => ['initial' => 'requerido', 'final' => 'requerido', 'certificate' => 'valid'],
        17 => ['initial' => 'requerido', 'final' => 'requerido', 'certificate' => 'expired'],
        32 => ['initial' => 'requerido', 'final' => 'requerido', 'certificate' => 'valid', 'expire_coverage' => true],
        34 => ['initial' => 'pendiente', 'final' => 'pendiente'],
        49 => ['initial' => 'no_requerido', 'final' => 'no_requerido', 'expire_coverage' => true],
        50 => ['initial' => 'no_requerido', 'final' => 'requerido'],
        51 => ['initial' => 'no_requerido', 'final' => 'no_requerido', 'expire_coverage' => true],
        52 => ['initial' => 'no_requerido', 'final' => 'no_requerido', 'expire_coverage' => true, 'revoke' => true],
    ];

    private const PRICE_ENVIRONMENT_KEYS = [
        'Krav Maga Kids' => 'FENIX_DEMO_PRICE_KRAV_MAGA_KIDS',
        'Kapap Krav Maga' => 'FENIX_DEMO_PRICE_KAPAP_KRAV_MAGA',
        'Kickboxing Muay Thai' => 'FENIX_DEMO_PRICE_KICKBOXING_MUAY_THAI',
        'Funcional' => 'FENIX_DEMO_PRICE_FUNCIONAL',
    ];

    public function run(): void
    {
        app(FenixDemoSafety::class)->run(fn () => $this->loadDemo());
    }

    private function loadDemo(): void
    {
        $prices = $this->validatedPrices();
        $this->assertSchemaReady();
        $this->assertOriginalOperatorsExist();
        $this->manifestDigest([]); // Require a signing key before any write.

        $createdFiles = [];

        try {
            Event::fakeFor(function () use ($prices, &$createdFiles): void {
                DB::transaction(function () use ($prices, &$createdFiles): void {
                    if ($this->alreadyLoaded($prices)) {
                        return;
                    }
                    $owner = User::query()->where('rol', 'admin')->orderBy('id')->firstOrFail();
                    $reception = User::query()->where('rol', 'recepcionista')->orderBy('id')->firstOrFail();
                    $disciplines = $this->prepareDisciplines($prices);
                    $groups = $this->prepareGroups($disciplines);
                    $certificateService = app(CertificadoMedicoService::class);
                    $credentialService = app(CredencialService::class);
                    $paymentService = app(PagoService::class);

                    $memberNumber = 1;
                    foreach (self::GROUPS as $groupDefinition) {
                        $group = $groups[$groupDefinition['discipline'] . ':' . $groupDefinition['letter']];
                        $discipline = $disciplines[$groupDefinition['discipline']];

                        for ($seat = 0; $seat < $groupDefinition['count']; $seat++) {
                            $profile = self::SPECIAL_CASES[$memberNumber] ?? [
                                'initial' => 'no_requerido',
                                'final' => 'no_requerido',
                            ];
                            $member = $this->prepareMember($memberNumber);
                            $enrollment = $this->ensureEnrollment($member, $group, $reception);
                            $this->ensureMedicalDecision(
                                $member,
                                $profile['initial'],
                                $owner,
                                $certificateService
                            );
                            $this->ensureCertificate(
                                $member,
                                $profile['certificate'] ?? null,
                                $owner,
                                $certificateService,
                                $createdFiles
                            );
                            $paymentDetail = $this->ensurePayment(
                                $memberNumber,
                                $member,
                                $enrollment,
                                $discipline,
                                $reception,
                                $paymentService
                            );

                            if ($member->estado_credencial === CredencialService::ESTADO_SIN_EMITIR
                                && $credentialService->evaluarRequisitosEmision($member)['habilitado']) {
                                $credentialService->emitirPrimeraVez($member, $owner);
                            }

                            $member->refresh();
                            $this->ensureMedicalDecision($member, $profile['final'], $owner, $certificateService);

                            if (!empty($profile['expire_coverage'])) {
                                $this->expireDemoCoverage($paymentDetail);
                            }

                            if (!empty($profile['revoke'])
                                && $member->estado_credencial === CredencialService::ESTADO_ACTIVO) {
                                $credentialService->revocarCredenciales(
                                    $member,
                                    $owner,
                                    'Revocación ficticia para prueba de acceso.'
                                );
                            }

                            $memberNumber++;
                        }
                    }
                    $manifest = [
                        'version' => 1,
                        'period' => Carbon::now(config('app.timezone'))->format('Ym'),
                        'prices' => $this->priceSignature($prices),
                        'state' => $this->demoState(),
                    ];
                    // Stored atomically with the fixtures, using the existing immutable
                    // audit table. Contains IDs and keyed digests, never raw credentials.
                    Auditoria::registrar(
                        tipoEvento: 'administrativo',
                        accion: self::MANIFEST_ACTION,
                        datosDespues: ['manifest' => $manifest, 'signature' => $this->manifestDigest($manifest)],
                        userId: $owner->id,
                    );
                }); // No automatic retries: filesystem writes are not transactional.
            });
        } catch (Throwable $exception) {
            foreach ($createdFiles as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception instanceof QueryException
                ? new RuntimeException('La carga se revirtió por un error de base de datos; no se exponen consultas ni valores sensibles.')
                : $exception;
        }
    }

    /** Repeated loads are read-only. Any ambiguity or manual change requires review. */
    private function alreadyLoaded(array $prices): bool
    {
        $markers = Auditoria::query()->where('accion', self::MANIFEST_ACTION)->lockForUpdate()->get();
        if ($markers->count() > 1) {
            throw new RuntimeException('La procedencia de la demostración es ambigua; no se modificó ningún dato.');
        }
        if ($markers->isEmpty()) {
            if ($this->reservedMembers()->exists()
                || Grupo::query()->whereIn('nombre', ['DEMO FENIX - Grupo A', 'DEMO FENIX - Grupo B'])->exists()
                || Pago::query()->where('referencia', 'like', 'FENIX-DEMO-%')->exists()) {
                throw new RuntimeException('Existen identificadores reservados sin procedencia verificada; no se reutilizarán registros ajenos.');
            }

            return false;
        }
        $payload = $markers->first()->datos_despues;
        $manifest = $payload['manifest'] ?? null;
        $signature = $payload['signature'] ?? null;
        if (!is_array($manifest) || !is_string($signature)
            || !hash_equals($this->manifestDigest($manifest), $signature)
            || ($manifest['version'] ?? null) !== 1) {
            throw new RuntimeException('No se pudo verificar la procedencia de la demostración; revise el registro y la clave de aplicación.');
        }
        if (($manifest['period'] ?? null) !== Carbon::now(config('app.timezone'))->format('Ym')
            || !is_array($manifest['prices'] ?? null)
            || $this->manifestDigest($manifest['prices']) !== $this->manifestDigest($this->priceSignature($prices))) {
            throw new RuntimeException('El período o los precios cambiaron; la demostración existente requiere revisión y no se actualizará automáticamente.');
        }
        if (!is_array($manifest['state'] ?? null)
            || $this->manifestDigest($manifest['state']) !== $this->manifestDigest($this->demoState())) {
            throw new RuntimeException('Los datos o documentos de demostración fueron modificados; no se sobrescribirán cambios manuales.');
        }

        return true;
    }

    private function reservedMembers(): \Illuminate\Database\Eloquent\Builder
    {
        return Socio::query()->where(function ($query): void {
            $query->whereIn('dni', array_map('strval', range(99990001, 99990066)))
                ->orWhere('correo', 'like', 'fenix-demo-%@gymcontrol.test');
        });
    }

    private function priceSignature(array $prices): array
    {
        return array_map(fn ($price) => number_format($price, 2, '.', ''), $prices);
    }

    private function manifestDigest(array $data): string
    {
        $key = config('app.key');
        if (!is_string($key) || $key === '') {
            throw new RuntimeException('La procedencia de la demostración requiere una clave de aplicación configurada.');
        }

        return base64_encode(hash_hmac('sha256', "fenix-demo-v1\0" . json_encode($this->canonicalData($data), JSON_THROW_ON_ERROR), $key, true));
    }

    private function canonicalData(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonicalData($value);
            }
        }

        return $data;
    }

    /** Only identifiers and keyed digests leave this method, never row contents or paths. */
    private function demoState(): array
    {
        $memberIds = $this->reservedMembers()->pluck('id');
        $groupIds = Grupo::query()->whereIn('nombre', ['DEMO FENIX - Grupo A', 'DEMO FENIX - Grupo B'])->pluck('id');
        $enrollmentIds = Inscripcion::query()->whereIn('socio_id', $memberIds)->orWhereIn('grupo_id', $groupIds)->pluck('id');
        $paymentIds = Pago::query()->whereIn('socio_id', $memberIds)->orWhere('referencia', 'like', 'FENIX-DEMO-%')->pluck('id');
        $queries = [
            'disciplinas' => DB::table('disciplinas')->whereIn('nombre', Disciplina::PLANES_FENIX),
            'socios' => DB::table('socios')->whereIn('id', $memberIds),
            'grupos' => DB::table('grupos')->whereIn('id', $groupIds),
            'grupo_horarios' => DB::table('grupo_horarios')->whereIn('grupo_id', $groupIds),
            'inscripciones' => DB::table('inscripciones')->whereIn('id', $enrollmentIds),
            'pagos' => DB::table('pagos')->whereIn('id', $paymentIds),
            'pago_detalles' => DB::table('pago_detalles')->whereIn('pago_id', $paymentIds)->orWhereIn('inscripcion_id', $enrollmentIds),
            'aptos_fisicos' => DB::table('aptos_fisicos')->whereIn('socio_id', $memberIds),
        ];
        $state = [];
        foreach ($queries as $table => $query) {
            $rows = $query->orderBy('id')->get();
            $state[$table] = ['ids' => $rows->pluck('id')->all(), 'digest' => $this->manifestDigest($rows->map(fn ($row) => (array) $row)->all())];
            if ($table === 'aptos_fisicos') {
                foreach ($rows as $row) {
                    if ($row->archivo) {
                        if (!Storage::disk('local')->exists($row->archivo)) {
                            throw new RuntimeException('Falta un documento privado de demostración; no se reemplazará automáticamente.');
                        }
                        $state['files'][$row->id] = $this->manifestDigest(['content' => base64_encode(Storage::disk('local')->get($row->archivo))]);
                    }
                }
            }
        }

        return $state;
    }

    /**
     * @return array<string, float>
     */
    private function validatedPrices(): array
    {
        $configured = (array) config('fenix_demo.prices', []);
        $prices = [];
        $invalid = [];

        foreach (Disciplina::PLANES_FENIX as $discipline) {
            $value = $configured[$discipline] ?? null;
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                $invalid[] = self::PRICE_ENVIRONMENT_KEYS[$discipline];
                continue;
            }

            if (!is_numeric($value) || !is_finite((float) $value) || round((float) $value, 2) <= 0 || (float) $value > 999999.99) {
                $invalid[] = self::PRICE_ENVIRONMENT_KEYS[$discipline];
                continue;
            }

            $prices[$discipline] = round((float) $value, 2);
        }

        if ($invalid !== []) {
            throw new RuntimeException(
                'No se crearon datos. Configure importes mayores que cero y dentro del límite permitido: ' . implode(', ', $invalid) . '.'
            );
        }

        return $prices;
    }

    private function assertSchemaReady(): void
    {
        foreach (['socios', 'disciplinas', 'grupos', 'grupo_horarios', 'inscripciones', 'pagos', 'pago_detalles', 'aptos_fisicos', 'auditorias'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Falta la tabla {$table}. Aplique las migraciones aprobadas antes de usar este seeder.");
            }
        }

        if (!Schema::hasColumn('disciplinas', 'precio')) {
            throw new RuntimeException(
                'No se crearon datos. Falta la migración de disciplinas.precio; aplique la migración por el procedimiento aprobado y vuelva a configurar los cuatro precios.'
            );
        }
    }

    private function assertOriginalOperatorsExist(): void
    {
        foreach (['admin', 'recepcionista'] as $role) {
            if (!User::query()->where('rol', $role)->exists()) {
                throw new RuntimeException(
                    "No existe una cuenta original con rol {$role}. El seeder no crea ni modifica usuarios; no ejecute UserSeeder para resolverlo."
                );
            }
        }
    }

    /**
     * @param array<string, float> $prices
     * @return array<string, Disciplina>
     */
    private function prepareDisciplines(array $prices): array
    {
        $disciplines = Disciplina::query()
            ->whereIn('nombre', Disciplina::PLANES_FENIX)
            ->get()
            ->keyBy('nombre');

        foreach (Disciplina::PLANES_FENIX as $name) {
            if (!$disciplines->has($name)) {
                throw new RuntimeException("No existe la disciplina Fénix requerida: {$name}.");
            }

            $discipline = $disciplines->get($name);
            if (!in_array($discipline->estado, ['activo', 'activa'], true)) {
                throw new RuntimeException("La disciplina {$name} está inactiva; el seeder no la habilitará automáticamente.");
            }

            $discipline->update([
                'precio' => $prices[$name],
                'estado' => 'activo',
            ]);
        }

        return $disciplines->all();
    }

    /**
     * @param array<string, Disciplina> $disciplines
     * @return array<string, Grupo>
     */
    private function prepareGroups(array $disciplines): array
    {
        $groups = [];

        foreach (self::GROUPS as $definition) {
            $discipline = $disciplines[$definition['discipline']];
            $name = "DEMO FENIX - Grupo {$definition['letter']}";
            $matches = Grupo::query()
                ->where('disciplina_id', $discipline->id)
                ->where('nombre', $name)
                ->get();

            if ($matches->isNotEmpty()) {
                throw new RuntimeException('Apareció un grupo reservado durante la carga; operación rechazada sin reutilizarlo.');
            }

            $group = Grupo::create([
                'disciplina_id' => $discipline->id,
                'nombre' => $name,
                'profesor' => 'Coach de demostración',
                'cupo_maximo' => 15,
                'estado' => 'activo',
            ]);

            $this->ensureSchedule($group, $definition);
            $groups[$definition['discipline'] . ':' . $definition['letter']] = $group->fresh(['horarios', 'disciplina']);
        }

        return $groups;
    }

    /**
     * @param array{day: string, start: string, end: string} $definition
     */
    private function ensureSchedule(Grupo $group, array $definition): void
    {
        $schedules = $group->horarios()->get();
        if ($schedules->isNotEmpty()) {
            throw new RuntimeException("El grupo de demostración {$group->nombre} tiene horarios inesperados.");
        }

        $conflict = HorarioGimnasioService::buscarSuperposicionProfesora(
            $definition['day'],
            $definition['start'],
            $definition['end'],
            $group->id
        );
        if ($conflict) {
            throw new RuntimeException(
                "El horario de {$group->nombre} se superpone con {$conflict->nombre}; no se modificó ningún dato."
            );
        }

        GrupoHorario::create([
            'grupo_id' => $group->id,
            'dia_semana' => $definition['day'],
            'hora_inicio' => $definition['start'],
            'hora_fin' => $definition['end'],
        ]);
    }

    private function prepareMember(int $number): Socio
    {
        $email = sprintf('fenix-demo-%03d@gymcontrol.test', $number);
        $dni = (string) (99990000 + $number);
        $collision = Socio::query()
            ->where('dni', $dni)
            ->orWhere('correo', $email)
            ->first();

        if ($collision) {
            throw new RuntimeException("Colisión con un registro ajeno a la demostración para el socio ficticio {$number}.");
        }

        return Socio::create([
            'correo' => $email,
            'membresia_id' => null,
            'apellido' => sprintf('Demo Fénix %03d', $number),
            'nombre' => 'Participante',
            'dni' => $dni,
            'sexo' => $number % 2 === 0 ? 'F' : 'M',
            'celular' => null,
            'foto' => null,
            'token' => Str::random(40),
            'qr' => null,
            'pin' => null,
            'qr_token' => null,
            'estado_credencial' => CredencialService::ESTADO_SIN_EMITIR,
            'decision_medica' => 'pendiente',
            'fecha_alta' => now()->toDateString(),
            'fecha_vencimiento' => null,
            'estado' => 'activo',
        ]);
    }

    private function ensureEnrollment(Socio $member, Grupo $group, User $reception): Inscripcion
    {
        $enrollment = Inscripcion::query()
            ->where('socio_id', $member->id)
            ->where('grupo_id', $group->id)
            ->first();

        if ($enrollment) {
            throw new RuntimeException('Apareció una inscripción durante la carga; no se reutilizará ni sustituirá.');
        }

        $result = app(InscripcionService::class)->solicitarInscripcion(
            $member,
            $group,
            $reception,
            'Inscripción ficticia de demostración Fénix.'
        );

        if (($result['tipo'] ?? null) !== 'inscripcion_confirmada') {
            throw new RuntimeException("No se pudo confirmar la inscripción demo de {$member->correo}.");
        }

        return $result['modelo'];
    }

    private function ensureMedicalDecision(
        Socio $member,
        string $decision,
        User $owner,
        CertificadoMedicoService $service
    ): void {
        $member->refresh();
        if ($member->decision_medica !== $decision) {
            $service->definirDecision($member, $decision, $owner);
        }
    }

    /**
     * @param array<int, string> $createdFiles
     */
    private function ensureCertificate(
        Socio $member,
        ?string $certificateType,
        User $owner,
        CertificadoMedicoService $service,
        array &$createdFiles
    ): void {
        if ($certificateType === null) {
            return;
        }

        $existing = $member->aptosFisicos()
            ->latest('fecha_emision')
            ->latest('id')
            ->first();
        if ($existing) {
            throw new RuntimeException('Apareció un certificado durante la carga; no se reutilizará ni reemplazará.');
        }

        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $expired = $certificateType === 'expired';
        $emission = $expired ? $today->copy()->subDays(60) : $today->copy()->subDay();
        $expiration = $expired ? $today->copy()->subDay() : $today->copy()->addYear();
        $fakeFile = UploadedFile::fake()->createWithContent(
            'FICTICIO-SIN-VALIDEZ-MEDICA.pdf',
            "%PDF-1.4\nDOCUMENTO FICTICIO PARA PRUEBAS. SIN VALIDEZ MEDICA NI DATOS CLINICOS.\n%%EOF"
        );

        $certificate = $service->registrarCertificado(
            $member,
            $emission->toDateString(),
            $expiration->toDateString(),
            $owner,
            $fakeFile
        );
        if ($certificate->archivo) {
            $createdFiles[] = $certificate->archivo;
        }
    }

    private function ensurePayment(
        int $memberNumber,
        Socio $member,
        Inscripcion $enrollment,
        Disciplina $discipline,
        User $reception,
        PagoService $service
    ): PagoDetalle {
        $reference = $this->paymentReference($memberNumber);
        $demoPayment = Pago::query()
            ->where('socio_id', $member->id)
            ->where('referencia', $reference)
            ->first();

        if ($demoPayment) {
            throw new RuntimeException('Apareció un pago durante la carga; no se reutilizará ni modificará.');
        }

        $today = Carbon::now(config('app.timezone'))->toDateString();
        $existingCoverage = $enrollment->pagoDetalles()
            ->where('estado_cobertura', PagoDetalle::COBERTURA_VIGENTE)
            ->whereDate('fecha_inicio_cobertura', '<=', $today)
            ->whereDate('fecha_fin_cobertura', '>=', $today)
            ->whereHas('pago', fn ($query) => $query
                ->where('socio_id', $member->id)
                ->where('estado', Pago::ESTADO_ACTIVO))
            ->latest('id')
            ->first();
        if ($existingCoverage) {
            throw new RuntimeException('Apareció una cobertura durante la carga; no se reutilizará ni modificará.');
        }

        $start = Carbon::now(config('app.timezone'))->startOfMonth()->toDateString();
        $end = Carbon::now(config('app.timezone'))->endOfMonth()->toDateString();
        $payment = $service->registrarPago(
            $member,
            [[
                'inscripcion_id' => $enrollment->id,
                'concepto' => 'Cuota demo Fénix - ' . $discipline->nombre,
                'fecha_inicio' => $start,
                'fecha_fin' => $end,
                'subtotal' => $discipline->precio,
            ]],
            'efectivo',
            $reception,
            $reference,
            $today
        );

        return $payment->detalles->firstOrFail();
    }

    private function expireDemoCoverage(PagoDetalle $detail): void
    {
        if (!str_starts_with((string) $detail->pago()->value('referencia'), 'FENIX-DEMO-')) {
            return;
        }

        $end = Carbon::now(config('app.timezone'))->subDay()->startOfDay();
        $detail->update([
            'fecha_inicio_cobertura' => $end->copy()->subDays(29)->toDateString(),
            'fecha_fin_cobertura' => $end->toDateString(),
            'estado_cobertura' => PagoDetalle::COBERTURA_VENCIDA,
        ]);
    }

    private function paymentReference(int $memberNumber): string
    {
        return sprintf('FENIX-DEMO-%03d-%s', $memberNumber, Carbon::now(config('app.timezone'))->format('Ym'));
    }
}
