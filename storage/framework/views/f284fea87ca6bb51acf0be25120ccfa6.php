<?php $__env->startSection('title', 'Security event #'.$event->id.' — '.config('app.name')); ?>

<?php $__env->startSection('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="space-y-4">
        <a href="<?php echo e(route('admin.logs.security.index')); ?>" class="text-sm text-slate-400 hover:text-white">&larr; Back to security events</a>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-semibold text-white"><?php echo e($event->event_type); ?></h1>
            <p class="text-sm text-slate-400"><?php echo e(ucfirst($event->severity)); ?> severity &middot; <?php echo e($event->created_at?->toDayDateTimeString()); ?></p>
            <p class="mt-4 text-slate-200"><?php echo e($event->message); ?></p>
            <dl class="mt-6 space-y-3 text-sm text-slate-200">
                <div>
                    <dt class="text-slate-400">User</dt>
                    <dd><?php echo e($event->user?->name ?? 'Unknown'); ?> (ID <?php echo e($event->user_id ?? '—'); ?>)</dd>
                </div>
                <div>
                    <dt class="text-slate-400">IP / Agent</dt>
                    <dd><?php echo e($event->ip_address ?? '—'); ?> &mdash; <?php echo e($event->user_agent ?? 'Unknown agent'); ?></dd>
                </div>
                <div>
                    <dt class="text-slate-400">Context</dt>
                    <dd><pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950/70 p-3 text-xs text-slate-300"><?php echo e(json_encode($event->context ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></dd>
                </div>
            </dl>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/admin/logs/security/show.blade.php ENDPATH**/ ?>