<?php
/** @var \App\Core\Paginator|null $paginator */
/** @var array $filters */
/** @var string|null $loadError */
/** @var string $basePath */
/** @var bool $isAdmin */
\App\Core\View::partial('partials/loading_overlay');

$q = trim((string) ($filters['q'] ?? ''));
$dl = (string) ($filters['deadline'] ?? 'all');
$filterBase = [
    'q' => $filters['q'] ?? '',
    'officer_id' => $filters['officer_id'] ?? '',
    'status_id' => $filters['status_id'] ?? '',
    'case_type_id' => $filters['case_type_id'] ?? '',
    'source_id' => $filters['source_id'] ?? '',
    'deadline' => $dl === 'all' ? '' : $dl,
];
$activeChips = [];
if ($q !== '') {
    $activeChips[] = [
        'label' => 'Cari: ' . $q,
        'removeUrl' => query_url($basePath, ['q' => ''], $filterBase),
    ];
}
if ($isAdmin && !empty($filters['officer_id'])) {
    $activeChips[] = [
        'label' => 'Petugas: ' . option_label_by_id($officers, $filters['officer_id'], 'Petugas'),
        'removeUrl' => query_url($basePath, ['officer_id' => '', 'officer' => ''], $filterBase),
    ];
}
if (!empty($filters['status_id'])) {
    $activeChips[] = [
        'label' => 'Status: ' . option_label_by_id($statuses, $filters['status_id'], 'Status'),
        'removeUrl' => query_url($basePath, ['status_id' => ''], $filterBase),
    ];
}
if (!empty($filters['case_type_id'])) {
    $activeChips[] = [
        'label' => 'Jenis: ' . option_label_by_id($types, $filters['case_type_id'], 'Jenis'),
        'removeUrl' => query_url($basePath, ['case_type_id' => ''], $filterBase),
    ];
}
if (!empty($filters['source_id'])) {
    $activeChips[] = [
        'label' => 'Sumber: ' . option_label_by_id($sources, $filters['source_id'], 'Sumber'),
        'removeUrl' => query_url($basePath, ['source_id' => ''], $filterBase),
    ];
}
if ($dl !== '' && $dl !== 'all') {
    $dlLabels = [
        'active' => 'Aktif',
        'overdue' => 'Terlambat',
        'today' => 'Hari ini',
        'h3' => 'H-3',
        'h5' => 'H-5',
        'normal' => 'Normal',
        'selesai' => 'Selesai',
    ];
    $activeChips[] = [
        'label' => 'Deadline: ' . ($dlLabels[$dl] ?? $dl),
        'removeUrl' => query_url($basePath, ['deadline' => ''], $filterBase),
    ];
}
$hasActiveFilters = $activeChips !== [];

$headingHtml = '<h2 class="cases-list-title">Daftar Kasus</h2>'
    . '<p class="cases-list-subtitle">Kelola dan monitor seluruh permohonan yang sedang ditangani.</p>'
    . '<p class="cases-list-helper" title="Setiap nomor kasus unik dan menjadi satu data utama.">'
    . '<i class="bi bi-info-circle" aria-hidden="true"></i> 1 Nomor Kasus = 1 data utama'
    . '</p>';

$endActions = '<a href="' . e(url('/cases/import')) . '" class="btn btn-outline-secondary btn-add cases-list-cta">'
    . '<i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Import Excel</a>'
    . '<a href="' . e(url('/cases/create')) . '" class="btn btn-primary btn-add cases-list-cta">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> Simpan/Update Kasus</a>';

