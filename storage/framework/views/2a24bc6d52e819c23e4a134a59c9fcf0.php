<?php $__env->startSection('title', 'Security events — '.config('app.name')); ?>

<?php $__env->startSection('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Security events</h1>
            <p class="text-sm text-slate-400">Realtime feed of blocked or suspicious tracker behavior.</p>
        </div>
        <form method="GET" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 md:grid-cols-5">
            <input type="number" name="user_id" value="<?php echo e($filters['user_id'] ?? ''); ?>" placeholder="User ID" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="text" name="event_type" value="<?php echo e($filters['event_type'] ?? ''); ?>" placeholder="Event type" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <select name="severity" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white">
                <option value="">Severity</option>
                <?php $__currentLoopData = ['low', 'medium', 'high', 'critical']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $level): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($level); ?>" <?php if(($filters['severity'] ?? '') === $level): echo 'selected'; endif; ?>><?php echo e(ucfirst($level)); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <input type="datetime-local" name="from" value="<?php echo e($filters['from'] ?? ''); ?>" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="datetime-local" name="to" value="<?php echo e($filters['to'] ?? ''); ?>" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white">Filter</button>
                <a href="<?php echo e(route('admin.logs.security.index')); ?>" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-200">Reset</a>
            </div>
        </form>
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Timestamp</th>
                        <th class="px-4 py-3 text-left">Severity</th>
                        <th class="px-4 py-3 text-left">Event</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="px-4 py-3"><?php echo e($event->created_at?->toDayDateTimeString()); ?></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-800 px-2 py-1 text-xs uppercase tracking-wide"><?php echo e(strtoupper($event->severity)); ?></span></td>
                            <td class="px-4 py-3">
                                <a href="<?php echo e(route('admin.logs.security.show', $event)); ?>" class="font-semibold text-white hover:text-brand"><?php echo e($event->event_type); ?></a>
                                <p class="text-xs text-slate-400"><?php echo e(\Illuminate\Support\Str::limit($event->message, 80)); ?></p>
                            </td>
                            <td class="px-4 py-3"><?php echo e($event->user?->name ?? 'Unknown'); ?></td>
                            <td class="px-4 py-3 text-xs text-slate-400"><?php echo e($event->ip_address ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">No security events logged.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                <?php echo e($events->links()); ?>

            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/admin/logs/security/index.blade.php ENDPATH**/ ?>