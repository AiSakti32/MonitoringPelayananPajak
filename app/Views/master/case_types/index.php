<?php
/** @var \App\Core\Paginator|null $paginator */
/** @var array $filters */
/** @var string|null $loadError */
/** @var string $basePath */
\App\Core\View::partial('partials/loading_overlay');
?>
<div class="page-intro mb-3">
    <p class="text-muted mb-0">Jenis kasus configurable. Varian Portal/Core digabung via <strong>Dashboard Group</strong>.</p>
</div>

<?php
\App\Core\View::partial('partials/master_toolbar', [
    'basePath' => $basePath,
    'filters' => $filters,
    'createUrl' => '/master/case-types/create',
    'createLabel' => 'Tambah Jenis Kasus',
]);
?>

<div class="panel-card master-panel mt-3">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => ($filters['q'] ?? '') !== '' || ($filters['status'] ?? 'all') !== 'all' ? 'Tidak ada hasil' : 'Belum ada jenis kasus',
            'hint' => 'Tambahkan jenis kasus sesuai kebutuhan operasional.',
            'actionUrl' => '/master/case-types/create',
            'actionLabel' => 'Tambah Jenis Kasus',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive master-table-wrap">
            <table class="table master-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Nama Jenis</th>
                    <th>Dashboard Group</th>
                    <th>Prioritas</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row): ?>
                    <tr class="<?= !(int) $row['is_active'] ? 'row-inactive' : '' ?>">
                        <td class="fw-semibold" data-label="Nama Jenis"><?= e($row['name']) ?></td>
                        <td data-label="Dashboard Group"><?= e($row['dashboard_group'] ?: '—') ?></td>
                        <td data-label="Prioritas">
                            <?php if ((int) $row['is_dashboard_priority']): ?>
                                <span class="badge badge-priority">Prioritas</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><?= active_badge((int) $row['is_active']) ?></td>
                        <td class="text-end master-actions" data-label="Aksi">
                            <div class="master-actions__btns">
                                <a href="<?= e(url('/master/case-types/' . $row['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="post" action="<?= e(url('/master/case-types/' . $row['id'] . '/toggle')) ?>" class="d-inline" data-confirm="<?= (int) $row['is_active'] ? 'Nonaktifkan jenis kasus ini?' : 'Aktifkan jenis kasus ini?' ?>">
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
