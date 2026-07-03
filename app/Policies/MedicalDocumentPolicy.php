<?php

namespace App\Policies;

use App\Models\MedicalDocument;
use App\Models\User;

/**
 * Política de documentos médicos.
 * Solo el dueño del historial, su supervisor o el owner del hogar pueden
 * eliminar documentos (spec §4 Tab 9 — Permisos).
 */
class MedicalDocumentPolicy
{
    public function view(User $user, MedicalDocument $document): bool
    {
        return $user->household_id === $document->household_id && $user->canManage($document->patient);
    }

    public function create(User $user, User $patient): bool
    {
        return ! $user->isViewer() && $user->household_id === $patient->household_id && $user->canManage($patient);
    }

    public function delete(User $user, MedicalDocument $document): bool
    {
        return ! $user->isViewer() && $user->household_id === $document->household_id && $user->canManage($document->patient);
    }
}
