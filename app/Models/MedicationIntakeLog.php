<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de una toma de medicamento.
 * MedicationIntakeObserver calcula delay_minutes y status automáticamente.
 */
class MedicationIntakeLog extends Model
{
    protected $fillable = [
        'medication_id', 'medication_schedule_id', 'user_id', 'registered_by',
        'scheduled_datetime', 'taken_at', 'status', 'delay_minutes',
        'dose_taken', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_datetime' => 'datetime',
            'taken_at' => 'datetime',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MedicationSchedule::class, 'medication_schedule_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
