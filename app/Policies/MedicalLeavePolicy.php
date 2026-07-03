<?php

namespace App\Policies;

use App\Models\MedicalLeave;
use App\Models\User;

class MedicalLeavePolicy
{
    public function view(User $user, MedicalLeave $leave): bool
    {
        return $user->household_id === $leave->household_id && $user->canManage($leave->patient);
    }

    public function create(User $user, User $patient): bool
    {
        return ! $user->isViewer() && $user->household_id === $patient->household_id && $user->canManage($patient);
    }

    public function update(User $user, MedicalLeave $leave): bool
    {
        return ! $user->isViewer() && $user->household_id === $leave->household_id && $user->canManage($leave->patient);
    }

    public function delete(User $user, MedicalLeave $leave): bool
    {
        return $this->update($user, $leave);
    }
}
