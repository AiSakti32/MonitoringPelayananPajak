<?php
/** @var \App\Core\Paginator|null $paginator */
/** @var array $filters */
/** @var string|null $loadError */
/** @var string $basePath */
\App\Core\View::partial('partials/loading_overlay');
?>
<div class="page-intro mb-3">
    <p class="text-muted mb-0">Sumber kasus configurable (seed: Portal, Core).</p>
</div>

<?php
\App\Core\View::partial('partials/master_toolbar', [
    'basePath' => $basePath,
    'filters' => $filters,
    'createUrl' => '/master/sources/create',
    'createLabel' => 'Tambah Sumber',
]);
?>

<div class="panel-card master-panel mt-3">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => 'Belum ada sumber',
            'hint' => 'Tambahkan sumber kasus seperti Portal atau Core.',
            'actionUrl' => '/master/sources/create',
            'actionLabel' => 'Tambah Sumber',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive master-table-wrap">
            <table class="table master-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Diperbarui</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row): ?>
                    <tr class="<?= !(int) $row['is_active'] ? 'row-inactive' : '' ?>">
                        <td class="fw-semibold" data-label="Nama"><?= e($row['name']) ?></td>
                        <td data-label="Status"><?= active_badge((int) $row['is_active']) ?></td>
                        <td data-label="Diperbarui"><?= e(format_datetime_id($row['updated_at'])) ?></td>
                        <td class="text-end master-actions" data-label="Aksi">
                            <div class="master-actions__btns">
                                <a href="<?= e(url('/master/sources/' . $row['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="post" action="<?= e(url('/master/sources/' . $row['id'] . '/toggle')) ?>" class="d-inline" data-confirm="<?= (int) $row['is_active'] ? 'Nonaktifkan sumber ini?' : 'Aktifkan sumber ini?' ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm <?= (int) $row['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" data-loading-btn>
                                        <?= (int) $row['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'basePath' => $basePath]); ?>
    <?php endif; ?>
</div>
