<?php $__env->startSection('title', 'Audit entry #'.$log->id.' — '.config('app.name')); ?>

<?php $__env->startSection('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="space-y-4">
        <a href="<?php echo e(route('admin.logs.audit.index')); ?>" class="text-sm text-slate-400 hover:text-white">&larr; Back to audit logs</a>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-semibold text-white"><?php echo e($log->action); ?></h1>
            <p class="text-sm text-slate-400">Recorded <?php echo e($log->created_at?->toDayDateTimeString()); ?></p>
            <dl class="mt-6 space-y-3 text-sm text-slate-200">
                <div>
                    <dt class="text-slate-400">User</dt>
                    <dd><?php echo e($log->user?->name ?? 'System'); ?> (ID <?php echo e($log->user_id ?? '—'); ?>)</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Target</dt>
                    <dd><?php echo e($log->target_type ?? 'N/A'); ?> #<?php echo e($log->target_id ?? '—'); ?></dd>
                </div>
                <?php if($target): ?>
                    <div>
                        <dt class="text-slate-400">Target preview</dt>
                        <dd class="text-xs text-slate-300"><?php echo e(method_exists($target, 'getAttribute') ? ($target->getAttribute('name') ?? $target->getAttribute('title') ?? $target->getKey()) : $target->getKey()); ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt class="text-slate-400">IP / Agent</dt>
                    <dd><?php echo e($log->ip_address ?? '—'); ?> &mdash; <?php echo e($log->user_agent ?? 'Unknown agent'); ?></dd>
                </div>
                <div>
                    <dt class="text-slate-400">Metadata</dt>
                    <dd><pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950/70 p-3 text-xs text-slate-300"><?php echo e(json_encode($log->metadata ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></dd>
                </div>
            </dl>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/admin/logs/audit/show.blade.php ENDPATH**/ ?>