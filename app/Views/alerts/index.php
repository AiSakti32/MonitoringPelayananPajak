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
 * @var string $emptyHint
 */
\App\Core\View::partial('partials/loading_overlay');

$deadline = (string) ($filters['deadline'] ?? 'all');
if ($deadline === 'alert') {
    $deadline = 'all';
}

$quickUrl = static function (string $key) use ($basePath, $filters): string {
    $params = $filters;
    $params['deadline'] = $key === 'all' ? 'all' : $key;
    unset($params['page']);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }
    return query_url($basePath, $params, []);
};

$filterBase = [
    'case_number' => $filters['case_number'] ?? '',
    'npwp' => $filters['npwp'] ?? '',
    'taxpayer_name' => $filters['taxpayer_name'] ?? '',
    'officer_id' => $filters['officer_id'] ?? '',
    'case_type_id' => $filters['case_type_id'] ?? '',
    'status_id' => $filters['status_id'] ?? '',
    'source_id' => $filters['source_id'] ?? '',
    'deadline' => ($deadline !== '' && $deadline !== 'all') ? $deadline : '',
];
$activeChips = [];
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
if (!empty($filters['status_id'])) {
    $activeChips[] = [
        'label' => 'Status: ' . option_label_by_id($statuses, $filters['status_id'], 'Status'),
        'removeUrl' => query_url($basePath, ['status_id' => ''], $filterBase),
    ];
}
if (!empty($filters['source_id'])) {
    $activeChips[] = [
        'label' => 'Sumber: ' . option_label_by_id($sources, $filters['source_id'], 'Sumber'),
        'removeUrl' => query_url($basePath, ['source_id' => ''], $filterBase),
    ];
}
if ($deadline !== '' && $deadline !== 'all') {
    $dlLabels = [
        'overdue' => 'Terlambat',
        'today' => 'Hari Ini',
        'h3' => 'H-3',
        'h5' => 'H-5',
    ];
    $activeChips[] = [
        'label' => 'Deadline: ' . ($dlLabels[$deadline] ?? $deadline),
        'removeUrl' => query_url($basePath, ['deadline' => ''], $filterBase),
    ];
}
$hasActiveFilters = $activeChips !== [];

ob_start();
?>
<div class="quick-filters" role="tablist" aria-label="Filter alert">
    <?php foreach ($quickFilters as $qf):
        $key = $qf['key'];
        $count = (int) ($counts[$key] ?? 0);
        $active = $deadline === $key || ($key === 'all' && ($deadline === 'all' || $deadline === ''));
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

