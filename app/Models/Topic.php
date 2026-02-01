<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'is_locked',
        'is_pinned',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'is_pinned' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
public function createForTopic(Topic $topic, array $attributes): Post
    {
    /** @var Post $post */
    $post = $topic->posts()->create($attributes);

    /** @var Post $fresh */
    $fresh = $post->fresh(['author.role']);

    return $fresh;
    }
}
