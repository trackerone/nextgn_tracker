<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $torrent_id
 * @property int|null $notification_watch_preset_id
 * @property string $title
 * @property string|null $body
 */
final class TorrentWatchNotification extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'torrent_id',
        'notification_watch_preset_id',
        'title',
        'body',
        'read_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(NotificationWatchPreset::class, 'notification_watch_preset_id');
    }
}
