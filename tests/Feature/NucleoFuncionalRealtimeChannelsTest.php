<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NucleoFuncionalRealtimeChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-app-key',
            'broadcasting.connections.reverb.secret' => 'test-app-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app-id',
        ]);
    }

    public function test_owner_can_authorize_administrative_channel_and_reception_cannot(): void
    {
        $owner = User::factory()->create(['rol' => 'admin']);
        $receptionist = User::factory()->create(['rol' => 'recepcionista']);

        $this->actingAs($owner)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-gym.admin',
            ])->assertOk();

        $this->actingAs($receptionist)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-gym.admin',
            ])->assertForbidden();
    }

    public function test_reception_can_authorize_operational_and_own_request_channels_only(): void
    {
        $receptionist = User::factory()->create(['rol' => 'recepcionista']);
        $otherReceptionist = User::factory()->create(['rol' => 'recepcionista']);
        $owner = User::factory()->create(['rol' => 'admin']);

        $this->actingAs($receptionist)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-gym.reception',
            ])->assertOk();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-gym.reception.' . $receptionist->id,
        ])->assertOk();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-gym.reception.' . $otherReceptionist->id,
        ])->assertForbidden();

        $this->actingAs($owner)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-gym.reception',
            ])->assertForbidden();
    }

    public function test_unauthenticated_and_unprivileged_users_cannot_authorize_private_channels(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-gym.admin',
        ])->assertForbidden();

        $unprivileged = User::factory()->create(['rol' => 'socio']);
        $this->actingAs($unprivileged)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-gym.reception',
            ])->assertForbidden();
    }
}
