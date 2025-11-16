<?php

declare(strict_types=1);

namespace App\Models;

<<<<<< codex/harden-file-upload-surface-in-nextgn-tracker
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'label',
        'last_used_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_used_at' => 'datetime',
    ];
=======
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['user_id', 'key', 'label', 'last_used_at'];

    protected $casts = ['last_used_at' => 'datetime'];
>>>>>> main

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateKey(): string
    {
<<<<<< codex/harden-file-upload-surface-in-nextgn-tracker
        return bin2hex(random_bytes(32));
=======
        return Str::random(80);
>>>>>> main
    }
}
