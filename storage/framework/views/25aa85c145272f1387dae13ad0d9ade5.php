<?php $__env->startSection('title', 'Torrent moderation — '.config('app.name')); ?>

<?php $__env->startSection('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="space-y-8">
        <div>
            <h1 class="text-2xl font-semibold text-white">Pending torrents</h1>
            <p class="text-sm text-slate-400">Approve, reject, or soft-delete submissions before they hit the browse index.</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Uploader</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-right">Size</th>
                        <th class="px-4 py-3 text-right">Uploaded</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    <?php $__empty_1 = true; $__currentLoopData = $pendingTorrents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $torrent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="px-4 py-3">
                                <a href="<?php echo e(route('torrents.show', $torrent)); ?>" class="font-semibold text-white hover:text-brand"><?php echo e($torrent->name); ?></a>
                            </td>
                            <td class="px-4 py-3"><?php echo e($torrent->uploader?->name ?? 'Unknown'); ?></td>
                            <td class="px-4 py-3"><?php echo e(ucfirst($torrent->type)); ?></td>
                            <td class="px-4 py-3 text-right font-semibold"><?php echo e($torrent->formatted_size); ?></td>
                            <td class="px-4 py-3 text-right text-slate-400"><?php echo e(optional($torrent->uploadedAtForDisplay())->toDateTimeString() ?? '—'); ?></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-2">
                                    <form method="POST" action="<?php echo e(route('staff.torrents.approve', $torrent)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="w-full rounded-xl bg-emerald-500 px-3 py-1 text-xs font-semibold text-slate-950">Approve</button>
                                    </form>
                                    <form method="POST" action="<?php echo e(route('staff.torrents.reject', $torrent)); ?>" class="flex flex-col gap-2">
                                        <?php echo csrf_field(); ?>
                                        <input type="text" name="reason" required placeholder="Reason" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-2 py-1 text-xs text-white">
                                        <button type="submit" class="w-full rounded-xl bg-rose-500 px-3 py-1 text-xs font-semibold text-white">Reject</button>
                                    </form>
                                    <form method="POST" action="<?php echo e(route('staff.torrents.soft_delete', $torrent)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="w-full rounded-xl border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-200">Soft-delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">No pending torrents.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                <?php echo e($pendingTorrents->links()); ?>

            </div>
        </div>
        <?php if($recentTorrents->isNotEmpty()): ?>
            <section class="rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
                <h2 class="text-lg font-semibold text-white">Recently moderated</h2>
                <div class="mt-4 space-y-3">
                    <?php $__currentLoopData = $recentTorrents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $torrent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-3 text-sm text-slate-200">
                            <div class="flex flex-wrap justify-between gap-2">
                                <a href="<?php echo e(route('torrents.show', $torrent)); ?>" class="font-semibold text-white hover:text-brand"><?php echo e($torrent->name); ?></a>
                                <span class="text-xs uppercase tracking-wide text-slate-400"><?php echo e(ucfirst(str_replace('_', ' ', $torrent->status))); ?></span>
                            </div>
                            <p class="text-xs text-slate-400">By <?php echo e($torrent->moderator?->name ?? 'Unknown'); ?> • <?php echo e(optional($torrent->moderated_at)->toDayDateTimeString() ?? 'recently'); ?></p>
                            <?php if($torrent->moderated_reason): ?>
                                <p class="mt-1 text-xs text-slate-300">Reason: <?php echo e($torrent->moderated_reason); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/staff/torrents/moderation/index.blade.php ENDPATH**/ ?>