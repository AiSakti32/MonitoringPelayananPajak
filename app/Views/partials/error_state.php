<?php /** @var string $message */ ?>
<div class="state-error" role="alert">
    <div class="state-error-icon"><i class="bi bi-exclamation-triangle"></i></div>
    <div>
        <strong>Gagal memuat data</strong>
        <p class="mb-0"><?= e($message ?? 'Terjadi kesalahan. Coba muat ulang halaman.') ?></p>
    </div>
    <a href="<?= e(url((string) ($retryUrl ?? '/'))) ?>" class="btn btn-outline-danger btn-sm ms-auto">Coba lagi</a>
</div>
