<?php
/** @var \App\Core\Paginator|null $paginator */
/** @var array $filters */
/** @var string|null $loadError */
/** @var string $basePath */
\App\Core\View::partial('partials/loading_overlay');
?>
<div class="page-intro mb-3">
    <p class="text-muted mb-0">Kelola daftar petugas yang dapat di-assign ke case.</p>
</div>

<?php
\App\Core\View::partial('partials/master_toolbar', [
    'basePath' => $basePath,
    'filters' => $filters,
    'createUrl' => '/master/officers/create',
    'createLabel' => 'Tambah Petugas',
]);
?>

<div class="panel-card master-panel mt-3">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => ($filters['q'] ?? '') !== '' || ($filters['status'] ?? 'all') !== 'all'
                ? 'Tidak ada hasil'
                : 'Belum ada petugas',
            'hint' => ($filters['q'] ?? '') !== '' || ($filters['status'] ?? 'all') !== 'all'
                ? 'Coba ubah kata kunci atau filter.'
                : 'Tambahkan petugas agar dapat ditugaskan pada kasus.',
            'actionUrl' => '/master/officers/create',
            'actionLabel' => 'Tambah Petugas',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive master-table-wrap">
            <table class="table master-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kode</th>
                    <th>Status</th>
                    <th>Diperbarui</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row): ?>
                    <tr class="<?= !(int) $row['is_active'] ? 'row-inactive' : '' ?>">
                        <td class="fw-semibold" data-label="Nama"><?= e($row['name']) ?></td>
                        <td data-label="Kode"><?= e($row['employee_code'] ?: '—') ?></td>
                        <td data-label="Status"><?= active_badge((int) $row['is_active']) ?></td>
                        <td data-label="Diperbarui"><?= e(format_datetime_id($row['updated_at'])) ?></td>
                        <td class="text-end master-actions" data-label="Aksi">
                            <div class="master-actions__btns">
                                <a href="<?= e(url('/master/officers/' . $row['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="post" action="<?= e(url('/master/officers/' . $row['id'] . '/toggle')) ?>" class="d-inline" data-confirm="<?= (int) $row['is_active'] ? 'Nonaktifkan petugas ini?' : 'Aktifkan kembali petugas ini?' ?>">
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
