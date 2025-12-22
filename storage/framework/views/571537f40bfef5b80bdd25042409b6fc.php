<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <title><?php echo $__env->yieldContent('title', config('app.name', 'NextGN Tracker')); ?></title>
        <?php echo $__env->yieldContent('meta'); ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.tsx']); ?>
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-slate-100">
        <div class="border-b border-slate-800 bg-slate-950/80">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-4">
                    <a href="<?php echo e(url('/')); ?>" class="text-lg font-semibold text-white">
                        <?php echo e(config('app.name', 'NextGN Tracker')); ?>

                    </a>
                    <a href="<?php echo e(route('torrents.index')); ?>" class="text-sm font-medium text-slate-300 hover:text-white">
                        Torrents
                    </a>
                </div>
                <?php if(auth()->guard()->check()): ?>
                    <div class="flex items-center gap-2 text-sm text-slate-300">
                        <span><?php echo e(auth()->user()->name); ?></span>
                        <span class="rounded-full border border-slate-800 bg-slate-900/70 px-3 py-1 text-xs font-semibold uppercase">
                            <?php echo e(auth()->user()->role_label); ?>

                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <main class="mx-auto w-full max-w-6xl px-4 py-8">
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </body>
</html>
<?php /**PATH /workspaces/nextgn_tracker/resources/views/layouts/app.blade.php ENDPATH**/ ?>