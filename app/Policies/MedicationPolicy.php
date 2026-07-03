<?php

namespace App\Policies;

use App\Models\Medication;
use App\Models\User;

/**
 * Política de acceso a medicamentos — mismo esquema que AppointmentPolicy:
 * owner ve/gestiona todo el hogar; member ve/gestiona su historial y el de
 * sus viewers supervisados; viewer solo lectura de su propio historial.
 */
class MedicationPolicy
{
    public function view(User $user, Medication $medication): bool
    {
        if ($user->household_id !== $medication->household_id) {
            return false;
        }

        return $user->canManage($medication->patient);
    }

    /** Se invoca con authorize('create', [Medication::class, $paciente]). */
    public function create(User $user, User $patient): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        if ($user->household_id !== $patient->household_id) {
            return false;
        }

        return $user->canManage($patient);
    }

    public function update(User $user, Medication $medication): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        if ($user->household_id !== $medication->household_id) {
            return false;
        }

        return $user->canManage($medication->patient);
    }

    public function delete(User $user, Medication $medication): bool
    {
        return $this->update($user, $medication);
    }
}
