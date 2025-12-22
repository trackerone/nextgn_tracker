<?php $__env->startSection('title', __('Something went wrong')); ?>
<?php $__env->startSection('status', '500'); ?>
<?php $__env->startSection('message', __('An unexpected error occurred. Please try again later.')); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspaces/nextgn_tracker/resources/views/errors/500.blade.php ENDPATH**/ ?>