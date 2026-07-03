<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Suscripción Web Push de un navegador/dispositivo del usuario. */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id', 'endpoint', 'p256dh', 'auth',
        'device_label', 'is_active', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
