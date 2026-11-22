<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Logging\AuditLogger;
use App\Support\Torrents\TorrentMediaTypes;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CategorySettingsController extends Controller
{
    public function index(): View
    {
        return view('sysop.categories.index', [
            'categories' => Category::query()
                ->withCount('torrents')
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
            'types' => TorrentMediaTypes::LABELS,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')],
            'media_type' => ['required', Rule::in(TorrentMediaTypes::VALUES)],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);
        $reason = $this->reason($validated);
        $name = trim((string) $validated['name']);
        $slug = Str::slug($name);
        if ($slug === '' || Category::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Choose a unique category name.']);
        }

        DB::transaction(function () use ($validated, $audit, $name, $slug, $reason): void {
            $category = Category::query()->create([
                'name' => $name,
                'slug' => $slug,
                'media_type' => $validated['media_type'],
                'position' => (int) $validated['position'],
                'is_active' => true,
            ]);

            $audit->log('sysop.categories.created', $category, [
                'after' => $category->only(['name', 'slug', 'media_type', 'position', 'is_active']),
                'audit_reason' => $reason,
            ]);
        });

        return redirect()->route('sysop.categories.index')->with('status', 'Category created.');
    }

    public function update(Request $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($category->getKey())],
            'media_type' => ['required', Rule::in(TorrentMediaTypes::VALUES)],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['required', 'boolean'],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);
        $reason = $this->reason($validated);
        $name = trim((string) $validated['name']);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'A category name is required.']);
        }

        // Changing an in-use category's media family would make old uploads
        // inconsistent with their stored torrent.type. Rename/reorder/archive
        // remain available, without removing torrents from the catalog.
        if (
            $category->media_type !== $validated['media_type']
            && $category->torrents()->exists()
        ) {
            throw ValidationException::withMessages([
                'media_type' => 'This category already contains torrents. Create a new category for another media type.',
            ]);
        }

        $before = $category->only(['name', 'slug', 'media_type', 'position', 'is_active']);
        DB::transaction(function () use ($category, $validated, $audit, $name, $before, $reason): void {
            $category->update([
                'name' => $name,
                'media_type' => $validated['media_type'],
                'position' => (int) $validated['position'],
                'is_active' => (bool) $validated['is_active'],
            ]);

            $audit->log('sysop.categories.updated', $category, [
                'before' => $before,
                'after' => $category->only(['name', 'slug', 'media_type', 'position', 'is_active']),
                'audit_reason' => $reason,
            ]);
        });

        return redirect()->route('sysop.categories.index')->with('status', 'Category updated.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function reason(array $validated): string
    {
        $reason = trim((string) $validated['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'An audit reason is required.']);
        }

        return $reason;
    }
}
