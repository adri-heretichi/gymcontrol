<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\User;

/**
 * PagoPolicy – Fase 5.
 *
 * Dueña (admin):
 *   - Todo lo que puede la recepcionista, más:
 *   - Consultar recaudación general.
 *   - Anular pagos internamente.
 *   - Modificar precios de membresías/disciplinas.
 *
 * Recepcionista:
 *   - Ver listado de pagos y coberturas de sus socios.
 *   - Crear (registrar) pagos ordinarios.
 *   - Consultar estado económico por inscripción.
 *   - NO puede anular, ver recaudación total, ni modificar precios.
 */
class PagoPolicy
{
    /** Ver listado de pagos. Ambos roles. */
    public function viewAny(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    /** Ver un pago puntual. Ambos roles. */
    public function view(User $user, Pago $pago): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    /** Registrar un nuevo pago ordinario. Ambos roles. */
    public function create(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    /** Consultar recaudación general. Solo la dueña. */
    public function verRecaudacion(User $user): bool
    {
        return $user->esDuena();
    }

    /**
     * Anular un pago internamente (corrección administrativa).
     * Solo la dueña puede anular.
     */
    public function anular(User $user, Pago $pago): bool
    {
        return $user->esDuena() && $pago->estaActivo();
    }

    /**
     * Configurar precios de disciplinas/membresías.
     * Solo la dueña.
     */
    public function configurarPrecios(User $user): bool
    {
        return $user->esDuena();
    }

    /**
     * Modificar un pago (update genérico, solo dueña).
     * La recepcionista no puede editar pagos históricos.
     */
    public function update(User $user, Pago $pago): bool
    {
        return $user->esDuena();
    }

    /**
     * Eliminar un pago. No se elimina: solo se anula internamente.
     * Método mantenido por compatibilidad con Gate::policy() pero siempre false.
     */
    public function delete(User $user, Pago $pago): bool
    {
        return false;
    }
}
