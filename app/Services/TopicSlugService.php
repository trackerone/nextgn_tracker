<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Topic;
use Illuminate\Support\Str;

class TopicSlugService
{
    public function generate(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'topic';
        }

        $slug = $base;
        $suffix = 1;

        while (Topic::query()->where('slug', $slug)->exists()) {
            $slug = sprintf('%s-%d', $base, $suffix);
            ++$suffix;
        }

        return $slug;
    }
}
