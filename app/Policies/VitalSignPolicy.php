<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VitalSign;

class VitalSignPolicy
{
    public function view(User $user, VitalSign $vitalSign): bool
    {
        return $user->household_id === $vitalSign->patient->household_id && $user->canManage($vitalSign->patient);
    }

    public function create(User $user, User $patient): bool
    {
        return ! $user->isViewer() && $user->household_id === $patient->household_id && $user->canManage($patient);
    }

    public function update(User $user, VitalSign $vitalSign): bool
    {
        return ! $user->isViewer() && $user->household_id === $vitalSign->patient->household_id && $user->canManage($vitalSign->patient);
    }

    public function delete(User $user, VitalSign $vitalSign): bool
    {
        return $this->update($user, $vitalSign);
    }
}
