<?php
/** @var \App\Core\Paginator|null $paginator */
/** @var array $filters */
/** @var string|null $loadError */
/** @var string $basePath */
\App\Core\View::partial('partials/loading_overlay');
$currentId = auth_user()['id'] ?? null;
?>
<div class="page-intro mb-3">
    <p class="text-muted mb-0">Kelola akun internal. Role <strong>Petugas</strong> wajib dikaitkan ke master petugas. Tidak ada registrasi publik.</p>
</div>

<?php
\App\Core\View::partial('partials/master_toolbar', [
    'basePath' => $basePath,
    'filters' => $filters,
    'createUrl' => '/users/create',
    'createLabel' => 'Tambah User',
    'showRoleFilter' => true,
]);
?>

<div class="panel-card master-panel mt-3">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => 'Belum ada user',
            'hint' => 'Buat user admin atau petugas untuk akses sistem.',
            'actionUrl' => '/users/create',
            'actionLabel' => 'Tambah User',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive master-table-wrap">
            <table class="table master-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Petugas</th>
                    <th>Status</th>
                    <th>Login terakhir</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row): ?>
                    <tr class="<?= !(int) $row['is_active'] ? 'row-inactive' : '' ?>">
                        <td class="fw-semibold" data-label="Nama"><?= e($row['full_name']) ?></td>
                        <td data-label="Username"><?= e($row['username']) ?></td>
                        <td data-label="Role"><span class="badge <?= $row['role'] === 'admin' ? 'badge-role-admin' : 'badge-role-petugas' ?>"><?= e(ucfirst($row['role'])) ?></span></td>
                        <td data-label="Petugas"><?= e($row['officer_name'] ?: '—') ?></td>
                        <td data-label="Status"><?= active_badge((int) $row['is_active']) ?></td>
                        <td data-label="Login terakhir"><?= e(format_datetime_id($row['last_login_at'] ?? null)) ?></td>
                        <td class="text-end master-actions" data-label="Aksi">
                            <div class="master-actions__btns">
                                <a href="<?= e(url('/users/' . $row['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <?php if ((int) $row['id'] !== (int) $currentId): ?>
                                    <form method="post" action="<?= e(url('/users/' . $row['id'] . '/toggle')) ?>" class="d-inline" data-confirm="<?= (int) $row['is_active'] ? 'Nonaktifkan user ini?' : 'Aktifkan user ini?' ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm <?= (int) $row['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" data-loading-btn>
                                            <?= (int) $row['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Anda</span>
                                <?php endif; ?>
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
