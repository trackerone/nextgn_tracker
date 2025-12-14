<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Torrent extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SOFT_DELETED = 'soft_deleted';

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'slug',
        'info_hash',
        'storage_path',
        'size_bytes',
        'file_count',
        'files_count',
        'type',
        'source',
        'resolution',
        'codecs',
        'tags',
        'description',
        'nfo_text',
        'nfo_storage_path',
        'imdb_id',
        'tmdb_id',
        'seeders',
        'leechers',
        'completed',
        'is_visible',
        'is_approved',
        'is_banned',
        'ban_reason',
        'freeleech',
        'status',
        'moderated_by',
        'moderated_at',
        'moderated_reason',
        'original_filename',
        'uploaded_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'file_count' => 'integer',
        'files_count' => 'integer',
        'seeders' => 'integer',
        'leechers' => 'integer',
        'completed' => 'integer',
        'is_visible' => 'boolean',
        'is_approved' => 'boolean',
        'is_banned' => 'boolean',
        'freeleech' => 'boolean',
        'category_id' => 'integer',
        'codecs' => 'array',
        'tags' => 'array',
        'uploaded_at' => 'datetime',
        'moderated_at' => 'datetime',
    ];

    /**
     * Use slug for implicit route model binding (/torrents/{torrent}).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function isDisplayable(): bool
    {
        return $this->isVisible();
    }

    public function isVisible(): bool
    {
        // “Visible” følger godkendelse + ikke-banned (status/flag afgør isApproved/isBanned)
        return $this->isApproved() && ! $this->isBanned();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        // Vi understøtter både legacy "status" og boolean "is_approved".
        // Hvis schemaet har is_approved, så skal den være true, og status skal være approved.
        $hasIsApprovedColumn = Schema::hasColumn($this->getTable(), 'is_approved');

        if ($hasIsApprovedColumn) {
            return (bool) $this->is_approved && $this->status === self::STATUS_APPROVED;
        }

        // Fallback hvis kolonnen ikke findes (fx ældre schema/test setup)
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isSoftDeleted(): bool
    {
        return $this->status === self::STATUS_SOFT_DELETED;
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned || $this->isSoftDeleted();
    }

    /**
     * Scope for torrents, der må vises offentligt (forsiden/index).
     */
    public function scopeDisplayable(Builder $query): Builder
    {
        return $query->visible();
    }

    /**
     * Basisscope for “synlige” torrents.
     *
     * Konservativt: kun APPROVED torrents, og ikke banned/soft-deleted.
     * Hvis vi har is_approved kolonnen, skal den også være true.
     */
    public function scopeVisible(Builder $query): Builder
    {
        $query = $query
            ->where('status', self::STATUS_APPROVED)
            ->where('is_banned', false);

        if (Schema::hasColumn($this->getTable(), 'is_approved')) {
            $query->where('is_approved', true);
        }

        return $query;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeModerated(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_PENDING);
    }

    public function peers(): HasMany
    {
        return $this->hasMany(Peer::class);
    }

    public function userTorrents(): HasMany
    {
        return $this->hasMany(UserTorrent::class);
    }

    public static function storagePathForHash(string $infoHash): string
    {
        return 'torrents/' . strtoupper($infoHash) . '.torrent';
    }

    public function torrentStoragePath(): string
    {
        return $this->storage_path ?? self::storagePathForHash($this->info_hash);
    }

    public function torrentFilePath(): string
    {
        $disk = (string) config('upload.torrents.disk', 'torrents');

        return Storage::disk($disk)->path($this->torrentStoragePath());
    }

    public function hasTorrentFile(): bool
    {
        $disk = (string) config('upload.torrents.disk', 'torrents');

        return Storage::disk($disk)->exists($this->torrentStoragePath());
    }

    public function nfoStoragePath(): ?string
    {
        return $this->nfo_storage_path;
    }

    public function hasStoredNfo(): bool
    {
        if ($this->nfo_storage_path === null) {
            return false;
        }

        $disk = (string) config('upload.nfo.disk', 'nfo');

        return Storage::disk($disk)->exists($this->nfo_storage_path);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = max(0, (int) ($this->size_bytes ?? 0));
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        $precision = $unitIndex === 0 ? 0 : 2;

        return number_format($bytes, $precision) . ' ' . $units[$unitIndex];
    }

    public function uploadedAtForDisplay(): ?Carbon
    {
        $timestamp = $this->uploaded_at ?? $this->created_at;

        if ($timestamp === null) {
            return null;
        }

        return Carbon::instance($timestamp);
    }
}
