<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\ResolveTorrentAccessAction;
use App\Http\Requests\BrowseTorrentsRequest;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\BencodeService;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\UploadReleaseAdvisor;
use App\Services\Tracker\DownloadEligibilityPolicy;
use App\Support\Torrents\TorrentBrowseMetadataFilterOptions;
use App\Support\Torrents\TorrentBrowseQuery;
use App\Support\Torrents\TorrentBrowseRowPresenter;
use App\Support\Torrents\TorrentMetadataQuality;
use App\Support\Torrents\TorrentModerationMetadataReview;
use App\Support\Torrents\TorrentPayloadFiles;
use App\Support\Torrents\TorrentReleaseFamilyGrouper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;

final class TorrentController extends Controller
{
    public function __construct()
    {
        // Matcher tests:
        // - guests -> /login on both index and show
        $this->middleware('auth');
    }

    public function index(
        BrowseTorrentsRequest $request,
        TorrentBrowseQuery $browseQuery,
        TorrentBrowseMetadataFilterOptions $metadataFilterOptions,
        TorrentReleaseFamilyGrouper $releaseFamilyGrouper,
        TorrentBrowseRowPresenter $rowPresenter
    ): Response|JsonResponse {
        $filters = $request->filters();
        $query = $browseQuery->apply(
            Torrent::query()->visible(),
            $filters
        );

        if ($request->expectsJson()) {
            return response()->json($query->with('metadata')->get());
        }

        $query
            ->select([
                'id',
                'name',
                'slug',
                'size_bytes',
                'category_id',
                'codecs',
                'file_count',
                'files_count',
                'type',
                'source',
                'resolution',
                'imdb_id',
                'tmdb_id',
                'nfo_text',
                'seeders',
                'leechers',
                'completed',
                'is_freeleech',
                'created_at',
                'uploaded_at',
            ])
            ->with(['category', 'metadata:id,torrent_id,title,year,type,resolution,source,release_group,language,audio_language,subtitle_language,subtitles,imdb_id,tmdb_id']);

        $perPage = (int) config('torrents.per_page', 25);
        $groupedBrowse = $request->grouped();
        $torrents = $query->paginate($perPage)->appends(array_merge(
            $filters->queryParams(),
            ['grouped' => $groupedBrowse ? '1' : '0']
        ));
        /** @var Collection<int, Torrent> $torrentCollection */
        $torrentCollection = $torrents->getCollection();
        $torrentMetadata = TorrentMetadataView::mapByTorrentId($torrentCollection, includeCodecs: true);
        $torrentMetadataQuality = TorrentMetadataQuality::mapByTorrentId($torrentCollection, $torrentMetadata);
        $torrentBrowseRows = $rowPresenter->map($torrentCollection, $torrentMetadata, $torrentMetadataQuality);
        $releaseFamilies = $groupedBrowse
            ? $releaseFamilyGrouper->group($torrentCollection, $torrentMetadata)
            : [];

        $metadataFilterValues = $metadataFilterOptions->forVisibleBrowse();

        $categories = Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->view('torrents.index', [
            'torrents' => $torrents,
            'torrentMetadata' => $torrentMetadata,
            'torrentMetadataQuality' => $torrentMetadataQuality,
            'torrentBrowseRows' => $torrentBrowseRows,
            'types' => $metadataFilterValues['types'],
            'resolutions' => $metadataFilterValues['resolutions'],
            'sources' => $metadataFilterValues['sources'],
            'categories' => $categories,
            'groupedBrowse' => $groupedBrowse,
            'canDownload' => PermissionService::allow($request->user(), 'torrent.download'),
            'canRss' => PermissionService::allow($request->user(), 'rss.access'),
            'canWatch' => PermissionService::allow($request->user(), 'watch.access'),
            'releaseFamilies' => $releaseFamilies,

            // View-friendly (and test-neutral)
            'filters' => array_merge($filters->toArray(), ['grouped' => $groupedBrowse ? '1' : '0']),
            'q' => $filters->q,
            'type' => $filters->type,
            'order' => $filters->order !== '' ? $filters->order : 'uploaded_at',
            'direction' => $filters->direction,
        ]);
    }

