<?php
    // Test-/view-sikkerhed: disse kan mangle i visse flows.
    $torrent = $torrent ?? null;
    $descriptionHtml = $descriptionHtml ?? '';
    $nfoText = $nfoText ?? '';
    $nfoHtml = $nfoHtml ?? '';
?>



<?php $__env->startSection('title', $torrent->name.' — Torrent details'); ?>

<?php $__env->startSection('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <?php
        $meta = [
            ['label' => 'Type', 'value' => ucfirst($torrent->type)],
            ['label' => 'Category', 'value' => $torrent->category?->name ?? 'Uncategorized'],
            ['label' => 'Size', 'value' => $torrent->formatted_size],
            ['label' => 'Files', 'value' => number_format($torrent->file_count)],
            ['label' => 'Resolution', 'value' => $torrent->resolution ?? 'Unknown'],
            ['label' => 'Codecs', 'value' => collect($torrent->codecs ?? [])->filter()->implode(' / ') ?: 'n/a'],
        ];
        $stats = [
            ['label' => 'Seeders', 'value' => number_format($torrent->seeders), 'class' => 'text-emerald-400'],
            ['label' => 'Leechers', 'value' => number_format($torrent->leechers), 'class' => 'text-amber-400'],
            ['label' => 'Completed', 'value' => number_format($torrent->completed), 'class' => 'text-slate-100'],
        ];
        $links = array_filter([
            $torrent->imdb_id ? ['label' => 'IMDb: '.$torrent->imdb_id, 'url' => 'https://www.imdb.com/title/'.$torrent->imdb_id.'/'] : null,
            $torrent->tmdb_id ? ['label' => 'TMDB: '.$torrent->tmdb_id, 'url' => 'https://www.themoviedb.org/movie/'.$torrent->tmdb_id] : null,
        ]);
    ?>
    <div class="space-y-8">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-8 shadow-2xl shadow-slate-950/40">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-400">Approved torrent</p>
                    <h1 class="mt-2 text-3xl font-bold text-white"><?php echo e($torrent->name); ?></h1>
                    <p class="mt-1 text-sm text-slate-400">Uploaded <?php echo e(optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? 'recently'); ?> by <?php echo e($torrent->uploader?->name ?? 'Unknown'); ?></p>
                </div>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('download', $torrent)): ?>
                    <div class="flex flex-wrap gap-3">
                        <a href="<?php echo e(route('torrents.download', $torrent)); ?>" class="inline-flex items-center rounded-2xl bg-brand px-5 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-brand/30">Download .torrent</a>
                        <button type="button" id="magnetButton" data-url="<?php echo e(route('torrents.magnet', $torrent)); ?>" class="inline-flex items-center rounded-2xl border border-slate-700 px-5 py-2 text-sm font-semibold text-white hover:border-brand">Get magnet link</button>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('moderate', $torrent)): ?>
                <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                    <div class="flex flex-col gap-2 text-sm text-slate-200">
                        <p><span class="font-semibold">Status:</span> <?php echo e(ucfirst(str_replace('_', ' ', $torrent->status))); ?></p>
                        <?php if($torrent->moderator): ?>
                            <p><span class="font-semibold">Moderator:</span> <?php echo e($torrent->moderator->name); ?> • <?php echo e(optional($torrent->moderated_at)->toDayDateTimeString()); ?></p>
                        <?php endif; ?>
                        <?php if($torrent->moderated_reason): ?>
                            <p><span class="font-semibold">Reason:</span> <?php echo e($torrent->moderated_reason); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-end">
                        <form method="POST" action="<?php echo e(route('staff.torrents.approve', $torrent)); ?>" class="flex items-center gap-2">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950">Approve</button>
                        </form>
                        <form method="POST" action="<?php echo e(route('staff.torrents.reject', $torrent)); ?>" class="flex flex-1 flex-col gap-2">
                            <?php echo csrf_field(); ?>
                            <label class="text-xs uppercase tracking-wide text-slate-400">Reject reason
                                <input type="text" name="reason" required class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-white" placeholder="Short reason" />
                            </label>
                            <button type="submit" class="rounded-xl bg-rose-500 px-4 py-2 text-sm font-semibold text-white">Reject</button>
                        </form>
                        <form method="POST" action="<?php echo e(route('staff.torrents.soft_delete', $torrent)); ?>" class="flex items-center gap-2">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200">Soft-delete</button>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
            <dl class="mt-8 grid gap-6 md:grid-cols-3">
                <?php $__currentLoopData = $meta; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400"><?php echo e($item['label']); ?></dt>
                        <dd class="text-lg font-semibold text-white"><?php echo e($item['value']); ?></dd>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </dl>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <?php $__currentLoopData = $stats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="rounded-2xl bg-slate-950/60 p-4 text-center">
                        <p class="text-xs uppercase tracking-wide text-slate-400"><?php echo e($stat['label']); ?></p>
                        <p class="text-3xl font-bold <?php echo e($stat['class']); ?>"><?php echo e($stat['value']); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php if($links !== []): ?>
                <div class="mt-8 grid gap-4 md:grid-cols-2">
                    <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e($link['url']); ?>" class="rounded-2xl border border-slate-800 px-4 py-3 text-sm text-slate-300 hover:border-brand" rel="noreferrer noopener" target="_blank"><?php echo e($link['label']); ?></a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if(! empty($torrent->tags)): ?>
            <div class="flex flex-wrap gap-2">
                <?php $__currentLoopData = $torrent->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="rounded-full border border-slate-800 bg-slate-900/70 px-3 py-1 text-xs uppercase tracking-wide text-slate-300"><?php echo e($tag); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
        <?php if($descriptionHtml !== ''): ?>
            <section class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <h2 class="text-lg font-semibold text-white">Description</h2>
                <div class="prose prose-invert mt-4 max-w-none text-slate-100"><?php echo $descriptionHtml; ?></div>
            </section>
        <?php endif; ?>
        <?php if(($nfoText ?? '') !== ''): ?>
            <section class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <h2 class="text-lg font-semibold text-white">NFO</h2>
                <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950/70 p-4 text-sm text-slate-200"><?php echo $nfoText; ?></pre>
            </section>
        <?php endif; ?>
        <div id="magnetValue" class="hidden rounded-2xl border border-emerald-500/50 bg-emerald-500/10 p-4 text-emerald-100 text-sm"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const button = document.getElementById('magnetButton');
            const output = document.getElementById('magnetValue');
            if (!button || !output) {
                return;
            }
            button.addEventListener('click', async () => {
                output.textContent = 'Fetching magnet link…';
                output.classList.remove('hidden');
                try {
                    const response = await fetch(button.dataset.url ?? '', { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) {
                        output.textContent = 'Unable to fetch magnet link right now.';
                        return;
                    }
                    const payload = await response.json();
                    output.textContent = payload.magnet ?? 'Magnet link unavailable.';
                } catch (error) {
                    output.textContent = 'Unable to fetch magnet link right now.';
                }
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/torrents/show.blade.php ENDPATH**/ ?>