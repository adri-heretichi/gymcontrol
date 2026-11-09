<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('gym.admin', fn ($user) => $user->esDuena());

Broadcast::channel('gym.reception', fn ($user) => $user->esRecepcionista());

Broadcast::channel('gym.reception.{userId}', fn ($user, $userId) => (
    $user->esRecepcionista() && (int) $user->id === (int) $userId
));
