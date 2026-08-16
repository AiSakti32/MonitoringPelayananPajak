<?php /** @var string $title */ /** @var string $hint */ ?>
<div class="empty-state py-5">
    <i class="bi bi-inbox"></i>
    <h3 class="h6 mt-3 mb-1"><?= e($title ?? 'Belum ada data') ?></h3>
    <p class="mb-0"><?= e($hint ?? 'Silakan tambah data baru untuk memulai.') ?></p>
    <?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
        <a href="<?= e(url((string) $actionUrl)) ?>" class="btn btn-primary btn-sm mt-3"><?= e((string) $actionLabel) ?></a>
    <?php endif; ?>
</div>
