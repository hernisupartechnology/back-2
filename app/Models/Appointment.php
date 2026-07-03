<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cita médica o necesidad sin agendar.
 * El Observer calcula recurrence_interval_days y genera la siguiente cita recurrente.
 */
class Appointment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'household_id', 'user_id', 'registered_by', 'doctor_id', 'doctor_name_free',
        'specialty', 'appointment_type', 'ips', 'address',
        'is_need', 'need_reason', 'need_urgency', 'need_registered_date',
        'max_days_to_schedule', 'alert_days_before_scheduling',
        'appointment_date', 'reason', 'diagnosis', 'notes',
        'status', 'cancelled_reason', 'cancelled_by',
        'next_appointment_date', 'next_appointment_notes', 'next_appointment_specialty',
        'is_recurring', 'recurrence_type', 'recurrence_interval_days',
        'alert_days_before_appointment', 'parent_appointment_id',
        'recurrence_number', 'next_recurrence_generated',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'datetime',
            'need_registered_date' => 'date',
            'next_appointment_date' => 'date',
            'is_need' => 'boolean',
            'is_recurring' => 'boolean',
            'next_recurrence_generated' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Relaciones
    // ──────────────────────────────────────────────────────────────

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

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** Cita padre en la cadena recurrente. */
    public function parentAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'parent_appointment_id');
    }

    /** Citas hijas en la cadena recurrente. */
    public function childAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'parent_appointment_id');
    }

    /** Log de la cadena recurrente. */
    public function recurrenceLog(): HasMany
    {
        return $this->hasMany(AppointmentRecurrenceLog::class, 'parent_appointment_id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function medicalLeaves(): HasMany
    {
        return $this->hasMany(MedicalLeave::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'related_id')
            ->where('related_type', 'appointment');
    }
}
