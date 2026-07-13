<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo de usuario — soporte de roles (owner, member, viewer) y perfil médico.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email null en perfiles gestionados (ver $is_managed)
 * @property string $role owner | member | viewer
 * @property int|null $household_id
 * @property bool $is_minor
 * @property bool $is_managed perfil creado por el owner, sin login propio (niños, adultos mayores, etc.)
 * @property int|null $supervised_by
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name', 'email', 'password', 'avatar',
        'role', 'household_id', 'is_managed',
        'phone', 'birthdate', 'gender',
        'blood_type', 'eps', 'ips_preferida', 'numero_afiliado',
        'is_minor', 'supervised_by',
        'track_vital_signs', 'dark_mode',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'is_minor' => 'boolean',
            'is_managed' => 'boolean',
            'track_vital_signs' => 'boolean',
            'dark_mode' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Relaciones
    // ──────────────────────────────────────────────────────────────

    /** Hogar al que pertenece el usuario. */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** Supervisor (padre/tutor) de este usuario si es menor. */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervised_by');
    }

    /** Menores de edad que este usuario supervisa. */
    public function supervisedMembers(): HasMany
    {
        return $this->hasMany(User::class, 'supervised_by');
    }

    /** Citas como paciente. */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Medicamentos. */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    /** Exámenes. */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /** Remisiones. */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /** Incapacidades. */
    public function medicalLeaves(): HasMany
    {
        return $this->hasMany(MedicalLeave::class);
    }

    /** Vacunas. */
    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    /** Signos vitales. */
    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    /** Alergias activas. */
    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class);
    }

    /** Condiciones crónicas. */
    public function chronicConditions(): HasMany
    {
        return $this->hasMany(ChronicCondition::class);
    }

    /** Documentos médicos. */
    public function medicalDocuments(): HasMany
    {
        return $this->hasMany(MedicalDocument::class);
    }

    /** Notificaciones in-app. */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** Suscripciones push (dispositivos). */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** Rangos personalizados de signos vitales. */
    public function vitalSignRange(): HasOne
    {
        return $this->hasOne(VitalSignRange::class);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers de rol
    // ──────────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    /** ¿Puede este usuario gestionar al miembro dado? */
    public function canManage(User $target): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if ($this->isMember()) {
            // Un miembro puede gestionar sus propios viewers
            return $target->supervised_by === $this->id || $target->id === $this->id;
        }

        // viewer solo puede ver su propio perfil
        return $target->id === $this->id;
    }
}
