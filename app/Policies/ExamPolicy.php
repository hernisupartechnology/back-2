<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function view(User $user, Exam $exam): bool
    {
        return $user->household_id === $exam->household_id && $user->canManage($exam->patient);
    }

    public function create(User $user, User $patient): bool
    {
        return ! $user->isViewer() && $user->household_id === $patient->household_id && $user->canManage($patient);
    }

    public function update(User $user, Exam $exam): bool
    {
        return ! $user->isViewer() && $user->household_id === $exam->household_id && $user->canManage($exam->patient);
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $this->update($user, $exam);
    }
}
