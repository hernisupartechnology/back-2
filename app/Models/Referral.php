<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'appointment_id', 'household_id', 'user_id', 'registered_by',
        'specialty', 'referring_doctor_id', 'referred_doctor_id',
        'reason', 'urgency', 'status', 'denied_reason',
        'authorization_date', 'authorization_number', 'authorization_expiry_date',
        'scheduled_appointment_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'authorization_date' => 'date',
            'authorization_expiry_date' => 'date',
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

    public function referringDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'referring_doctor_id');
    }

    public function referredDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'referred_doctor_id');
    }

    public function scheduledAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'scheduled_appointment_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'related_id')
            ->where('related_type', 'referral');
    }

    /** Días restantes de vigencia de la autorización. */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (! $this->authorization_expiry_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->authorization_expiry_date, false);
    }
}
