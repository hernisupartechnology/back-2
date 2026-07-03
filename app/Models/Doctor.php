<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Médico del catálogo del hogar. */
class Doctor extends Model
{
    protected $fillable = [
        'household_id', 'name', 'specialty', 'registration_number',
        'phone', 'email', 'ips', 'address', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function chronicConditions(): HasMany
    {
        return $this->hasMany(ChronicCondition::class, 'treating_doctor_id');
    }
}
