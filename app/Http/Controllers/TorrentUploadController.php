<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\SubmitTorrentUploadAction;
use App\Actions\Torrents\SubmitTorrentUploadResult;
use App\Http\Requests\Web\TorrentUploadStoreRequest;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Category;
use App\Models\Torrent;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\UploadPreflightContextBuilderContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class TorrentUploadController extends Controller
{
    public function __construct(
        private readonly SubmitTorrentUploadAction $submitTorrentUpload,
        private readonly UploadEligibilityService $uploadEligibility,
        private readonly UploadPreflightContextBuilderContract $preflightContextBuilder,
    ) {}

    public function create(): View
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $context = $this->preflightContextBuilder->forUser($user);
        $decision = $this->uploadEligibility->evaluate($user, $context);
        abort_unless($decision->allowed, 403);

        $categories = Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('torrents.upload', [
            'categories' => $categories,
            'releaseAdvice' => is_array($context->releaseAdvice) ? $context->releaseAdvice : null,
        ]);
    }

    public function store(TorrentUploadStoreRequest $request): RedirectResponse
    {
        $torrentFile = $request->file('torrent_file');

        if (($torrentFile instanceof UploadedFile) === false) {
            throw ValidationException::withMessages([
                'torrent_file' => 'A valid .torrent file is required.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $nfoFile = $request->file('nfo_file');
        $result = $this->submitTorrentUpload->execute(
            $user,
            $torrentFile,
            $request->validated(),
            $nfoFile instanceof UploadedFile ? $nfoFile : null,
        );

        if ($result->isDuplicate()) {
            if ($result->duplicateTorrent instanceof Torrent) {
                return $this->redirectToExistingTorrent($result->duplicateTorrent);
            }

            throw ValidationException::withMessages([
                'torrent_file' => 'Torrent already exists.',
            ]);
        }

        if ($result->isDenied()) {
            return $this->handleDeniedUploadResult($result);
        }

        if (! $result->torrent instanceof Torrent) {
            abort(403);
        }

        return $this->successfulUploadResponse($result->torrent);
    }

    private function handleDeniedUploadResult(SubmitTorrentUploadResult $result): RedirectResponse
    {
        $decision = $result->deniedDecision;

        if ($decision?->reason === UploadEligibilityReason::MissingMetadata) {
            throw ValidationException::withMessages([
                'torrent_file' => 'Invalid torrent payload: missing required metadata.',
            ]);
        }

        abort(403);
    }

    private function redirectToExistingTorrent(Torrent $torrent): RedirectResponse
    {
        return redirect()
            ->route('torrents.show', $torrent->slug)
            ->with('status', 'Torrent already exists – redirected to the existing entry.');
    }

    private function successfulUploadResponse(Torrent $torrent): RedirectResponse
    {
        $uploadMetadata = TorrentMetadataView::forTorrent(
            $torrent->loadMissing('metadata')
        );

        return redirect()
            ->route('torrents.show', $torrent->slug)
            ->with('status', 'Torrent uploaded and awaiting approval.')
            ->with('upload_metadata', $uploadMetadata);
    }
}
