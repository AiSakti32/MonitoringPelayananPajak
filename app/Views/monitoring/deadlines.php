<?php
/**
 * @var \App\Core\Paginator|null $paginator
 * @var array $filters
 * @var array $counts
 * @var array $quickFilters
 * @var string|null $loadError
 * @var string $basePath
 * @var bool $isAdmin
 * @var string $todayLabel
 */
\App\Core\View::partial('partials/loading_overlay');

$deadline = (string) ($filters['deadline'] ?? 'all');

$quickUrl = static function (string $key) use ($basePath, $filters): string {
    $params = $filters;
    $params['deadline'] = $key;
    unset($params['page']);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }
    return query_url($basePath, $params, []);
};

$createdFrom = trim((string) ($filters['created_from'] ?? ''));
$createdTo = trim((string) ($filters['created_to'] ?? ''));
$dueFrom = trim((string) ($filters['due_from'] ?? ''));
$dueTo = trim((string) ($filters['due_to'] ?? ''));
$filterBase = [
    'case_number' => $filters['case_number'] ?? '',
    'npwp' => $filters['npwp'] ?? '',
    'taxpayer_name' => $filters['taxpayer_name'] ?? '',
    'officer_id' => $filters['officer_id'] ?? '',
    'case_type_id' => $filters['case_type_id'] ?? '',
    'status_id' => $filters['status_id'] ?? '',
    'source_id' => $filters['source_id'] ?? '',
    'deadline' => ($deadline !== '' && $deadline !== 'all') ? $deadline : '',
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
if ($deadline !== '' && $deadline !== 'all') {
    $dlLabels = [
        'overdue' => 'Terlambat',
        'today' => 'Hari Ini',
        'h3' => 'Kritis H-3',
        'h5' => 'Waspada H-5',
        'normal' => 'Normal',
        'selesai' => 'Selesai',
    ];
    $activeChips[] = [
        'label' => 'Deadline: ' . ($dlLabels[$deadline] ?? $deadline),
        'removeUrl' => query_url($basePath, ['deadline' => ''], $filterBase),
    ];
}
if ($dueFrom !== '' || $dueTo !== '') {
    $activeChips[] = [
        'label' => 'Jatuh tempo: ' . ($dueFrom !== '' ? format_date_id($dueFrom) : '…') . ' – ' . ($dueTo !== '' ? format_date_id($dueTo) : '…'),
        'removeUrl' => query_url($basePath, ['due_from' => '', 'due_to' => ''], $filterBase),
    ];
}
if (trim((string) ($filters['case_number'] ?? '')) !== '') {
    $activeChips[] = [
        'label' => 'Nomor: ' . trim((string) $filters['case_number']),
        'removeUrl' => query_url($basePath, ['case_number' => ''], $filterBase),
    ];
}
if (trim((string) ($filters['npwp'] ?? '')) !== '') {
    $activeChips[] = [
        'label' => 'NPWP: ' . trim((string) $filters['npwp']),
        'removeUrl' => query_url($basePath, ['npwp' => ''], $filterBase),
    ];
}
if (trim((string) ($filters['taxpayer_name'] ?? '')) !== '') {
    $activeChips[] = [
        'label' => 'WP: ' . trim((string) $filters['taxpayer_name']),
        'removeUrl' => query_url($basePath, ['taxpayer_name' => ''], $filterBase),
    ];
}
$hasActiveFilters = $activeChips !== [];

ob_start();
?>
<div class="quick-filters" role="tablist" aria-label="Filter cepat deadline">
    <?php foreach ($quickFilters as $qf):
        $key = $qf['key'];
        $countKey = $key === 'all' ? 'all' : $key;
        $count = (int) ($counts[$countKey] ?? 0);
        $active = $deadline === $key;
    ?>
        <a href="<?= e($quickUrl($key)) ?>"
           class="quick-filter tone-<?= e($qf['tone']) ?> <?= $active ? 'active' : '' ?>"
           role="tab"
           aria-selected="<?= $active ? 'true' : 'false' ?>">
            <span class="qf-label"><?= e($qf['label']) ?></span>
            <span class="qf-count"><?= $count ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php
$quickFiltersHtml = ob_get_clean();

$headingHtml = '<h2 class="cases-list-title">Monitoring Permohonan</h2>'
    . '<p class="cases-list-subtitle">Pantau deadline seluruh permohonan berdasarkan tingkat urgensi.</p>'
    . '<p class="cases-list-helper" title="Case Selesai tidak masuk kategori terlambat/H-3/H-5. Diurutkan dari yang paling mendesak.">'
    . '<i class="bi bi-info-circle" aria-hidden="true"></i> Data per '
    . e(format_date_short_id(today_jakarta()))
    . ' • Zona waktu Asia/Jakarta'
    . '</p>';

\App\Core\View::partial('partials/collapsible_filter_start', [
    'activeChips' => $activeChips,
    'resetUrl' => url($basePath),
    'headingHtml' => $headingHtml,
    'afterToolbarHtml' => $quickFiltersHtml,
]);
?>
<form method="get" action="<?= e(url($basePath)) ?>" class="app-filter-panel monitoring-filters dash-filter-panel--embedded" id="masterFilterForm">
    <div class="app-filter-panel__header">
        <span class="app-filter-panel__icon" aria-hidden="true"><i class="bi bi-funnel"></i></span>
        <div>
            <h3 class="app-filter-panel__title">Filter Data</h3>
            <p class="app-filter-panel__desc">Saring data monitoring berdasarkan periode dan kriteria yang dipilih.</p>
        </div>
    </div>

    <div class="app-filter-grid app-filter-grid--monitoring">
        <div class="app-filter-field app-filter-field--span-2 <?= ($createdFrom !== '' || $createdTo !== '') ? 'is-active' : '' ?>">
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
            <label class="app-filter-label" for="mon_filter_officer">Petugas</label>
            <select id="mon_filter_officer" name="officer" class="form-select app-filter-control">
                <option value="">Semua petugas</option>
                <?php foreach ($officers as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= (string) ($filters['officer_id'] ?? '') === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="app-filter-field <?= !empty($filters['case_type_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="mon_filter_type">Jenis Kasus</label>
            <select id="mon_filter_type" name="case_type_id" class="form-select app-filter-control">
                <option value="">Semua jenis</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (string) ($filters['case_type_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['source_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="mon_filter_source">Sumber</label>
            <select id="mon_filter_source" name="source_id" class="form-select app-filter-control">
                <option value="">Semua sumber</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?= (int) $src['id'] ?>" <?= (string) ($filters['source_id'] ?? '') === (string) $src['id'] ? 'selected' : '' ?>><?= e($src['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['status_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="mon_filter_status">Status</label>
            <select id="mon_filter_status" name="status_id" class="form-select app-filter-control">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['status_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= ($deadline !== '' && $deadline !== 'all') ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="mon_filter_deadline">Deadline</label>
            <select id="mon_filter_deadline" name="deadline" class="form-select app-filter-control">
                <?php
                $dlOpts = [
                    'all' => 'Semua',
                    'overdue' => 'Terlambat',
                    'today' => 'Hari Ini',
                    'h3' => 'Kritis H-3',
                    'h5' => 'Waspada H-5',
                    'normal' => 'Normal',
                    'selesai' => 'Selesai',
                ];
                foreach ($dlOpts as $k => $label):
                ?>
                    <option value="<?= e($k) ?>" <?= $deadline === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field app-filter-field--span-2 <?= ($dueFrom !== '' || $dueTo !== '') ? 'is-active' : '' ?>">
            <label class="app-filter-label">Periode Jatuh Tempo</label>
            <?php \App\Core\View::partial('partials/date_range_inputs', [
                'fromName' => 'due_from',
                'toName' => 'due_to',
                'fromValue' => $dueFrom,
                'toValue' => $dueTo,
                'fromLabel' => 'Tanggal mulai',
                'toLabel' => 'Tanggal akhir',
            ]); ?>
        </div>

        <div class="app-filter-field <?= trim((string) ($filters['case_number'] ?? '')) !== '' ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="mon_filter_case_number">Nomor Kasus</label>
            <input type="search" id="mon_filter_case_number" name="case_number" class="form-control app-filter-control"
                   value="<?= e((string) ($filters['case_number'] ?? '')) ?>" placeholder="P0000000001">
        </div>

        <div class="app-filter-field <?= trim((string) ($filters['npwp'] ?? '')) !== '' ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="mon_filter_npwp">NPWP</label>
            <input type="search" id="mon_filter_npwp" name="npwp" class="form-control app-filter-control"
                   value="<?= e((string) ($filters['npwp'] ?? '')) ?>" placeholder="16 digit">
        </div>

        <div class="app-filter-field <?= trim((string) ($filters['taxpayer_name'] ?? '')) !== '' ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="mon_filter_taxpayer">Nama WP</label>
            <input type="search" id="mon_filter_taxpayer" name="taxpayer_name" class="form-control app-filter-control"
                   value="<?= e((string) ($filters['taxpayer_name'] ?? '')) ?>" placeholder="Nama wajib pajak">
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

<div class="panel-card cases-table-card monitoring-table-card">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => 'Tidak ada case pada filter ini',
            'hint' => match ((string) ($filters['deadline'] ?? 'all')) {
                'overdue' => 'Tidak ada case terlambat saat ini.',
                'today' => 'Tidak ada case jatuh tempo hari ini.',
                'h3' => 'Tidak ada case H-3 saat ini.',
                'h5' => 'Tidak ada case H-5 saat ini.',
                'normal' => 'Tidak ada case dengan sisa waktu normal saat ini.',
                'selesai' => 'Tidak ada case selesai pada filter ini.',
                'active' => 'Tidak ada case aktif pada filter ini.',
                default => 'Ubah filter atau input kasus baru.',
            },
            'actionUrl' => $hasActiveFilters ? $basePath : '/cases/create',
            'actionLabel' => $hasActiveFilters ? 'Reset Filter' : 'Input Kasus',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive cases-table-wrap">
            <table class="table cases-table monitoring-table align-middle mb-0">
                <thead>
                <tr>
                    <th class="col-indicator">Indikator</th>
                    <th class="col-case-number">Nomor Kasus</th>
                    <th class="col-taxpayer">Wajib Pajak</th>
                    <th class="col-case-type">Jenis Permohonan</th>
                    <th class="col-status">Status</th>
                    <th class="col-source">Sumber</th>
                    <th class="col-officer">Petugas</th>
                    <th class="col-date">Dibuat</th>
                    <th class="col-date">Jatuh Tempo</th>
                    <th class="col-actions text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row):
                    $dl = $row['deadline'];
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
                    <tr class="row-deadline tone-<?= e($dl['tone'] ?? 'normal') ?>">
                        <td class="col-indicator">
                            <?= deadline_badge($dl) ?>
                        </td>
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
                        <td class="col-officer">
                            <span class="cases-officer"><?= e((string) ($row['officer_name'] ?? '—')) ?></span>
                        </td>
                        <td class="col-date">
                            <span class="cases-date"><?= e(format_date_short_id($row['created_date'] ?? null)) ?></span>
                        </td>
                        <td class="col-date">
                            <span class="cases-date"><?= e(format_date_short_id($row['due_date'] ?? null)) ?></span>
                        </td>
                        <td class="col-actions text-end">
                            <a class="cases-action-link" href="<?= e(url('/cases/' . $caseId)) ?>">Detail →</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'basePath' => $basePath]); ?>
    <?php endif; ?>
</div>
