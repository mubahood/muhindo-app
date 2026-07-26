<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property string $password
 * @property string|null $role
 * @property bool $is_active
 * @property bool $is_admin
 * @property bool $password_change_required
 */
class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, HasRoles, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role',
        'phone', 'bio', 'avatar', 'is_active', 'is_admin', 'theme', 'last_active_at',
        'password_change_required',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'last_active_at' => 'datetime',
            'password_change_required' => 'boolean',
        ];
    }

    // ── Roles ──────────────────────────────────────────────────

    /** Every account type on the platform (mirrors Spatie role names & RbacSeeder). */
    public const ROLES = ['super_admin', 'admin', 'student', 'client'];

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin'], true) || $this->is_admin === true;
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function canAccessAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'super_admin' => 'Owner',
            'admin' => 'Admin',
            'student' => 'Student',
            'client' => 'Client',
            default => ucfirst($this->role ?? 'User'),
        };
    }

    // ── Domain relations ──────────────────────────────────────────

    /**
     * A `client`-role user's client profile (project billing/portal access).
     *
     * @return HasOne<Client, $this>
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /**
     * Courses this user (as a student) is enrolled in.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<DeviceToken, $this> */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    // ── Avatar helper ──────────────────────────────────────────

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            return asset('storage/'.$this->avatar);
        }

        return null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', trim($this->name ?? 'U'));
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $initials .= strtoupper(substr(end($parts), 0, 1));
        }

        return $initials;
    }

    // ── Password reset ─────────────────────────────────────────

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ── Spatie role sync ───────────────────────────────────────

    protected static function booted(): void
    {
        // Keep the Spatie role in sync with the `role` column so permission
        // checks work everywhere without a separate workflow.
        static::saved(function (User $user) {
            if ($user->wasChanged('role') || $user->wasRecentlyCreated) {
                $user->syncSpatieRole();
            }
        });
    }

    /** Mirror the role column into Spatie roles (no-op if unset or roles aren't seeded). */
    public function syncSpatieRole(): void
    {
        if ($this->role !== null && \Spatie\Permission\Models\Role::where('name', $this->role)->where('guard_name', 'web')->exists()) {
            $this->syncRoles([$this->role]);
        }
    }
}
