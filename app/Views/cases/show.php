<?php
/**
 * @var array $case
 * @var list<array> $histories
 * @var array $deadline
 * @var int|null $daysRemaining
 * @var string $timelineOrder
 */

$tone = $deadline['tone'] ?? 'normal';
$deadlineLabel = $deadline['label'] ?? '—';

if ($deadline['key'] === 'selesai') {
    $sisaText = 'Selesai';
} elseif ($daysRemaining === null) {
    $sisaText = '—';
} elseif ($daysRemaining < 0) {
    $sisaText = 'Terlambat ' . abs($daysRemaining) . ' hari';
} elseif ($daysRemaining === 0) {
    $sisaText = 'Jatuh tempo hari ini';
} else {
    $sisaText = 'Sisa ' . $daysRemaining . ' hari';
}
?>
<div class="case-detail-page">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <a href="<?= e(url('/cases')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Daftar kasus</a>
        <div class="d-flex gap-2">
            <a href="<?= e(url('/cases/' . $case['id'] . '/edit')) ?>" class="btn btn-primary btn-sm">Perbarui Kasus</a>
            <a href="<?= e(url('/cases/create')) ?>" class="btn btn-outline-secondary btn-sm">Simpan kasus lain</a>
        </div>
    </div>

    <!-- Header -->
    <section class="panel-card case-detail-header tone-<?= e($tone) ?> mb-3">
        <div class="case-detail-header-grid">
            <div>
                <div class="text-muted small text-uppercase letter-space">Nomor Kasus</div>
                <h1 class="case-number-title mb-2"><?= e($case['case_number']) ?></h1>
                <div class="case-header-meta">
                    <span class="badge badge-status-lg"><?= e($case['status_name']) ?></span>
                    <?= deadline_badge($deadline) ?>
                </div>
            </div>
            <div class="case-header-side">
                <div class="case-officer-block">
                    <span class="text-muted small d-block">Petugas</span>
                    <strong><?= e($case['officer_name']) ?></strong>
                </div>
                <div class="deadline-visual tone-<?= e($tone) ?>" role="status" aria-label="<?= e($deadlineLabel) ?>">
                    <div class="deadline-visual-icon">
                        <?php if ($tone === 'overdue'): ?>
                            <i class="bi bi-exclamation-octagon-fill"></i>
                        <?php elseif ($tone === 'critical'): ?>
                            <i class="bi bi-alarm-fill"></i>
                        <?php elseif ($tone === 'warn'): ?>
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php elseif ($tone === 'done'): ?>
                            <i class="bi bi-check-circle-fill"></i>
                        <?php else: ?>
                            <i class="bi bi-calendar2-check"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="deadline-visual-label"><?= e($deadlineLabel) ?></div>
                        <div class="deadline-visual-sub"><?= e($sisaText) ?></div>
                        <div class="deadline-visual-date">Jatuh tempo: <?= e(format_date_id($case['due_date'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-3">
        <!-- Informasi -->
        <div class="col-lg-5">
            <section class="panel-card h-100">
                <div class="panel-card-header mb-3">
                    <h2 class="h6 mb-0">Informasi Kasus</h2>
                    <p class="mb-0">Data utama permohonan</p>
                </div>
                <dl class="info-grid">
                    <div>
                        <dt>NPWP</dt>
                        <dd><?= e($case['npwp']) ?></dd>
                    </div>
                    <div>
                        <dt>Nama WP</dt>
                        <dd><?= e($case['taxpayer_name']) ?></dd>
                    </div>
                    <div>
                        <dt>Jenis Kasus</dt>
                        <dd><?= e($case['case_type_name']) ?></dd>
                    </div>
                    <div>
                        <dt>Sumber</dt>
                        <dd><?= e($case['source_name']) ?></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="badge text-bg-light border"><?= e($case['status_name']) ?></span></dd>
                    </div>
                    <div>
                        <dt>Petugas</dt>
                        <dd><?= e($case['officer_name']) ?></dd>
                    </div>
                    <div>
                        <dt>Tanggal Dibuat</dt>
                        <dd><?= e(format_date_id($case['created_date'])) ?></dd>
                    </div>
                    <div>
                        <dt>Jatuh Tempo</dt>
                        <dd><?= e(format_date_id($case['due_date'])) ?></dd>
                    </div>
                    <div>
                        <dt>Sisa Hari</dt>
                        <dd>
                            <span class="badge badge-deadline tone-<?= e($tone) ?>"><?= e($sisaText) ?></span>
                        </dd>
                    </div>
                    <div class="full">
                        <dt>Catatan terakhir</dt>
                        <dd><?= e($case['last_note'] ?: '—') ?></dd>
                    </div>
                    <div>
                        <dt>Dibuat oleh</dt>
                        <dd><?= e($case['created_by_name'] ?: '—') ?><br><small class="text-muted"><?= e(format_datetime_id($case['created_at'])) ?></small></dd>
                    </div>
                    <div>
                        <dt>Diperbarui oleh</dt>
                        <dd><?= e($case['updated_by_name'] ?: '—') ?><br><small class="text-muted"><?= e(format_datetime_id($case['updated_at'])) ?></small></dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- Riwayat -->
        <div class="col-lg-7">
            <section class="panel-card h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="panel-card-header mb-0">
                        <h2 class="h6 mb-0">Riwayat Perubahan</h2>
                        <p class="mb-0">Progres kasus dari awal sampai perubahan terakhir</p>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <a class="btn btn-outline-secondary <?= $timelineOrder === 'desc' ? 'active' : '' ?>" href="<?= e(query_url('/cases/' . $case['id'], ['timeline' => 'desc'])) ?>">Terbaru</a>
                        <a class="btn btn-outline-secondary <?= $timelineOrder === 'asc' ? 'active' : '' ?>" href="<?= e(query_url('/cases/' . $case['id'], ['timeline' => 'asc'])) ?>">Terlama</a>
                    </div>
                </div>

                <?php if ($histories === []): ?>
                    <div class="empty-state py-5">
                        <i class="bi bi-clock-history"></i>
                        <p class="mb-0">Belum ada riwayat perubahan.</p>
                    </div>
                <?php else: ?>
                    <ol class="history-timeline">
                        <?php foreach ($histories as $h): ?>
                            <?php
                            $tone = (string) ($h['event_tone'] ?? 'updated');
                            $changeCount = (int) ($h['change_count'] ?? count($h['changes'] ?? []));
                            ?>
                            <li class="history-item history-item--<?= e($tone) ?>">
                                <div class="history-dot" aria-hidden="true"></div>
                                <div class="history-card">
                                    <div class="history-card-head">
                                        <time class="history-time" datetime="<?= e((string) $h['created_at']) ?>">
                                            <?= e(format_datetime_id($h['created_at'])) ?>
                                        </time>
                                        <div class="history-card-head__meta">
                                            <?php if ($changeCount > 0): ?>
                                                <span class="history-change-count"><?= $changeCount ?> field</span>
                                            <?php endif; ?>
                                            <span class="badge history-event-badge history-event-badge--<?= e($tone) ?>">
                                                <?= e($h['event_label'] ?? $h['event_type']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if (!empty($h['summary'])): ?>
                                        <p class="history-summary"><?= e($h['summary']) ?></p>
                                    <?php else: ?>
                                        <div class="history-actor">
                                            <i class="bi bi-person-circle"></i>
                                            <?= e($h['actor'] ?? ($h['changed_by_name'] ?: 'Sistem')) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($h['changes'])): ?>
                                        <div class="history-changes">
                                            <?php foreach ($h['changes'] as $change): ?>
                                                <?php $isInitial = !empty($change['is_initial']); ?>
                                                <div class="history-change-row<?= $isInitial ? ' history-change-row--initial' : '' ?><?= ($change['field'] ?? '') === 'status_id' ? ' history-change-row--status' : '' ?>">
                                                    <div class="history-field"><?= e($change['label']) ?></div>
                                                    <div class="history-values">
                                                        <?php if ($isInitial): ?>
                                                            <span class="val-initial-label">Nilai awal</span>
                                                            <span class="val-new"><?= e($change['new']) ?></span>
                                                        <?php else: ?>
                                                            <span class="val-old"><?= e($change['old']) ?></span>
                                                            <span class="val-arrow" aria-hidden="true">→</span>
                                                            <span class="val-new"><?= e($change['new']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (!empty($h['old_status_name']) || !empty($h['new_status_name'])): ?>
                                        <div class="history-changes">
                                            <div class="history-change-row history-change-row--status">
                                                <div class="history-field">Status</div>
                                                <div class="history-values">
                                                    <span class="val-old"><?= e($h['old_status_name'] ?: '—') ?></span>
                                                    <span class="val-arrow">→</span>
                                                    <span class="val-new"><?= e($h['new_status_name'] ?: '—') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($h['note'])): ?>
                                        <div class="history-note">
                                            <span class="text-muted small">Catatan:</span> <?= e($h['note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
