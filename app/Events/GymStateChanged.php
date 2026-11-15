<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** Invalida consultas autorizadas; nunca transporta registros del gimnasio. */
class GymStateChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $connection = 'database';

    public string $queue = 'realtime';

    public bool $afterCommit = true;

    public function __construct(public readonly string $changeId, public readonly array $resources, private readonly string $audience, private readonly ?int $requesterId = null) {}

    public function broadcastOn(): array
    {
        return [match ($this->audience) {
            'owner' => new PrivateChannel('gym.admin'),
            'staff' => new PrivateChannel('gym.reception'),
            'requester' => new PrivateChannel('gym.reception.'.$this->requesterId),
            'terminal' => new Channel('gym.terminal'),
            default => throw new \InvalidArgumentException('Audiencia inválida.'),
        }];
    }

    public function broadcastAs(): string
    {
        return 'gym.state-changed';
    }

    public function broadcastWith(): array
    {
        return ['change_id' => $this->changeId, 'resources' => $this->resources];
    }
}
