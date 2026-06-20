<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlphaFeedback extends Model
{
    use HasFactory;

    public const SEVERITIES = ['blocker', 'must_fix', 'non_blocking'];

    public const AREAS = [
        'auth_session',
        'browse',
        'torrent_detail',
        'download_magnet',
        'upload',
        'my_uploads',
        'staff_moderation',
        'rss_watch',
        'operations',
        'mobile',
        'other',
    ];

    public const STATUSES = ['open', 'reviewing', 'fixed', 'deferred', 'not_reproducible'];

    protected $table = 'alpha_feedback';

    protected $fillable = [
        'user_id',
        'status_updated_by',
        'area',
        'severity',
        'role',
        'environment',
        'title',
        'steps_to_reproduce',
        'expected_result',
        'actual_result',
        'url_or_context',
        'blocks_alpha',
        'status',
        'status_updated_at',
    ];

    protected $casts = [
        'blocks_alpha' => 'boolean',
        'status_updated_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function statusUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }
}
