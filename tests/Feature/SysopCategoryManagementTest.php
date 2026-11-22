<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Torrents\TorrentMediaTypes;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SysopCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sysop_can_create_or_manage_categories(): void
    {
        $this->get(route('sysop.categories.index'))->assertRedirect(route('login'));

        foreach ([User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('sysop.categories.index'))->assertForbidden();
            $this->post(route('sysop.categories.store'), $this->categoryPayload())->assertForbidden();
        }

        $this->assertDatabaseMissing('categories', ['slug' => 'audiobooks']);
    }

    public function test_books_category_gets_the_book_type_and_seed_preserves_owner_changes(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'media_type'));
        $this->assertTrue(Schema::hasColumn('categories', 'is_active'));

        $this->seed(CategorySeeder::class);
        $books = Category::query()->where('slug', 'books')->firstOrFail();
        $this->assertSame('book', $books->media_type);
        $this->assertContains('book', TorrentMediaTypes::VALUES);

        $books->update(['name' => 'Ebooks', 'position' => 99, 'is_active' => false]);
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseHas('categories', [
            'id' => $books->id,
            'slug' => 'books',
            'name' => 'Ebooks',
            'position' => 99,
            'is_active' => false,
            'media_type' => 'book',
        ]);
    }

    public function test_sysop_can_create_rename_reorder_and_archive_without_deleting_existing_torrents(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->post(route('sysop.categories.store'), $this->categoryPayload())
            ->assertRedirect(route('sysop.categories.index'));

        $category = Category::query()->where('slug', 'audiobooks')->firstOrFail();
        $this->assertSame('book', $category->media_type);
        $this->assertTrue($category->is_active);

        $torrent = Torrent::factory()->create([
            'category_id' => $category->id,
            'type' => 'book',
            'name' => 'My Audiobook Release',
        ]);

        $this->patch(route('sysop.categories.update', $category), [
            'name' => 'Audio Books',
            'media_type' => 'book',
            'position' => 1,
            'is_active' => 0,
            'audit_reason' => 'Archive new uploads while retaining existing releases.',
        ])->assertRedirect(route('sysop.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'audiobooks',
            'name' => 'Audio Books',
            'position' => 1,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('torrents', [
            'id' => $torrent->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs(User::factory()->create())->get(route('torrents.index', [
            'category' => $category->id,
            'type' => 'book',
            'grouped' => '0',
        ]))->assertOk()->assertSee('My Audiobook Release');

        $audit = AuditLog::query()->where('action', 'sysop.categories.updated')->firstOrFail();
        $this->assertSame($sysop->id, $audit->user_id);
        $this->assertSame('Archive new uploads while retaining existing releases.', $audit->metadata['audit_reason']);
        $this->assertSame(true, $audit->metadata['before']['is_active']);
        $this->assertSame(false, $audit->metadata['after']['is_active']);
    }

    public function test_upload_dropdown_only_offers_active_categories_and_books_type_is_available(): void
    {
        $this->seed(CategorySeeder::class);
        $books = Category::query()->where('slug', 'books')->firstOrFail();
        $hidden = Category::query()->where('slug', 'games')->firstOrFail();
        $hidden->update(['is_active' => false]);

        $uploader = User::factory()->create(['role' => User::ROLE_UPLOADER]);
        $this->actingAs($uploader)->get(route('torrents.upload'))
            ->assertOk()
            ->assertSee('data-media-type="book"', false)
            ->assertSee('<option value="book"', false)
            ->assertDontSee('data-media-type="game"', false);

        $this->post(route('torrents.store'), [
            'name' => 'Incorrect Book Type',
            'type' => 'movie',
            'category_id' => $books->id,
            'torrent_file' => $this->torrentFile(),
        ])->assertSessionHasErrors('type');

        $this->post(route('torrents.store'), [
            'name' => 'Archived Category Release',
            'type' => 'game',
            'category_id' => $hidden->id,
            'torrent_file' => $this->torrentFile(),
        ])->assertSessionHasErrors('category_id');
    }

    public function test_book_media_type_filters_api_browse_and_rss_consistently(): void
    {
        $this->seed(CategorySeeder::class);
        $books = Category::query()->where('slug', 'books')->firstOrFail();
        $member = User::factory()->create();

        $book = Torrent::factory()->create([
            'category_id' => $books->id,
            'type' => 'book',
            'name' => 'Canonical Book Release',
        ]);
        Torrent::factory()->create([
            'type' => 'movie',
            'name' => 'Unrelated Movie Release',
        ]);

        $member->rotateRssToken();
        $this->actingAs($member)->getJson(route('api.torrents.index', [
            'type' => 'book',
        ]))->assertOk()
            ->assertSee($book->name)
            ->assertDontSee('Unrelated Movie Release');

        $this->get(route('rss.feed', [
            'token' => $member->rss_token,
            'type' => 'book',
        ]))->assertOk()
            ->assertSee($book->name)
            ->assertDontSee('Unrelated Movie Release');
    }

    public function test_media_type_cannot_change_for_category_with_existing_torrents(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $category = Category::factory()->create(['media_type' => 'music']);
        Torrent::factory()->create(['category_id' => $category->id, 'type' => 'music']);

        $this->actingAs($sysop)->patch(route('sysop.categories.update', $category), [
            'name' => $category->name,
            'media_type' => 'movie',
            'position' => $category->position,
            'is_active' => true,
            'audit_reason' => 'Attempt to reclassify used category.',
        ])->assertSessionHasErrors('media_type');

        $this->assertSame('music', $category->fresh()->media_type);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sysop.categories.updated']);
    }

    public function test_invalid_or_unaudited_changes_do_not_mutate_categories(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop);

        foreach ([
            ['media_type' => 'anything'],
            ['position' => -1],
            ['audit_reason' => '   '],
        ] as $invalid) {
            $this->post(route('sysop.categories.store'), array_replace($this->categoryPayload(), $invalid))
                ->assertSessionHasErrors();
        }

        $this->post(route('sysop.categories.store'), $this->categoryPayload())
            ->assertRedirect(route('sysop.categories.index'));
        $this->post(route('sysop.categories.store'), $this->categoryPayload())
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('categories', 1);
        $this->assertSame(1, AuditLog::query()->where('action', 'sysop.categories.created')->count());
    }

    private function categoryPayload(): array
    {
        return [
            'name' => 'Audiobooks',
            'media_type' => 'book',
            'position' => 12,
            'audit_reason' => 'Create a separate audiobook category.',
        ];
    }

    private function torrentFile(): UploadedFile
    {
        $payload = app(\App\Services\BencodeService::class)->encode([
            'announce' => 'https://tracker.invalid/announce',
            'info' => ['name' => 'A.2026.ebook', 'length' => 2000, 'piece length' => 16384, 'pieces' => str_repeat('a', 20)],
        ]);

        return UploadedFile::fake()->createWithContent('a.torrent', $payload, 'application/x-bittorrent');
    }
}
