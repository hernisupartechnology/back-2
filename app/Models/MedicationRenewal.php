<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationRenewal extends Model
{
    protected $fillable = [
        'medication_id', 'renewal_number', 'period_start', 'period_end',
        'status', 'authorization_date', 'authorization_number',
        'claimed_date', 'alert_sent_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'authorization_date' => 'date',
            'claimed_date' => 'date',
            'alert_sent_at' => 'datetime',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
