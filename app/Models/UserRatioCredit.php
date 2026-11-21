<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserRatioCredit extends Model
{
    protected $fillable = [
        'user_id',
        'uploaded_credit_bytes',
        'downloaded_credit_bytes',
    ];

    protected $casts = [
        'uploaded_credit_bytes' => 'integer',
        'downloaded_credit_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
