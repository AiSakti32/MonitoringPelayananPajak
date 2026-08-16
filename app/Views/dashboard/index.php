<?php
/**
 * @var array $kpi
 * @var array $charts
 * @var array $tables
 * @var array $filters
 * @var array $links
 * @var array|null $user
 * @var bool $isAdmin
 * @var bool $isEmpty
 * @var string|null $loadError
 * @var array $meta
 * @var string $todayLabel
 */
\App\Core\View::partial('partials/loading_overlay');

$meta = $meta ?? [
    'workload_shown' => count($tables['workload'] ?? []),
    'workload_total' => count($tables['workload'] ?? []),
    'workload_limit' => 10,
    'types_limit' => 10,
    'top_types_limit' => 5,
];
$workloadTruncated = (int) ($meta['workload_total'] ?? 0) > (int) ($meta['workload_shown'] ?? 0);
$kpiCards = [
    ['key' => 'active', 'label' => 'Total Case Aktif', 'help' => 'Belum selesai', 'icon' => 'bi-briefcase', 'tone' => 'navy', 'href' => $links['active']],
    ['key' => 'dibuat', 'label' => 'Case Baru', 'help' => 'Status Dibuat', 'icon' => 'bi-file-earmark-plus', 'tone' => 'blue', 'href' => $links['dibuat']],
    ['key' => 'diproses', 'label' => 'Diproses', 'help' => 'Sedang dikerjakan', 'icon' => 'bi-arrow-repeat', 'tone' => 'blue', 'href' => $links['diproses']],
    ['key' => 'selesai', 'label' => 'Selesai', 'help' => 'Sudah diselesaikan', 'icon' => 'bi-check2-circle', 'tone' => 'green', 'href' => $links['selesai']],
    ['key' => 'h5', 'label' => 'Waspada H-5', 'help' => 'Sisa 4–5 hari', 'icon' => 'bi-exclamation-triangle', 'tone' => 'amber', 'href' => $links['h5']],
    ['key' => 'h3', 'label' => 'Kritis H-3', 'help' => 'Sisa 1–3 hari', 'icon' => 'bi-exclamation-octagon', 'tone' => 'red', 'href' => $links['h3']],
    ['key' => 'today', 'label' => 'Jatuh Tempo Hari Ini', 'help' => 'Due hari ini', 'icon' => 'bi-calendar-event', 'tone' => 'red', 'href' => $links['today']],
    ['key' => 'overdue', 'label' => 'Terlambat', 'help' => 'Melewati jatuh tempo', 'icon' => 'bi-clock-history', 'tone' => 'darkred', 'href' => $links['overdue']],
];
?>
<div class="page-intro dash-page-intro mb-2 d-flex flex-wrap justify-content-between gap-2 align-items-center">
    <div>
        <h2 class="dashboard-greeting mb-0">Selamat datang, <?= e($user['full_name'] ?? '') ?></h2>
        <p class="text-muted mb-0 dash-page-intro__sub">Ringkasan monitoring per <strong><?= e($todayLabel) ?></strong>. Klik kartu KPI untuk membuka daftar terkait.</p>
    </div>
    <a href="<?= e($links['monitoring']) ?>" class="btn btn-outline-secondary btn-sm">Monitoring Deadline</a>
</div>

<?php if ($loadError): ?>
    <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => '/dashboard']); ?>
<?php endif; ?>

<?php
// Active filter chips (display only — values already applied via GET)
$activeChips = [];
$from = trim((string) ($filters['created_from'] ?? ''));
$to = trim((string) ($filters['created_to'] ?? ''));
$filterBase = [
    'created_from' => $filters['created_from'] ?? '',
    'created_to' => $filters['created_to'] ?? '',
    'officer_id' => $filters['officer_id'] ?? '',
    'case_type_id' => $filters['case_type_id'] ?? '',
    'source_id' => $filters['source_id'] ?? '',
    'status_id' => $filters['status_id'] ?? '',
];