$headingHtml = '<h2 class="cases-list-title">Alert / Perlu Tindakan</h2>'
    . '<p class="cases-list-subtitle">Kasus yang membutuhkan perhatian berdasarkan tingkat urgensi deadline.</p>'
    . '<p class="cases-list-helper" title="Data realtime dari database. Diurutkan Terlambat → Hari Ini → H-3 → H-5.">'
    . '<i class="bi bi-info-circle" aria-hidden="true"></i> Data per '
    . e(format_date_short_id(today_jakarta()))
    . ' • Prioritas: Terlambat → Hari Ini → H-3 → H-5'
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
            <p class="app-filter-panel__desc">Saring alert berdasarkan kriteria kasus yang membutuhkan perhatian.</p>
        </div>
    </div>

    <div class="app-filter-grid app-filter-grid--monitoring">
        <div class="app-filter-field <?= trim((string) ($filters['case_number'] ?? '')) !== '' ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_case_number">Nomor Kasus</label>
            <input type="search" id="alert_filter_case_number" name="case_number" class="form-control app-filter-control"
                   value="<?= e((string) ($filters['case_number'] ?? '')) ?>" placeholder="P0000000001">
        </div>

        <div class="app-filter-field <?= trim((string) ($filters['npwp'] ?? '')) !== '' ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_npwp">NPWP</label>
            <input type="search" id="alert_filter_npwp" name="npwp" class="form-control app-filter-control"
                   value="<?= e((string) ($filters['npwp'] ?? '')) ?>" placeholder="16 digit">
        </div>

        <div class="app-filter-field <?= trim((string) ($filters['taxpayer_name'] ?? '')) !== '' ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_taxpayer">Nama WP</label>
            <input type="search" id="alert_filter_taxpayer" name="taxpayer_name" class="form-control app-filter-control"
                   value="<?= e((string) ($filters['taxpayer_name'] ?? '')) ?>" placeholder="Nama wajib pajak">
        </div>

        <?php if ($isAdmin): ?>
        <div class="app-filter-field <?= !empty($filters['officer_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_officer">Petugas</label>
            <select id="alert_filter_officer" name="officer" class="form-select app-filter-control">
                <option value="">Semua petugas</option>
                <?php foreach ($officers as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= (string) ($filters['officer_id'] ?? '') === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="app-filter-field <?= !empty($filters['case_type_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_type">Jenis Kasus</label>
            <select id="alert_filter_type" name="case_type_id" class="form-select app-filter-control">
                <option value="">Semua</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (string) ($filters['case_type_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['status_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_status">Status</label>
            <select id="alert_filter_status" name="status_id" class="form-select app-filter-control">
                <option value="">Semua</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['status_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= !empty($filters['source_id']) ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_source">Sumber</label>
            <select id="alert_filter_source" name="source_id" class="form-select app-filter-control">
                <option value="">Semua</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?= (int) $src['id'] ?>" <?= (string) ($filters['source_id'] ?? '') === (string) $src['id'] ? 'selected' : '' ?>><?= e($src['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="app-filter-field <?= ($deadline !== '' && $deadline !== 'all') ? 'is-active' : '' ?>">
            <label class="app-filter-label" for="alert_filter_deadline">Deadline</label>
            <select id="alert_filter_deadline" name="deadline" class="form-select app-filter-control">
                <?php
                $dlOpts = [
                    'all' => 'Semua Alert',
                    'overdue' => 'Terlambat',
                    'today' => 'Hari Ini',
                    'h3' => 'H-3',
                    'h5' => 'H-5',
                ];
                foreach ($dlOpts as $k => $label):
                ?>
                    <option value="<?= e($k) ?>" <?= $deadline === $k ? 'selected' : '' ?>><?= e($label) ?></option>
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

<div class="panel-card cases-table-card alerts-table-card">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => 'Tidak ada kasus yang memerlukan tindakan.',
            'hint' => $emptyHint !== '' ? $emptyHint : 'Tidak ada deadline dalam kategori ini.',
            'actionUrl' => $hasActiveFilters ? $basePath : null,
            'actionLabel' => $hasActiveFilters ? 'Reset Filter' : null,
        ]); ?>
    <?php else: ?>
        <div class="table-responsive cases-table-wrap">
            <table class="table cases-table alerts-table monitoring-table align-middle mb-0">
                <thead>
                <tr>
                    <th class="col-indicator">Prioritas</th>
                    <th class="col-case-number">Nomor Kasus</th>
                    <th class="col-taxpayer">Wajib Pajak</th>
                    <th class="col-case-type">Jenis Permohonan</th>
                    <th class="col-officer">Petugas</th>
                    <th class="col-status">Status</th>
                    <th class="col-date">Jatuh Tempo</th>
                    <th class="col-actions text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row):
                    $dl = $row['deadline'];
                    $dlKey = (string) ($dl['key'] ?? '');
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
                    $dueUrgent = in_array($dlKey, ['overdue', 'today'], true);
                ?>
                    <tr class="row-deadline tone-<?= e($dl['tone'] ?? 'critical') ?>">
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
                        <td class="col-officer">
                            <span class="cases-officer"><?= e((string) ($row['officer_name'] ?? '—')) ?></span>
                        </td>
                        <td class="col-status">
                            <span class="cases-status-badge tone-<?= e($statusTone) ?>"><?= e($statusName) ?></span>
                        </td>
                        <td class="col-date">
                            <span class="cases-date<?= $dueUrgent ? ' cases-date--urgent' : '' ?>"><?= e(format_date_short_id($row['due_date'] ?? null)) ?></span>
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