    public function show(Request $request, string $torrent): Response|JsonResponse
    {
        $model = app(ResolveTorrentAccessAction::class)->execute(
            $torrent,
            'view',
            ['category', 'uploader', 'moderator', 'metadata']
        );

        if ($request->expectsJson()) {
            return response()->json($model);
        }

        $model->loadMissing('externalMetadata');
        $metadataRecordExists = $model->getRelationValue('metadata') !== null;
        $metadata = TorrentMetadataView::forTorrent($model, includeCodecs: true);
        $quality = TorrentMetadataQuality::evaluate($metadata, $model->name);
        $metadataReview = TorrentModerationMetadataReview::fromQuality($quality);
        $metadataView = TorrentMetadataView::fromTorrent($model);
        $metadataEnrichmentOutcome = $metadataView->enrichmentOutcome();
        $metadataConfig = app(ExternalMetadataConfig::class);
        $type = $metadata['type'] ?? null;
        $mediaType = ExternalMetadataConfig::mediaType(is_string($type) ? $type : null, $model->category?->slug);
        $sourceOptions = ExternalMetadataConfig::SOURCE_OPTIONS[$mediaType ?? ''] ?? [];
        $metadataSource = $metadataConfig->sourceFor($mediaType, (int) $model->id);
        $externalDetailsEnabled = $metadataSource !== 'none';

        $user = $request->user();
        $eligibility = $user instanceof User && PermissionService::allow($user, 'torrent.download')
            ? app(DownloadEligibilityPolicy::class)->check($user, $model)
            : ['allowed' => false, 'reason' => 'permission_denied'];

        $eligibilityMessage = match ($eligibility['reason']) {
            'freeleech' => 'Download allowed: freeleech bypass is active for this torrent.',
            'no_history' => 'Download allowed: no-history grace applies to your account.',
            'ratio_too_low' => 'Download denied: your ratio is below the required threshold.',
            'permission_denied' => 'Download is unavailable for your account.',
            default => 'Download allowed: you can download this torrent.',
        };
        $isFreeleech = (bool) $model->is_freeleech;
        $eligibilityTone = $eligibility['allowed'] ? 'success' : 'danger';
        $eligibilityTitle = $eligibility['allowed']
            ? 'You can download this torrent'
            : 'Download is currently blocked';
        $freeleechMessage = $isFreeleech
            ? (($eligibility['reason'] ?? '') === 'freeleech'
                ? 'Freeleech is active and currently bypasses ratio requirements.'
                : 'Freeleech is active for this torrent.')
            : 'Freeleech is not active for this torrent.';
        $ratioMessage = match ($eligibility['reason'] ?? '') {
            'permission_denied' => 'This download is restricted by account permissions, not your ratio.',
            'ratio_too_low' => 'Your current ratio does not meet the required threshold.',
            default => 'Your ratio is not blocking this download.',
        };

        $releaseAdvice = app(UploadReleaseAdvisor::class)->advise(
            CanonicalTorrentMetadata::fromArray($metadata)
        );

        $descriptionText = (string) ($model->description ?? '');
        $descriptionHtml = nl2br(e($descriptionText));

        $nfoText = (string) ($metadata['nfo'] ?? '');
        $hasDisplayableMetadata = collect([
            $metadata['year'] ?? null,
            $metadata['type'] ?? null,
            $metadata['resolution'] ?? null,
            $metadata['source'] ?? null,
            $metadata['release_group'] ?? null,
            $metadata['language'] ?? null,
            $metadata['audio_language'] ?? null,
            $metadata['subtitle_language'] ?? null,
            $metadata['subtitles'] ?? null,
            $metadata['imdb_id'] ?? null,
            $metadata['tmdb_id'] ?? null,
            $metadata['nfo'] ?? null,
            $metadata['parsed_name'] ?? null,
        ])->contains(static function (mixed $value): bool {
            if (is_int($value)) {
                return true;
            }

            return is_string($value) && trim($value) !== '';
        });

        return response()->view('torrents.show', [
            'torrent' => $model,
            'metadata' => $metadata,
            'descriptionHtml' => $descriptionHtml,
            'contentFiles' => $this->contentFiles($model),
            'film' => $externalDetailsEnabled ? $metadataView->filmDetails() : null,
            'metadataSourceOptions' => $sourceOptions,
            'metadataSourceOverride' => $metadataConfig->torrentSourceOverride((int) $model->id, $mediaType),
            'rottenTomatoes' => ($metadata['type'] ?? null) === 'movie'
                ? $metadataConfig->rottenTomatoesRatings($metadata['imdb_id'] ?? null)
                : null,
            'tvmazeDetails' => $metadataSource === 'tvmaze' ? $metadataView->tvmazeDetails() : null,
            'catalogDetails' => $externalDetailsEnabled ? $metadataView->catalogDetails() : null,
            'nfoText' => $nfoText,
            'eligibility' => $eligibility,
            'eligibilityMessage' => $eligibilityMessage,
            'eligibilityTitle' => $eligibilityTitle,
            'eligibilityTone' => $eligibilityTone,
            'freeleechMessage' => $freeleechMessage,
            'ratioMessage' => $ratioMessage,
            'releaseAdvice' => $releaseAdvice,
            'metadataQuality' => $quality,
            'metadataReview' => $metadataReview,
            'metadataEnrichmentOutcome' => $metadataEnrichmentOutcome,
            'hasMetadataRecord' => $metadataRecordExists,
            'hasDisplayableMetadata' => $hasDisplayableMetadata,
        ]);
    }

    /** @return list<array{path: string, size: int}> */
    private function contentFiles(Torrent $torrent): array
    {
        try {
            $payload = Storage::disk((string) config('upload.torrents.disk', 'torrents'))->get($torrent->torrentStoragePath());
            if ($payload === null) {
                return [];
            }

            $decoded = app(BencodeService::class)->decode($payload);
            $info = is_array($decoded) ? ($decoded['info'] ?? null) : null;

            return is_array($info) ? TorrentPayloadFiles::listing($info) : [];
        } catch (InvalidArgumentException|FilesystemException) {
            return [];
        }
    }
}
