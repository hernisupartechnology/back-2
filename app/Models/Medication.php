<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Medicamento — flujo de estados desde sin_orden hasta completado.
 * MedicationObserver calcula end_date y remaining_doses automáticamente.
 */
class Medication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'appointment_id', 'household_id', 'user_id', 'registered_by',
        'name', 'active_ingredient', 'presentation', 'dosage', 'frequency',
        'duration_days', 'quantity', 'start_date', 'end_date',
        'is_recurring', 'recurrence_days', 'alert_days_before',
        'status', 'denied_reason', 'authorization_date', 'authorization_number',
        'claimed_date', 'notes',
        'track_intake', 'intake_quantity_per_dose', 'remaining_doses', 'low_stock_alert_doses',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'authorization_date' => 'date',
            'claimed_date' => 'date',
            'is_recurring' => 'boolean',
            'track_intake' => 'boolean',
            'intake_quantity_per_dose' => 'decimal:2',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(MedicationRenewal::class)->orderByDesc('renewal_number');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MedicationSchedule::class);
    }

    public function intakeLogs(): HasMany
    {
        return $this->hasMany(MedicationIntakeLog::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'related_id')
            ->where('related_type', 'medication');
    }

    /** Días restantes antes de que venza (para semáforo de renovación). */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (! $this->end_date || ! $this->is_recurring) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->end_date, false);
    }
}
