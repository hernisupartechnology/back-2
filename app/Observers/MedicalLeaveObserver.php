<?php

namespace App\Observers;

use App\Models\MedicalLeave;

/**
 * Observer de incapacidades.
 * Calcula total_days automáticamente = end_date - start_date + 1.
 */
class MedicalLeaveObserver
{
    public function creating(MedicalLeave $leave): void
    {
        $this->calcularTotalDays($leave);
    }

    public function updating(MedicalLeave $leave): void
    {
        $this->calcularTotalDays($leave);
    }

    private function calcularTotalDays(MedicalLeave $leave): void
    {
        if ($leave->start_date && $leave->end_date) {
            $leave->total_days = $leave->start_date->diffInDays($leave->end_date) + 1;
        }
    }
}
