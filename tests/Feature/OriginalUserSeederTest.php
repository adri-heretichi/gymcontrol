<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OriginalUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_installation_creates_only_original_operators_and_disciplines(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([
            ['name' => 'Administrador General', 'email' => 'admin@gymcontrol.com', 'rol' => 'admin'],
            ['name' => 'Recepcionista de Turno', 'email' => 'recep@gymcontrol.com', 'rol' => 'recepcionista'],
        ], User::orderBy('id')->get(['name', 'email', 'rol'])->toArray());
        foreach (User::all() as $user) {
            $this->assertNotSame('password', $user->password);
            $this->assertTrue(Hash::check('password', $user->password));
        }
        $this->assertDatabaseCount('disciplinas', 4);
        foreach (['Krav Maga Kids' => 30000, 'Kapap Krav Maga' => 35000, 'Kickboxing Muay Thai' => 35000, 'Funcional' => 35000] as $nombre => $precio) {
            $this->assertDatabaseHas('disciplinas', ['nombre' => $nombre, 'precio' => $precio]);
        }
        foreach (['socios', 'inscripciones', 'pagos', 'pago_detalles', 'aptos_fisicos', 'asistencias', 'asistencias_clases', 'grupos', 'grupo_horarios', 'membresias', 'solicitudes'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }

    public function test_repeated_seeding_restores_original_fields_without_duplicating_operators(): void
    {
        $this->seed(UserSeeder::class);
        $ids = User::orderBy('id')->pluck('id')->all();
        User::where('email', 'admin@gymcontrol.com')->update([
            'name' => 'Nombre modificado',
            'rol' => 'recepcionista',
            'password' => Hash::make('clave-modificada-solo-en-test'),
        ]);

        $this->seed(UserSeeder::class);

        $this->assertSame($ids, User::orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('users', 2);
        $admin = User::where('email', 'admin@gymcontrol.com')->firstOrFail();
        $this->assertSame('Administrador General', $admin->name);
        $this->assertSame('admin', $admin->rol);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_original_operators_can_log_in_with_their_original_roles(): void
    {
        Http::fake();
        $this->seed(DatabaseSeeder::class);
        foreach (['admin@gymcontrol.com' => 'admin', 'recep@gymcontrol.com' => 'recepcionista'] as $email => $rol) {
            auth()->logout();
            Volt::test('pages.auth.login')
                ->set('form.email', $email)
                ->set('form.password', 'password')
                ->call('login')
                ->assertHasNoErrors()
                ->assertRedirect(route('dashboard', absolute: false));
            $this->assertAuthenticatedAs(User::where('email', $email)->firstOrFail());
            $this->assertSame($rol, auth()->user()->rol);
            $this->get(route('dashboard'))->assertOk();
            if ($rol === 'recepcionista') {
                $this->get(route('operadores.index'))->assertForbidden();
            } else {
                $this->get(route('operadores.index'))->assertOk();
            }
        }
    }
}
