<?php
/** @var int $status */
/** @var string $message */
?>
<div class="text-center py-4">
    <div class="display-6 fw-bold mb-2"><?= (int) $status ?></div>
    <h2 class="h4"><?= e($pageTitle ?? 'Error') ?></h2>
    <p class="text-muted"><?= e($message ?? '') ?></p>
    <a href="<?= e(url(auth_user() ? '/dashboard' : '/login')) ?>" class="btn btn-primary mt-2">
        Kembali
    </a>
</div>
