<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Roles\RoleLevel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'passkey',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'passkey',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->role_id !== null) {
                return;
            }

            $roleId = Role::query()
                ->where('slug', Role::DEFAULT_SLUG)
                ->value('id');

            if ($roleId !== null) {
                $user->role_id = $roleId;
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function userTorrents(): HasMany
    {
        return $this->hasMany(UserTorrent::class);
    }

    public function snatches(): HasMany
    {
        return $this->userTorrents();
    }

    public function isStaff(): bool
    {
        return (bool) ($this->role?->is_staff);
    }

    public function roleLevel(): int
    {
        return RoleLevel::levelForUser($this);
    }

    public function hasLevelAtLeast(int $minimumLevel): bool
    {
        return $this->roleLevel() >= $minimumLevel;
    }

    public function ensurePasskey(): string
    {
        if (! $this->passkey) {
            $this->passkey = bin2hex(random_bytes(16));
            $this->save();
        }

        return (string) $this->passkey;
    }

    public function getAnnounceUrlAttribute(): string
    {
        $baseUrl = rtrim((string) config('tracker.announce_url', '/announce'), '/');

        return $baseUrl.'/'.$this->ensurePasskey();
    }
}
