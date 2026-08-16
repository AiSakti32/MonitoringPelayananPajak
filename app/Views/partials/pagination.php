<?php
/** @var \App\Core\Paginator $paginator */
/** @var string $basePath */
if (!$paginator->hasPages()) {
    return;
}
$current = $paginator->page;
$last = $paginator->lastPage();
$start = max(1, $current - 2);
$end = min($last, $current + 2);
?>
<nav class="master-pagination" aria-label="Pagination">
    <div class="pagination-meta">
        Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> data
    </div>
    <ul class="pagination mb-0">
        <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current <= 1 ? '#' : e(query_url($basePath, ['page' => $current - 1])) ?>">Sebelumnya</a>
        </li>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= $i === $current ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(query_url($basePath, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $current >= $last ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current >= $last ? '#' : e(query_url($basePath, ['page' => $current + 1])) ?>">Berikutnya</a>
        </li>
    </ul>
</nav>
