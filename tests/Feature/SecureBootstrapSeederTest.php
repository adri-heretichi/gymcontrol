<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SecureBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->bootstrapEnvironmentKeys() as $key) {
            $this->originalEnvironment[$key] = [
                'process' => getenv($key),
                'env_exists' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'server_exists' => array_key_exists($key, $_SERVER),
                'server' => $_SERVER[$key] ?? null,
            ];
            putenv($key . '=');
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $original) {
            if ($original['process'] === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $original['process']);
            }

            if ($original['env_exists']) {
                $_ENV[$key] = $original['env'];
            } else {
                unset($_ENV[$key]);
            }

            if ($original['server_exists']) {
                $_SERVER[$key] = $original['server'];
            } else {
                unset($_SERVER[$key]);
            }
        }

        parent::tearDown();
    }

    public function test_user_seeder_requires_explicit_strong_operator_credentials(): void
    {
        try {
            app(UserSeeder::class)->run();
            $this->fail('The user seeder accepted missing credentials.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('at least 16 characters', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_seeder_creates_configured_operators_without_resetting_existing_accounts(): void
    {
        $ownerPassword = 'owner-secret-for-test-2026';
        $receptionPassword = 'reception-secret-for-test-2026';
        $this->setBootstrapEnvironment([
            'GYMCONTROL_BOOTSTRAP_OWNER_EMAIL' => 'owner@example.test',
            'GYMCONTROL_BOOTSTRAP_OWNER_PASSWORD' => $ownerPassword,
            'GYMCONTROL_BOOTSTRAP_RECEPTION_EMAIL' => 'reception@example.test',
            'GYMCONTROL_BOOTSTRAP_RECEPTION_PASSWORD' => $receptionPassword,
        ]);

        app(UserSeeder::class)->run();

        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $reception = User::where('email', 'reception@example.test')->firstOrFail();

        $this->assertSame('admin', $owner->rol);
        $this->assertSame('recepcionista', $reception->rol);
        $this->assertTrue(password_verify($ownerPassword, $owner->password));
        $this->assertTrue(password_verify($receptionPassword, $reception->password));

        $this->setBootstrapEnvironment([
            'GYMCONTROL_BOOTSTRAP_OWNER_PASSWORD' => 'replacement-secret-for-test',
        ]);
        app(UserSeeder::class)->run();

        $this->assertSame(2, User::count());
        $this->assertTrue(password_verify($ownerPassword, $owner->fresh()->password));
    }

    public function test_clean_installation_seeds_only_original_operators_and_disciplines_even_in_production(): void
    {
        $this->setBootstrapEnvironment([
            'GYMCONTROL_BOOTSTRAP_OWNER_EMAIL' => 'owner@example.test',
            'GYMCONTROL_BOOTSTRAP_OWNER_PASSWORD' => 'owner-secret-for-test-2026',
            'GYMCONTROL_BOOTSTRAP_RECEPTION_EMAIL' => 'reception@example.test',
            'GYMCONTROL_BOOTSTRAP_RECEPTION_PASSWORD' => 'reception-secret-for-test-2026',
        ]);
        $this->app->detectEnvironment(fn () => 'production');
        app(DatabaseSeeder::class)->run();
        $this->assertDatabaseCount('users', 2);
        $originales = User::orderBy('id')->get()->map->getAttributes()->all();
        app(DatabaseSeeder::class)->run();
        $this->assertSame($originales, User::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('disciplinas', 4);
        foreach (['socios', 'inscripciones', 'pagos', 'pago_detalles', 'aptos_fisicos', 'asistencias', 'asistencias_clases', 'grupos', 'grupo_horarios', 'membresias', 'solicitudes'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
        foreach (['Krav Maga Kids' => 30000, 'Kapap Krav Maga' => 35000, 'Kickboxing Muay Thai' => 35000, 'Funcional' => 35000] as $nombre => $precio) {
            $this->assertDatabaseHas('disciplinas', ['nombre' => $nombre, 'precio' => $precio]);
        }
    }

    private function setBootstrapEnvironment(array $values): void
    {
        foreach ($values as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function bootstrapEnvironmentKeys(): array
    {
        return [
            'GYMCONTROL_BOOTSTRAP_OWNER_EMAIL',
            'GYMCONTROL_BOOTSTRAP_OWNER_PASSWORD',
            'GYMCONTROL_BOOTSTRAP_RECEPTION_EMAIL',
            'GYMCONTROL_BOOTSTRAP_RECEPTION_PASSWORD',
        ];
    }
}
