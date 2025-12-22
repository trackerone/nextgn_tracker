<?php $__env->startSection('title', 'Audit logs — '.config('app.name')); ?>

<?php $__env->startSection('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Audit logs</h1>
            <p class="text-sm text-slate-400">Structured record of sensitive actions performed across the tracker.</p>
        </div>
        <form method="GET" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 md:grid-cols-5">
            <input type="number" name="user_id" value="<?php echo e($filters['user_id'] ?? ''); ?>" placeholder="User ID" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="text" name="action" value="<?php echo e($filters['action'] ?? ''); ?>" placeholder="Action" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="text" name="target_type" value="<?php echo e($filters['target_type'] ?? ''); ?>" placeholder="Target type" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="datetime-local" name="from" value="<?php echo e($filters['from'] ?? ''); ?>" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="datetime-local" name="to" value="<?php echo e($filters['to'] ?? ''); ?>" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white">Filter</button>
                <a href="<?php echo e(route('admin.logs.audit.index')); ?>" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-200">Reset</a>
            </div>
        </form>
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Timestamp</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Target</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="px-4 py-3"><?php echo e($log->created_at?->toDayDateTimeString()); ?></td>
                            <td class="px-4 py-3"><?php echo e($log->user?->name ?? 'System'); ?></td>
                            <td class="px-4 py-3">
                                <a href="<?php echo e(route('admin.logs.audit.show', $log)); ?>" class="font-semibold text-white hover:text-brand"><?php echo e($log->action); ?></a>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400"><?php echo e($log->target_type ?? 'N/A'); ?> #<?php echo e($log->target_id ?? '—'); ?></td>
                            <td class="px-4 py-3 text-xs text-slate-400"><?php echo e($log->ip_address ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">No audit entries yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                <?php echo e($logs->links()); ?>

            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/admin/logs/audit/index.blade.php ENDPATH**/ ?>