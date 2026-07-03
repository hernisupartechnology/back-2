<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChronicCondition extends Model
{
    protected $fillable = [
        'user_id', 'name', 'diagnosed_date',
        'treating_doctor_id', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'diagnosed_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function treatingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'treating_doctor_id');
    }
}
