<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Tracker\PasskeyService;
use App\Support\Roles\RoleLevel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property string $email
 * @property string $name
 * @property string $passkey
 * @property bool $is_banned
 * @property bool $is_disabled
 * @property bool $announce_rate_limit_exceeded
 * @property \Carbon\Carbon|null $last_announce_at
 * @property \App\Models\Role|null $role
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use MustVerifyEmailTrait;
    use Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_POWER_USER = 'power_user';

    public const ROLE_UPLOADER = 'uploader';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SYSOP = 'sysop';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_id',
        'invited_by_id',
        'passkey',
        'is_banned',
        'is_disabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'passkey',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_banned' => 'boolean',
        'is_disabled' => 'boolean',
        'last_announce_at' => 'datetime',
        'announce_rate_limit_exceeded' => 'boolean',
    ];

    protected $attributes = [
        'role' => self::ROLE_USER,
        'is_banned' => false,
        'is_disabled' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $roleValue = $user->getAttribute('role');

            if (! is_string($roleValue) || $roleValue === '') {
                $user->forceFill(['role' => self::ROLE_USER]);
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function userTorrents(): HasMany
    {
        return $this->hasMany(UserTorrent::class);
    }

    public function sentInvites(): HasMany
    {
        return $this->hasMany(Invite::class, 'inviter_user_id');
    }

    public function snatches(): HasMany
    {
        return $this->userTorrents();
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', self::staffRoles());
    }

    public function isStaff(): bool
    {
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue !== null && in_array($roleValue, self::staffRoles(), true);
    }

    public function isModerator(): bool
    {
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue === self::ROLE_MODERATOR || $roleValue === self::ROLE_ADMIN || $roleValue === self::ROLE_SYSOP;
    }

    public function isAdmin(): bool
    {
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue === self::ROLE_ADMIN || $roleValue === self::ROLE_SYSOP;
    }

    public function isSysop(): bool
    {
        return $this->resolveRoleIdentifier() === self::ROLE_SYSOP;
    }

    public function isLogViewer(): bool
    {
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue === self::ROLE_ADMIN || $roleValue === self::ROLE_SYSOP;
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    public function isDisabled(): bool
    {
        return (bool) $this->is_disabled;
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
        if ($this->passkey) {
            return (string) $this->passkey;
        }

        return app(PasskeyService::class)->generate($this);
    }

    public function getAnnounceUrlAttribute(): string
    {
        $announceConfig = (string) config('tracker.announce_url', '/announce/%s');
        $passkey = $this->ensurePasskey();

        if (str_contains($announceConfig, '%s')) {
            return sprintf($announceConfig, $passkey);
        }

        $baseUrl = rtrim($announceConfig, '/');

        return $baseUrl.'/'.$passkey;
    }

    public function totalUploaded(): int
    {
        return (int) $this->userTorrents()->sum('uploaded');
    }

    public function totalDownloaded(): int
    {
        return (int) $this->userTorrents()->sum('downloaded');
    }

    public function ratio(): ?float
    {
        $downloaded = $this->totalDownloaded();

        if ($downloaded === 0) {
            return null;
        }

        return $this->totalUploaded() / $downloaded;
    }

    public function userClass(): string
    {
        if ($this->isStaff()) {
            return 'Staff';
        }

        if ($this->isDisabled()) {
            return 'Disabled';
        }

        $ratio = $this->ratio();

        if ($ratio === null) {
            return 'User';
        }

        return match (true) {
            $ratio >= 1.5 => 'Elite',
            $ratio >= 0.8 => 'Power User',
            $ratio >= 0.4 => 'User',
            default => 'Leech',
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return Str::of($this->resolveRoleIdentifier() ?? self::ROLE_USER)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * @return array<int, string>
     */
    private static function staffRoles(): array
    {
        return [self::ROLE_MODERATOR, self::ROLE_ADMIN, self::ROLE_SYSOP];
    }

    public function resolveRoleIdentifier(): ?string
    {
        $roleRelation = $this->getRelationValue('role');

        if ($roleRelation instanceof Role) {
            if (is_string($roleRelation->slug) && $roleRelation->slug !== '') {
                return $roleRelation->slug;
            }

            return is_string($roleRelation->name) ? $roleRelation->name : null;
        }

        $roleAttribute = $this->getAttribute('role');

        return is_string($roleAttribute) ? $roleAttribute : null;
    }

    public static function roleFromLegacySlug(?string $slug): string
    {
        return match ($slug) {
            'sysop' => self::ROLE_SYSOP,
            'admin2', 'admin1' => self::ROLE_ADMIN,
            'mod2', 'mod1' => self::ROLE_MODERATOR,
            'uploader3', 'uploader2', 'uploader1' => self::ROLE_UPLOADER,
            'user4', 'user3', 'user2' => self::ROLE_POWER_USER,
            'user1', 'newbie' => self::ROLE_USER,
            default => self::ROLE_USER,
        };
    }
}
