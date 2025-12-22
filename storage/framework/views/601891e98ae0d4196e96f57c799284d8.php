<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invite management</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #020617; color: #e2e8f0; padding: 2.5rem; }
        h1 { font-size: 2rem; font-weight: 600; }
        form { margin-top: 1.5rem; display: grid; gap: 0.75rem; max-width: 32rem; }
        label { display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.9rem; color: #cbd5f5; }
        input, textarea, select { padding: 0.65rem; border-radius: 0.375rem; border: 1px solid #1e293b; background: #020617; color: #f8fafc; }
        button { padding: 0.65rem 1rem; border-radius: 0.375rem; border: none; background-color: #2563eb; color: white; font-weight: 600; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #1f2937; text-align: left; font-size: 0.95rem; }
        th { text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.8rem; color: #94a3b8; }
        nav { margin-top: 1rem; }
        nav a { margin-right: 0.75rem; padding: 0.4rem 0.9rem; border-radius: 999px; text-decoration: none; font-size: 0.85rem; }
        .pill-active { background-color: #2563eb; color: white; }
        .pill-muted { background-color: #111827; color: #94a3b8; }
        .status { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background-color: #14532d; color: #dcfce7; max-width: 32rem; }
    </style>
</head>
<body>
    <h1>Invite management</h1>

    <nav>
        <?php ($options = ['' => 'All', 'active' => 'Active', 'used' => 'Used', 'expired' => 'Expired']); ?>
        <?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('admin.invites.index', array_filter(['status' => $value]))); ?>"
               class="<?php echo e(($status ?? '') === $value ? 'pill-active' : 'pill-muted'); ?>">
                <?php echo e($label); ?>

            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>

    <?php if(session('status')): ?>
        <div class="status"><?php echo e(session('status')); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('admin.invites.store')); ?>">
        <?php echo csrf_field(); ?>
        <label>
            Max uses
            <input type="number" name="max_uses" min="1" value="<?php echo e(old('max_uses', 1)); ?>" required>
        </label>
        <label>
            Expires at (optional)
            <input type="datetime-local" name="expires_at" value="<?php echo e(old('expires_at')); ?>">
        </label>
        <label>
            Notes
            <textarea name="notes" rows="3" placeholder="Optional"><?php echo e(old('notes')); ?></textarea>
        </label>
        <button type="submit">Generate invite</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Uses</th>
                <th>Max</th>
                <th>Expires</th>
                <th>Inviter</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $invites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><code><?php echo e($invite->code); ?></code></td>
                    <td><?php echo e($invite->uses); ?></td>
                    <td><?php echo e($invite->max_uses); ?></td>
                    <td><?php echo e(optional($invite->expires_at)->format('Y-m-d H:i') ?? 'Never'); ?></td>
                    <td><?php echo e($invite->inviter?->name ?? 'System'); ?></td>
                    <td><?php echo e($invite->notes ?? '—'); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 2rem; color:#94a3b8;">No invites found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php echo e($invites->links()); ?>

</body>
</html>
<?php /**PATH /workspaces/nextgn_tracker/resources/views/admin/invites/index.blade.php ENDPATH**/ ?>