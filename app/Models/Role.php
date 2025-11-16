<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const DEFAULT_SLUG = 'newbie';

    public const STAFF_LEVEL_THRESHOLD = 8;

    protected $fillable = [
        'slug',
        'name',
        'level',
        'is_staff',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_staff' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
