<?php
/**
 * @var list<array> $workload
 * @var \App\Core\Paginator|null $paginator
 * @var array|null $detail
 * @var array $filters
 * @var int|null $selectedOfficerId
 * @var string|null $loadError
 * @var string $basePath
 * @var bool $isAdmin
 * @var list<array> $officers
 * @var list<array> $statuses
 * @var list<array> $types
 * @var list<array> $sources
 */
\App\Core\View::partial('partials/loading_overlay');
?>
<div class="page-intro mb-3 d-none">
    <p class="text-muted mb-0">
        Pilih petugas untuk melihat case yang sedang ditangani.
        Filter jenis, status, sumber, dan periode berlaku pada daftar case.
    </p>
</div>

<?php if ($loadError): ?>
    <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
<?php endif; ?>

<?php
$createdFrom = trim((string) ($filters['created_from'] ?? ''));
$createdTo = trim((string) ($filters['created_to'] ?? ''));
$dueFrom = trim((string) ($filters['due_from'] ?? ''));
$dueTo = trim((string) ($filters['due_to'] ?? ''));
$filterBase = [
    'officer_id' => $filters['officer_id'] ?? '',
    'case_type_id' => $filters['case_type_id'] ?? '',
    'status_id' => $filters['status_id'] ?? '',
    'source_id' => $filters['source_id'] ?? '',
    'created_from' => $createdFrom,
    'created_to' => $createdTo,
    'due_from' => $dueFrom,
    'due_to' => $dueTo,
];
$activeChips = [];
if ($createdFrom !== '' || $createdTo !== '') {
    $activeChips[] = [
        'label' => 'Dibuat: ' . ($createdFrom !== '' ? format_date_id($createdFrom) : '…') . ' – ' . ($createdTo !== '' ? format_date_id($createdTo) : '…'),
        'removeUrl' => query_url($basePath, ['created_from' => '', 'created_to' => ''], $filterBase),
    ];
}
if ($isAdmin && !empty($filters['officer_id'])) {
    $activeChips[] = [
        'label' => 'Petugas: ' . option_label_by_id($officers, $filters['officer_id'], 'Petugas'),
        'removeUrl' => query_url($basePath, ['officer_id' => '', 'officer' => ''], $filterBase),
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
if (!empty($filters['status_id'])) {
    $activeChips[] = [
        'label' => 'Status: ' . option_label_by_id($statuses, $filters['status_id'], 'Status'),
        'removeUrl' => query_url($basePath, ['status_id' => ''], $filterBase),
    ];
}
if ($dueFrom !== '' || $dueTo !== '') {
    $activeChips[] = [
        'label' => 'Jatuh tempo: ' . ($dueFrom !== '' ? format_date_id($dueFrom) : '…') . ' – ' . ($dueTo !== '' ? format_date_id($dueTo) : '…'),
        'removeUrl' => query_url($basePath, ['due_from' => '', 'due_to' => ''], $filterBase),
    ];
}
$hasActiveFilters = $activeChips !== [];
\App\Core\View::partial('partials/collapsible_filter_start', [
    'activeChips' => $activeChips,
    'resetUrl' => url($basePath),
    'headingHtml' => '<h2 class="cases-list-title">Monitoring Petugas</h2>'
        . '<p class="cases-list-subtitle">Pilih petugas untuk melihat case yang sedang ditangani. Filter jenis, status, sumber, dan periode berlaku pada daftar case.</p>',
]);
?>
<form method="get" action="<?= e(url($basePath)) ?>" class="app-filter-panel monitoring-filters dash-filter-panel--embedded" id="officerFilterForm">
    <div class="app-filter-panel__header">
        <span class="app-filter-panel__icon" aria-hidden="true"><i class="bi bi-funnel"></i></span>
        <div>
            <h3 class="app-filter-panel__title">Filter Data</h3>
            <p class="app-filter-panel__desc">Saring data monitoring berdasarkan periode dan kriteria yang dipilih.</p>
        </div>
    </div>

    <div class="app-filter-grid app-filter-grid--officers">
        <div class="app-filter-field app-filter-field--period <?= ($createdFrom !== '' || $createdTo !== '') ? 'is-active' : '' ?>">
            <label class="app-filter-label">Periode Dibuat</label>
            <?php \App\Core\View::partial('partials/date_range_inputs', [
                'fromName' => 'created_from',
                'toName' => 'created_to',
                'fromValue' => $createdFrom,
                'toValue' => $createdTo,
            ]); ?>
        </div>

        <?php if ($isAdmin): ?>
        <div class="app-filter-field <?= !empty($filters['officer_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="off_filter_officer">Petugas</label>
            <select id="off_filter_officer" name="officer" class="form-select app-filter-control">
                <option value="">— Pilih petugas —</option>
                <?php foreach ($officers as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= (string) ($filters['officer_id'] ?? '') === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
            <input type="hidden" name="officer" value="<?= (int) ($filters['officer_id'] ?? 0) ?>">
        <?php endif; ?>

        <div class="app-filter-field <?= !empty($filters['case_type_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="off_filter_type">Jenis Kasus</label>
            <select id="off_filter_type" name="case_type_id" class="form-select app-filter-control">
                <option value="">Semua jenis</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (string) ($filters['case_type_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['source_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="off_filter_source">Sumber</label>
            <select id="off_filter_source" name="source_id" class="form-select app-filter-control">
                <option value="">Semua sumber</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?= (int) $src['id'] ?>" <?= (string) ($filters['source_id'] ?? '') === (string) $src['id'] ? 'selected' : '' ?>><?= e($src['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['status_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="off_filter_status">Status</label>
            <select id="off_filter_status" name="status_id" class="form-select app-filter-control">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['status_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field app-filter-field--period <?= ($dueFrom !== '' || $dueTo !== '') ? 'is-active' : '' ?>">
            <label class="app-filter-label">Periode Jatuh Tempo</label>
            <?php \App\Core\View::partial('partials/date_range_inputs', [
                'fromName' => 'due_from',
                'toName' => 'due_to',
                'fromValue' => $dueFrom,
                'toValue' => $dueTo,
            ]); ?>
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

<div class="panel-card mb-3">
    <div class="table-responsive">
        <table class="table master-table align-middle mb-0">
            <thead>
            <tr>
                <th>Petugas</th>
                <th>Total Aktif</th>
                <th>Normal</th>
                <th>H-5</th>
                <th>H-3</th>
                <th>Hari Ini</th>
                <th>Terlambat</th>
                <th>Selesai Bulan Ini</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($workload === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data petugas/case.</td></tr>
            <?php else: ?>
                <?php foreach ($workload as $w):
                    $active = (int) $selectedOfficerId === (int) $w['officer_id'];
                ?>
                    <tr class="<?= $active ? 'table-active' : '' ?>">
                        <td>
                            <a class="fw-semibold" href="<?= e(query_url($basePath, [
                                'officer' => (int) $w['officer_id'],
                                'officer_id' => (int) $w['officer_id'],
                                'page' => '',
                            ], $filters)) ?>">
                                <?= e($w['label']) ?>
                            </a>
                        </td>
                        <td><?= (int) $w['aktif'] ?></td>
                        <td><?= (int) $w['normal'] ?></td>
                        <td><?= (int) $w['h5'] ?></td>
                        <td><?= (int) $w['h3'] ?></td>
                        <td><?= (int) $w['today'] ?></td>
                        <td><?= (int) $w['overdue'] ?></td>
                        <td><?= (int) $w['selesai_bulan_ini'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($selectedOfficerId): ?>
    <div class="panel-card">
        <div class="panel-card-header mb-3">
            <h3>Case milik <?= e($detail['label'] ?? 'Petugas') ?></h3>
            <p>Diurutkan berdasarkan urgensi deadline. Tanggal tanpa jam.</p>
        </div>
        <?php if ($paginator === null || $paginator->total === 0): ?>
            <?php \App\Core\View::partial('partials/empty_state', [
                'title' => 'Tidak ada case',
                'hint' => 'Petugas ini belum memiliki case pada filter saat ini.',
            ]); ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table master-table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Nomor Kasus</th>
                        <th>Nama Wajib Pajak</th>
                        <th>Jenis Kasus</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th>Jatuh Tempo</th>
                        <th>Sisa waktu / Deadline</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($paginator->items as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($row['case_number']) ?></td>
                            <td><?= e($row['taxpayer_name']) ?></td>
                            <td><?= e($row['case_type_name']) ?></td>
                            <td><?= e($row['status_name']) ?></td>
                            <td><?= e(format_date_id($row['created_date'] ?? null)) ?></td>
                            <td><?= e(format_date_id($row['due_date'])) ?></td>
                            <td><?= deadline_badge($row['deadline']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/cases/' . $row['id'])) ?>">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'basePath' => $basePath]); ?>
        <?php endif; ?>
    </div>
<?php elseif ($isAdmin): ?>
    <div class="panel-card">
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => 'Pilih petugas',
            'hint' => 'Gunakan filter Petugas di atas, atau klik nama petugas pada ringkasan workload.',
        ]); ?>
    </div>
<?php endif; ?>