if ($from !== '' || $to !== '') {
    $fromLabel = $from !== '' ? format_date_id($from) : '…';
    $toLabel = $to !== '' ? format_date_id($to) : '…';
    $activeChips[] = [
        'key' => 'periode',
        'label' => $fromLabel . ' – ' . $toLabel,
        'removeUrl' => query_url('/dashboard', ['created_from' => '', 'created_to' => ''], $filterBase),
    ];
}
if (!empty($filters['officer_id'])) {
    $officerLabel = 'Petugas';
    foreach ($officers as $o) {
        if ((string) $o['id'] === (string) $filters['officer_id']) {
            $officerLabel = 'Petugas: ' . $o['name'];
            break;
        }
    }
    $activeChips[] = [
        'key' => 'officer',
        'label' => $officerLabel,
        'removeUrl' => query_url('/dashboard', ['officer_id' => ''], $filterBase),
    ];
}
if (!empty($filters['case_type_id'])) {
    $typeLabel = 'Jenis Kasus';
    foreach ($types as $t) {
        if ((string) $t['id'] === (string) $filters['case_type_id']) {
            $typeLabel = 'Jenis: ' . $t['name'];
            break;
        }
    }
    $activeChips[] = [
        'key' => 'type',
        'label' => $typeLabel,
        'removeUrl' => query_url('/dashboard', ['case_type_id' => ''], $filterBase),
    ];
}
if (!empty($filters['source_id'])) {
    $sourceLabel = 'Sumber';
    foreach ($sources as $src) {
        if ((string) $src['id'] === (string) $filters['source_id']) {
            $sourceLabel = 'Sumber: ' . $src['name'];
            break;
        }
    }
    $activeChips[] = [
        'key' => 'source',
        'label' => $sourceLabel,
        'removeUrl' => query_url('/dashboard', ['source_id' => ''], $filterBase),
    ];
}
if (!empty($filters['status_id'])) {
    $statusLabel = 'Status';
    foreach ($statuses as $s) {
        if ((string) $s['id'] === (string) $filters['status_id']) {
            $statusLabel = 'Status: ' . $s['name'];
            break;
        }
    }
    $activeChips[] = [
        'key' => 'status',
        'label' => $statusLabel,
        'removeUrl' => query_url('/dashboard', ['status_id' => ''], $filterBase),
    ];
}
$activeFilterCount = count($activeChips);
\App\Core\View::partial('partials/collapsible_filter_start', [
    'activeChips' => $activeChips,
    'resetUrl' => url('/dashboard'),
]);
?>
            <form method="get" action="<?= e(url('/dashboard')) ?>" class="app-filter-panel dash-filter-panel dash-filter-panel--embedded" id="masterFilterForm">
                <div class="app-filter-panel__header">
                    <span class="app-filter-panel__icon" aria-hidden="true"><i class="bi bi-funnel"></i></span>
                    <div>
                        <h3 class="app-filter-panel__title">Filter Data</h3>
                        <p class="app-filter-panel__desc">Saring data monitoring berdasarkan periode dan kriteria yang dipilih.</p>
                    </div>
                </div>

                <div class="app-filter-grid dash-filter-grid">
                    <div class="app-filter-field app-filter-field--period dash-filter-field--period <?= ($from !== '' || $to !== '') ? 'is-active' : '' ?>">
                        <label class="app-filter-label">Periode Dibuat</label>
                        <?php \App\Core\View::partial('partials/date_range_inputs', [
                            'fromName' => 'created_from',
                            'toName' => 'created_to',
                            'fromValue' => (string) ($filters['created_from'] ?? ''),
                            'toValue' => (string) ($filters['created_to'] ?? ''),
                            'fromId' => 'created_from',
                            'toId' => 'created_to',
                        ]); ?>
                    </div>

                    <?php if ($isAdmin): ?>
                    <div class="app-filter-field <?= !empty($filters['officer_id']) ? 'is-active' : '' ?>">
                        <label class="app-filter-label" for="filter_officer">Petugas</label>
                        <select id="filter_officer" name="officer_id" class="form-select app-filter-control searchable" data-placeholder="Semua petugas">
                            <option value="">Semua petugas</option>
                            <?php foreach ($officers as $o): ?>
                                <option value="<?= (int) $o['id'] ?>" <?= (string) ($filters['officer_id'] ?? '') === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="app-filter-field <?= !empty($filters['case_type_id']) ? 'is-active' : '' ?>">
                        <label class="app-filter-label" for="filter_type">Jenis Kasus</label>
                        <select id="filter_type" name="case_type_id" class="form-select app-filter-control searchable" data-placeholder="Semua jenis">
                            <option value="">Semua jenis</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (string) ($filters['case_type_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="app-filter-field <?= !empty($filters['source_id']) ? 'is-active' : '' ?>">
                        <label class="app-filter-label" for="filter_source">Sumber</label>
                        <select id="filter_source" name="source_id" class="form-select app-filter-control searchable" data-placeholder="Semua sumber">
                            <option value="">Semua sumber</option>
                            <?php foreach ($sources as $src): ?>
                                <option value="<?= (int) $src['id'] ?>" <?= (string) ($filters['source_id'] ?? '') === (string) $src['id'] ? 'selected' : '' ?>><?= e($src['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="app-filter-field <?= !empty($filters['status_id']) ? 'is-active' : '' ?>">
                        <label class="app-filter-label" for="filter_status">Status</label>
                        <select id="filter_status" name="status_id" class="form-select app-filter-control searchable" data-placeholder="Semua status">
                            <option value="">Semua status</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['status_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="app-filter-panel__footer">
                    <a href="<?= e(url('/dashboard')) ?>"
                       class="btn app-filter-btn app-filter-btn--ghost<?= $activeChips !== [] ? '' : ' is-muted' ?>">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        Reset filter
                    </a>
                    <button type="submit" class="btn app-filter-btn app-filter-btn--primary" data-loading-btn>Terapkan Filter</button>
                </div>
            </form>
<?php \App\Core\View::partial('partials/collapsible_filter_end'); ?>

<div class="row g-3 kpi-grid">
    <?php foreach ($kpiCards as $card):
        $value = (int) ($kpi[$card['key']] ?? 0);
    ?>
    <div class="col-6 col-md-4 col-xl-3">
        <a class="kpi-card kpi-link tone-<?= e($card['tone']) ?>" href="<?= e($card['href']) ?>">
            <div class="kpi-icon"><i class="bi <?= e($card['icon']) ?>"></i></div>
            <div class="kpi-body">
                <span class="kpi-label"><?= e($card['label']) ?></span>
                <strong class="kpi-value"><?= e((string) $value) ?></strong>
                <span class="kpi-help"><?= e($card['help']) ?></span>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($isEmpty && !$loadError): ?>
    <div class="panel-card mt-3">
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => 'Belum ada data kasus',
            'hint' => 'Dashboard menampilkan angka 0 dari database. Input kasus untuk melihat grafik dan tabel.',
            'actionUrl' => '/cases/create',
            'actionLabel' => 'Input Kasus',
        ]); ?>
    </div>
<?php endif; ?>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="panel-card h-100">
            <div class="panel-card-header">
                <h3>Case per Status</h3>
                <p>Klik segmen/legend untuk membuka daftar.</p>
            </div>
            <div class="chart-wrap chart-wrap-donut">
                <canvas id="chartStatus"></canvas>
            </div>
            <?php if ($charts['status'] === []): ?>
                <div class="empty-state py-3"><p class="mb-0">Tidak ada data.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel-card h-100">
            <div class="panel-card-header">
                <h3>Jenis Permohonan</h3>
                <p>Top <?= (int) ($meta['types_limit'] ?? 10) ?> jenis berdasarkan jumlah case.</p>
            </div>
            <div class="chart-wrap chart-wrap-dynamic" data-chart-rows="<?= count($charts['types'] ?? []) ?>">
                <canvas id="chartTypes"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel-card h-100">
            <div class="panel-card-header">
                <h3>5 Kelompok Prioritas</h3>
                <p>Agregasi kelompok utama (Portal/Core digabung).</p>
            </div>
            <div class="chart-wrap chart-wrap-dynamic" data-chart-rows="<?= count($charts['priority'] ?? []) ?>">
                <canvas id="chartPriority"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="panel-card h-100">
            <div class="panel-card-header d-flex justify-content-between align-items-start">
                <div>
                    <h3>Workload per Petugas</h3>
                    <p>
                        Case aktif, diurutkan dari workload tertinggi.
                        <?php if ($workloadTruncated): ?>
                            Menampilkan top <?= (int) $meta['workload_shown'] ?> dari <?= (int) $meta['workload_total'] ?> petugas.
                        <?php else: ?>
                            Klik nama petugas untuk melihat casenya.
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?= e($links['officers']) ?>" class="btn btn-sm btn-outline-secondary">Semua petugas</a>
            </div>
            <div class="chart-wrap chart-wrap-dynamic mb-3" data-chart-rows="<?= count($charts['workload'] ?? []) ?>">
                <canvas id="chartWorkload"></canvas>
            </div>
            <?php if ($tables['workload'] === []): ?>
                <div class="empty-state py-3"><p class="mb-0">Belum ada workload.</p></div>
            <?php else: ?>
                <div class="table-responsive dashboard-table-scroll">
                    <table class="table master-table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Petugas</th>
                            <th>Aktif</th>
                            <th>H-5</th>
                            <th>H-3</th>
                            <th>Terlambat</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tables['workload'] as $w): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="<?= e(query_url('/cases', ['officer' => $w['officer_id']], [])) ?>">
                                        <?= e($w['label']) ?>
                                    </a>
                                </td>
                                <td><?= (int) $w['value'] ?></td>
                                <td><?= (int) $w['h5'] ?></td>
                                <td><?= (int) $w['h3'] ?></td>
                                <td><?= (int) $w['overdue'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($workloadTruncated): ?>
                    <div class="dashboard-more-note mt-2">
                        <a href="<?= e($links['officers']) ?>">Lihat semua <?= (int) $meta['workload_total'] ?> petugas di Monitoring Petugas →</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel-card h-100">
            <div class="panel-card-header">
                <h3>Top Jenis Permohonan</h3>
                <p><?= (int) ($meta['top_types_limit'] ?? 5) ?> teratas berdasarkan jumlah case.</p>
            </div>
            <?php if ($tables['top_types'] === []): ?>
                <div class="empty-state py-4"><p class="mb-0">Belum ada data.</p></div>
            <?php else: ?>
                <ol class="top-list">
                    <?php foreach ($tables['top_types'] as $i => $t): ?>
                        <li>
                            <a href="<?= e(query_url('/cases', array_filter(['case_type_id' => $t['case_type_id'], 'officer_id' => $filters['officer_id'] ?? null, 'source_id' => $filters['source_id'] ?? null]), [])) ?>">
                                <span class="top-rank"><?= $i + 1 ?></span>
                                <span class="top-label"><?= e($t['label']) ?></span>
                                <strong class="top-value"><?= (int) $t['value'] ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="panel-card h-100">
            <div class="panel-card-header d-flex justify-content-between">
                <div>
                    <h3>Case Mendekati Jatuh Tempo</h3>
                    <p>Hari ini → H-3 → H-5</p>
                </div>
                <a href="<?= e($links['h3']) ?>" class="btn btn-sm btn-outline-secondary">Lihat H-3</a>
            </div>
            <?php \App\Core\View::partial('dashboard/_case_mini_table', ['rows' => $tables['approaching']]); ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card h-100">
            <div class="panel-card-header d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h3>Case Terlambat</h3>
                    <p>Sudah melewati jatuh tempo dan belum selesai</p>
                </div>
                <a href="<?= e($links['overdue']) ?>" class="btn btn-sm btn-outline-danger">Semua terlambat</a>
            </div>
            <?php \App\Core\View::partial('dashboard/_case_mini_table', ['rows' => $tables['overdue']]); ?>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="panel-card">
            <div class="panel-card-header">
                <h3>Aktivitas Terbaru</h3>
                <p>Perubahan case terbaru — siapa mengubah apa, dan menjadi apa.</p>
            </div>
            <?php if ($tables['recent'] === []): ?>
                <div class="empty-state py-4"><p class="mb-0">Belum ada aktivitas.</p></div>
            <?php else: ?>
                <div class="activity-feed">
                    <?php foreach ($tables['recent'] as $act):
                        $eventType = (string) ($act['event_type'] ?? '');
                        $eventTone = match ($eventType) {
                            'CREATED' => 'created',
                            'STATUS_CHANGED', 'REOPENED' => 'status',
                            'ASSIGNED' => 'assign',
                            default => 'update',
                        };
                        $changes = is_array($act['changes'] ?? null) ? $act['changes'] : [];
                    ?>
                        <article class="activity-card tone-<?= e($eventTone) ?>">
                            <div class="activity-card-mark" aria-hidden="true">
                                <?php if ($eventTone === 'created'): ?>
                                    <i class="bi bi-plus-lg"></i>
                                <?php elseif ($eventTone === 'status'): ?>
                                    <i class="bi bi-arrow-left-right"></i>
                                <?php elseif ($eventTone === 'assign'): ?>
                                    <i class="bi bi-person-check"></i>
                                <?php else: ?>
                                    <i class="bi bi-pencil-square"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-card-main">
                                <div class="activity-card-top">
                                    <span class="activity-badge"><?= e((string) ($act['event_label'] ?? 'Perubahan')) ?></span>
                                    <time datetime="<?= e((string) ($act['created_at'] ?? '')) ?>"><?= e(format_datetime_id($act['created_at'] ?? null)) ?></time>
                                </div>
                                <p class="activity-headline">
                                    <strong><?= e((string) ($act['actor'] ?? 'Sistem')) ?></strong>
                                    <?php
                                    $verb = match ($eventType) {
                                        'CREATED' => 'membuat kasus',
                                        'STATUS_CHANGED' => 'mengubah status',
                                        'ASSIGNED' => 'mengganti petugas',
                                        'REOPENED' => 'membuka kembali',
                                        default => 'memperbarui',
                                    };
                                    echo ' ' . e($verb) . ' ';
                                    ?>
                                    <a href="<?= e(url('/cases/' . ($act['case_id'] ?? 0))) ?>"><?= e((string) ($act['case_number'] ?? '')) ?></a>
                                </p>
                                <?php if ($changes !== []): ?>
                                    <ul class="activity-changes">
                                        <?php foreach (array_slice($changes, 0, 4) as $change): ?>
                                            <li>
                                                <span class="activity-change-label"><?= e((string) ($change['label'] ?? '')) ?></span>
                                                <span class="activity-change-old"><?= e((string) ($change['old'] ?? '—')) ?></span>
                                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                                <span class="activity-change-new"><?= e((string) ($change['new'] ?? '—')) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (!empty($act['note'])): ?>
                                    <p class="activity-note mb-0"><i class="bi bi-chat-left-text"></i> <?= e((string) $act['note']) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.KAJANG_DASHBOARD = {
  status: <?= json_encode($charts['status'], JSON_UNESCAPED_UNICODE) ?>,
  types: <?= json_encode($charts['types'], JSON_UNESCAPED_UNICODE) ?>,
  priority: <?= json_encode($charts['priority'], JSON_UNESCAPED_UNICODE) ?>,
  workload: <?= json_encode($charts['workload'], JSON_UNESCAPED_UNICODE) ?>,
  links: {
    casesBase: <?= json_encode(url('/cases')) ?>,
    monitoringBase: <?= json_encode(url('/monitoring/deadlines')) ?>,
    officersBase: <?= json_encode(url('/monitoring/officers')) ?>,
    filters: <?= json_encode(array_filter($filters, static fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_UNICODE) ?>
  }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= e(asset('js/dashboard.js')) ?>"></script>
