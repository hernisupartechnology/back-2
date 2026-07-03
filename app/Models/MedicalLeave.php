<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Incapacidad médica — total_days calculado por MedicalLeaveObserver. */
class MedicalLeave extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'household_id', 'user_id', 'registered_by', 'appointment_id',
        'issuing_doctor_id', 'issuing_doctor_name_free',
        'start_date', 'end_date', 'total_days',
        'diagnosis', 'diagnosis_code', 'leave_type', 'ips_issued', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function issuingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'issuing_doctor_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'related_id')
            ->where('related_type', 'medical_leave');
    }
}
