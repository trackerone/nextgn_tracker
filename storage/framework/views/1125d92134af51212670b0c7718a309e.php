<?php
    // Test-/view-sikkerhed: sørg for at disse altid findes, uanset controller-flow.
    $filters = $filters ?? [];
    $types = $types ?? [];
    $categories = $categories ?? collect();
?>



<?php $__env->startSection('title', 'Browse Torrents — '.config('app.name')); ?>

<?php $__env->startSection('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="space-y-8">
        <div class="rounded-2xl bg-slate-900/70 p-6 shadow-xl shadow-slate-900/30">
            <form method="GET" action="<?php echo e(route('torrents.index')); ?>" class="grid gap-4 md:grid-cols-4">
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Search</span>
                    <input
                        type="text"
                        name="q"
                        value="<?php echo e($filters['q'] ?? ''); ?>"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white focus:border-brand focus:outline-none"
                        placeholder="Name or tag"
                    >
                </label>
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Type</span>
                    <select name="type" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                        <option value="">All types</option>
                        <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($type); ?>" <?php if(($filters['type'] ?? '') === $type): echo 'selected'; endif; ?>><?php echo e(ucfirst($type)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <?php if($categories->isNotEmpty()): ?>
                    <label class="text-sm font-semibold text-slate-300">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Category</span>
                        <select name="category_id" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="">All categories</option>
                            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($category->id); ?>" <?php if((string) ($filters['category_id'] ?? '') === (string) $category->id): echo 'selected'; endif; ?>>
                                    <?php echo e($category->name); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </label>
                <?php endif; ?>
                <div class="grid gap-2 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-300">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Order by</span>
                        <select name="order" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="created" <?php if(($filters['order'] ?? 'created') === 'created'): echo 'selected'; endif; ?>>Uploaded</option>
                            <option value="size" <?php if(($filters['order'] ?? '') === 'size'): echo 'selected'; endif; ?>>Size</option>
                            <option value="seeders" <?php if(($filters['order'] ?? '') === 'seeders'): echo 'selected'; endif; ?>>Seeders</option>
                            <option value="leechers" <?php if(($filters['order'] ?? '') === 'leechers'): echo 'selected'; endif; ?>>Leechers</option>
                            <option value="completed" <?php if(($filters['order'] ?? '') === 'completed'): echo 'selected'; endif; ?>>Completed</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-300">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Direction</span>
                        <select name="direction" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="desc" <?php if(($filters['direction'] ?? 'desc') === 'desc'): echo 'selected'; endif; ?>>Desc</option>
                            <option value="asc" <?php if(($filters['direction'] ?? 'desc') === 'asc'): echo 'selected'; endif; ?>>Asc</option>
                        </select>
                    </label>
                </div>
                <div class="md:col-span-4 flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="rounded-xl bg-brand px-5 py-2 text-sm font-semibold text-white">Apply</button>
                    <a href="<?php echo e(route('torrents.index')); ?>" class="rounded-xl border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-200">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 shadow-xl shadow-slate-900/30">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">Size</th>
                            <th class="px-4 py-3 text-right">Seed</th>
                            <th class="px-4 py-3 text-right">Leech</th>
                            <th class="px-4 py-3 text-right">Done</th>
                            <th class="px-4 py-3 text-right">Uploaded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-slate-100">
                        <?php $__empty_1 = true; $__currentLoopData = $torrents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $torrent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-slate-800/50">
                                <td class="px-4 py-3">
                                    <a href="<?php echo e(route('torrents.show', $torrent)); ?>" class="font-semibold text-white hover:text-brand">
                                        <?php echo e($torrent->name); ?>

                                    </a>
                                    <?php if(! empty($torrent->tags)): ?>
                                        <div class="mt-1 flex flex-wrap gap-1 text-xs text-slate-400">
                                            <?php $__currentLoopData = $torrent->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <span class="rounded-full border border-slate-700 px-2 py-0.5"><?php echo e($tag); ?></span>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-300"><?php echo e(ucfirst($torrent->type)); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo e($torrent->formatted_size); ?></td>
                                <td class="px-4 py-3 text-right text-emerald-400"><?php echo e(number_format($torrent->seeders)); ?></td>
                                <td class="px-4 py-3 text-right text-amber-400"><?php echo e(number_format($torrent->leechers)); ?></td>
                                <td class="px-4 py-3 text-right text-slate-200"><?php echo e(number_format($torrent->completed)); ?></td>
                                <td class="px-4 py-3 text-right text-slate-400">
                                    <?php echo e(optional($torrent->uploadedAtForDisplay())->toDateTimeString() ?? '—'); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400">No torrents matched your filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                <?php echo e($torrents->links()); ?>

            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/torrents/index.blade.php ENDPATH**/ ?>