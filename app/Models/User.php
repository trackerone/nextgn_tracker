<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Tracker\PasskeyService;
use App\Support\Roles\RoleLevel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * @property string|null $role
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
        'is_staff',
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
        'is_staff' => 'boolean',
        'last_announce_at' => 'datetime',
        'announce_rate_limit_exceeded' => 'boolean',
    ];

    protected $attributes = [
        'is_banned' => false,
        'is_disabled' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $attributes = $user->getAttributes();

            // Only set a default role if role was NOT explicitly provided (tests can set role=null).
            if (! array_key_exists('role', $attributes)) {
                $user->forceFill([
                    'role' => self::ROLE_USER,
                ]);
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function sendEmailVerificationNotification(): void
    {
        if (! Route::has('verification.verify')) {
            return;
        }

        $this->notify(new VerifyEmail);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by_id');
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
        return $query->where(function (Builder $builder): void {
            $builder->where('is_staff', true)
                ->orWhereHas('role', function (Builder $roleQuery): void {
                    $roleQuery->where('level', '>=', Role::STAFF_LEVEL_THRESHOLD)
                        ->orWhere('is_staff', true);
                })
                ->orWhereIn('role', self::staffRoles());
        });
    }

    public function isStaff(): bool
    {
        // Explicit staff-flag override (bruges i moderation flows / tests)
        if ((bool) $this->is_staff) {
            return true;
        }

        // 1) Primary: numeric level mapping (covers legacy slugs + normalized roles)
        if ($this->roleLevel() >= Role::STAFF_LEVEL_THRESHOLD) {
            return true;
        }

        // 2) Role relation flag/level if present
        $roleRelation = $this->getRelationValue('role');
        if ($roleRelation instanceof Role) {
            if ((bool) $roleRelation->is_staff) {
                return true;
            }

            if ($roleRelation->level !== && (int) $roleRelation->level >= Role::STAFF_LEVEL_THRESHOLD) {
                return true;
            }
        }

        // 3) Last resort: slug/name match (covers edge cases where level mapping isn't available)
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue !== null && in_array($roleValue, self::staffRoles(), true);
    }

    public function isModerator(): bool
    {
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue === self::ROLE_MODERATOR
            || $roleValue === self::ROLE_ADMIN
            || $roleValue === self::ROLE_SYSOP
            || in_array($roleValue, ['mod1', 'mod2', 'admin1', 'admin2', 'sysop'], true);
    }

    public function isAdmin(): bool
    {
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue === self::ROLE_ADMIN
            || $roleValue === self::ROLE_SYSOP
            || in_array($roleValue, ['admin1', 'admin2', 'sysop'], true);
    }

    public function isSysop(): bool
    {
        $roleValue = $this->resolveRoleIdentifier();

        return $roleValue === self::ROLE_SYSOP || $roleValue === 'sysop';
    }

    public function isLogViewer(): bool
    {
        // Log viewer = admin/sysop (including legacy)
        return $this->isAdmin() || $this->isSysop();
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

        /** @var \App\Services\Settings\RatioSettings $ratioSettings */
        $ratioSettings = app(\App\Services\Settings\RatioSettings::class);

        $ratio = $this->ratio();

        if ($ratio === null) {
            return 'User';
        }

        $totalDownloaded = $this->totalDownloaded();
        $userMinRatio = $ratioSettings->userMinRatio();

        return match (true) {
            $ratio >= $ratioSettings->eliteMinRatio() => 'Elite',

            $ratio >= $ratioSettings->powerUserMinRatio()
                && $totalDownloaded >= $ratioSettings->powerUserMinDownloaded() => 'Power User',

            $ratio >= $userMinRatio => 'User',

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
        return [
            // Normaliserede roller
            self::ROLE_MODERATOR,
            self::ROLE_ADMIN,
            self::ROLE_SYSOP,

            // Legacy slugs
            'mod1',
            'mod2',
            'admin1',
            'admin2',
            'sysop',
        ];
    }

    public function resolveRoleIdentifier(): ?string
    {
        $roleAttribute = $this->getAttribute('role');

        // Hvis role-attributten er sat til noget andet end default, så er den autoritativ.
        if (is_string($roleAttribute) && $roleAttribute !== '' && $roleAttribute !== self::ROLE_USER) {
            return $roleAttribute;
        }

        // Hvis role-attributten er default (typisk 'user'), men vi har en Role relation/role_id,
        // så skal relationen have forrang (bruges i moderation/staff flows).
        $roleRelation = $this->getRelationValue('role');

        if ($roleRelation instanceof Role) {
            if (($roleRelation->slug) && $roleRelation->slug !== '') {
                return $roleRelation->slug;
            }

            return $roleRelation->name;
        }

        // Hvis der ikke er relation, men role-attributten findes (og er default), returnér den.
        if (is_string($roleAttribute) && $roleAttribute !== '') {
            return $roleAttribute;
        }

        return null;
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
