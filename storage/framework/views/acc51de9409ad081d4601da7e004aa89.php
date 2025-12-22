<?php $__env->startSection('title', __('Page not found')); ?>
<?php $__env->startSection('status', '404'); ?>
<?php $__env->startSection('message', __('The page you are looking for could not be found.')); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/errors/404.blade.php ENDPATH**/ ?>