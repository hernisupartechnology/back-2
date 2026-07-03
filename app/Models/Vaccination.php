<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vaccination extends Model
{
    protected $fillable = [
        'user_id', 'registered_by', 'vaccine_name', 'dose_number',
        'application_date', 'applied_by', 'lot_number', 'ips_or_center',
        'next_dose_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'next_dose_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'related_id')
            ->where('related_type', 'vaccination');
    }
}
