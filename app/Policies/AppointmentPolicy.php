<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Política de acceso a citas médicas.
 * - owner: ve y gestiona todo el hogar.
 * - member: ve y gestiona su propio historial y el de sus viewers supervisados.
 * - viewer: solo lectura de su propio historial.
 */
class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->household_id !== $appointment->household_id) {
            return false;
        }

        return $user->canManage($appointment->patient);
    }

    /** Se invoca con authorize('create', [Appointment::class, $paciente]). */
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

    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        if ($user->household_id !== $appointment->household_id) {
            return false;
        }

        return $user->canManage($appointment->patient);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