\App\Core\View::partial('partials/collapsible_filter_start', [
    'activeChips' => $activeChips,
    'resetUrl' => url($basePath),
    'headingHtml' => $headingHtml,
    'endActions' => $endActions,
]);
?>
<form method="get" action="<?= e(url($basePath)) ?>" class="app-filter-panel case-filters dash-filter-panel--embedded" id="masterFilterForm">
    <div class="app-filter-panel__header">
        <span class="app-filter-panel__icon" aria-hidden="true"><i class="bi bi-funnel"></i></span>
        <div>
            <h3 class="app-filter-panel__title">Filter Data</h3>
            <p class="app-filter-panel__desc">Saring daftar kasus berdasarkan kata kunci dan kriteria yang dipilih.</p>
        </div>
    </div>

    <div class="app-filter-grid app-filter-grid--cases">
        <div class="app-filter-field <?= $q !== '' ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="case_filter_q">Cari</label>
            <input type="search" id="case_filter_q" name="q" class="form-control app-filter-control"
                   value="<?= e($q) ?>" placeholder="Nomor / NPWP / Nama WP">
        </div>

        <?php if ($isAdmin): ?>
        <div class="app-filter-field <?= !empty($filters['officer_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="case_filter_officer">Petugas</label>
            <select id="case_filter_officer" name="officer" class="form-select app-filter-control">
                <option value="">Semua</option>
                <?php foreach ($officers as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= (string) ($filters['officer_id'] ?? '') === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="app-filter-field <?= !empty($filters['status_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="case_filter_status">Status</label>
            <select id="case_filter_status" name="status_id" class="form-select app-filter-control">
                <option value="">Semua</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['status_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['case_type_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="case_filter_type">Jenis</label>
            <select id="case_filter_type" name="case_type_id" class="form-select app-filter-control">
                <option value="">Semua</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (string) ($filters['case_type_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['source_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="case_filter_source">Sumber</label>
            <select id="case_filter_source" name="source_id" class="form-select app-filter-control">
                <option value="">Semua</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?= (int) $src['id'] ?>" <?= (string) ($filters['source_id'] ?? '') === (string) $src['id'] ? 'selected' : '' ?>><?= e($src['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= ($dl !== '' && $dl !== 'all') ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="case_filter_deadline">Deadline</label>
            <select id="case_filter_deadline" name="deadline" class="form-select app-filter-control">
                <?php
                $opts = [
                    'all' => 'Semua',
                    'active' => 'Aktif (belum selesai)',
                    'overdue' => 'Terlambat',
                    'today' => 'Hari ini',
                    'h3' => 'H-3',
                    'h5' => 'H-5',
                    'normal' => 'Normal',
                    'selesai' => 'Selesai',
                ];
                foreach ($opts as $k => $label):
                ?>
                    <option value="<?= e($k) ?>" <?= $dl === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="app-filter-panel__footer">
        <a href="<?= e(url($basePath)) ?>"
           class="btn app-filter-btn app-filter-btn--ghost<?= $hasActiveFilters ? '' : ' is-muted' ?>">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            Reset filter
        </a>
        <button type="submit" class="btn app-filter-btn app-filter-btn--primary" data-loading-btn>Terapkan Filter</button>
    </div>
</form>
<?php \App\Core\View::partial('partials/collapsible_filter_end'); ?>

<div class="panel-card cases-table-card">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php if ($hasActiveFilters): ?>
            <?php \App\Core\View::partial('partials/empty_state', [
                'title' => 'Tidak ada kasus ditemukan',
                'hint' => 'Ubah filter atau tambahkan data kasus baru.',
                'actionUrl' => $basePath,
                'actionLabel' => 'Reset Filter',
            ]); ?>
        <?php else: ?>
            <?php \App\Core\View::partial('partials/empty_state', [
                'title' => 'Belum ada kasus',
                'hint' => 'Gunakan form Simpan/Update Kasus untuk menambah atau memperbarui permohonan.',
                'actionUrl' => '/cases/create',
                'actionLabel' => 'Input Kasus',
            ]); ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive cases-table-wrap">
            <table class="table cases-table align-middle mb-0">
                <thead>
                <tr>
                    <th class="col-case-number">Nomor Kasus</th>
                    <th class="col-taxpayer">Wajib Pajak</th>
                    <th class="col-case-type">Jenis Permohonan</th>
                    <th class="col-status">Status</th>
                    <th class="col-source">Sumber</th>
                    <th class="col-date">Dibuat</th>
                    <th class="col-date">Jatuh Tempo</th>
                    <th class="col-deadline">Deadline</th>
                    <th class="col-officer">Petugas</th>
                    <th class="col-actions text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row):
                    $caseId = (int) $row['id'];
                    $caseType = (string) ($row['case_type_name'] ?? '');
                    $statusName = (string) ($row['status_name'] ?? '');
                    $statusSlug = strtolower(trim($statusName));
                    $statusTone = match (true) {
                        str_contains($statusSlug, 'selesai') => 'done',
                        str_contains($statusSlug, 'proses') => 'progress',
                        str_contains($statusSlug, 'buat') => 'new',
                        default => 'neutral',
                    };
                ?>
                    <tr>
                        <td class="col-case-number">
                            <a class="cases-case-number" href="<?= e(url('/cases/' . $caseId)) ?>"><?= e((string) $row['case_number']) ?></a>
                        </td>
                        <td class="col-taxpayer">
                            <div class="cases-taxpayer">
                                <span class="cases-taxpayer__name"><?= e((string) ($row['taxpayer_name'] ?? '—')) ?></span>
                                <span class="cases-taxpayer__npwp"><?= e(preg_replace('/\D+/', '', (string) ($row['npwp'] ?? '')) ?: '—') ?></span>
                            </div>
                        </td>
                        <td class="col-case-type">
                            <span class="cases-type-clamp" title="<?= e($caseType) ?>"><?= e($caseType) ?></span>
                        </td>
                        <td class="col-status">
                            <span class="cases-status-badge tone-<?= e($statusTone) ?>"><?= e($statusName) ?></span>
                        </td>
                        <td class="col-source">
                            <span class="cases-source"><?= e((string) ($row['source_name'] ?? '—')) ?></span>
                        </td>
                        <td class="col-date">
                            <span class="cases-date"><?= e(format_date_short_id($row['created_date'] ?? null)) ?></span>
                        </td>
                        <td class="col-date">
                            <span class="cases-date"><?= e(format_date_short_id($row['due_date'] ?? null)) ?></span>
                        </td>
                        <td class="col-deadline">
                            <?= deadline_badge($row['deadline'] ?? ['label' => '—', 'tone' => 'normal']) ?>
                        </td>
                        <td class="col-officer">
                            <span class="cases-officer"><?= e((string) ($row['officer_name'] ?? '—')) ?></span>
                        </td>
                        <td class="col-actions text-end">
                            <div class="cases-row-actions">
                                <a class="cases-action-link" href="<?= e(url('/cases/' . $caseId)) ?>">Detail</a>
                                <div class="dropdown">
                                    <button class="cases-action-more"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            data-bs-popper-config='{"strategy":"fixed"}'
                                            aria-expanded="false"
                                            aria-label="Aksi lainnya">
                                        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end cases-action-menu">
                                        <li>
                                            <a class="dropdown-item" href="<?= e(url('/cases/' . $caseId . '/edit')) ?>">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                Edit Kasus
                                            </a>
                                        </li>
                                    </ul>
                                </div>
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
