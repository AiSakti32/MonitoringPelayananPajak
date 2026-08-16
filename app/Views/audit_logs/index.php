<?php
/**
 * @var \App\Core\Paginator|null $paginator
 * @var array $filters
 * @var array $actions
 * @var array $modules
 * @var array $userOptions
 * @var string|null $loadError
 * @var string $basePath
 */

use App\Services\AuditLogger;

\App\Core\View::partial('partials/loading_overlay');

$decode = static function (mixed $raw): ?array {
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw)) {
        return null;
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
};

$referenceOf = static function (array $row) use ($decode): string {
    $meta = $decode($row['meta'] ?? null) ?? [];
    $new = $decode($row['new_values'] ?? null) ?? [];
    foreach (['case_number', 'username', 'name'] as $key) {
        if (!empty($meta[$key]) && is_string($meta[$key])) {
            return $meta[$key];
        }
        if (!empty($new[$key]) && is_string($new[$key])) {
            return $new[$key];
        }
    }
    $type = (string) ($row['entity_type'] ?? '');
    $id = $row['entity_id'] ?? null;
    if ($type !== '' && $id !== null) {
        return $type . ' #' . $id;
    }
    return $id !== null ? '#' . $id : '—';
};
?>
<div class="page-intro mb-3">
    <p class="text-muted mb-0">
        Jejak aktivitas penting sistem (auth, kasus, master data, user). Password dan secret tidak dicatat.
        Urutan default: terbaru di atas.
    </p>
</div>

<form method="get" action="<?= e(url($basePath)) ?>" class="panel-card mb-3 audit-filters" id="auditFilterForm">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Cari</label>
            <input type="search" name="q" class="form-control" value="<?= e((string) ($filters['q'] ?? '')) ?>"
                   placeholder="User, nomor kasus, deskripsi">
        </div>
        <div class="col-md-2">
            <label class="form-label">Dari tanggal</label>
            <input type="date" name="date_from" class="form-control" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Sampai tanggal</label>
            <input type="date" name="date_to" class="form-control" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">User</label>
            <select name="user_id" class="form-select">
                <option value="">Semua user</option>
                <?php foreach ($userOptions as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (string) ($filters['user_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
                        <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
                <option value="all" <?= ($filters['role'] ?? 'all') === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="petugas" <?= ($filters['role'] ?? '') === 'petugas' ? 'selected' : '' ?>>Petugas</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Modul</label>
            <select name="module" class="form-select">
                <option value="all" <?= ($filters['module'] ?? 'all') === 'all' ? 'selected' : '' ?>>Semua</option>
                <?php
                $moduleChoices = $modules !== [] ? $modules : ['AUTH', 'CASE', 'MASTER', 'USER'];
                foreach ($moduleChoices as $m):
                ?>
                    <option value="<?= e($m) ?>" <?= ($filters['module'] ?? '') === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Aktivitas</label>
            <select name="action" class="form-select">
                <option value="all" <?= ($filters['action'] ?? 'all') === 'all' ? 'selected' : '' ?>>Semua</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= e($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>>
                        <?= e(AuditLogger::actionLabel($act)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-loading-btn>Terapkan</button>
            <a href="<?= e(url($basePath)) ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="panel-card">
    <?php if ($loadError): ?>
        <?php \App\Core\View::partial('partials/error_state', ['message' => $loadError, 'retryUrl' => $basePath]); ?>
    <?php elseif ($paginator === null || $paginator->total === 0): ?>
        <?php \App\Core\View::partial('partials/empty_state', [
            'title' => 'Belum ada audit log',
            'hint' => 'Aktivitas login, kasus, master data, dan user akan muncul di sini.',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table master-table align-middle mb-0" id="auditLogTable">
                <thead>
                <tr>
                    <th>Tanggal & Jam</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Aktivitas</th>
                    <th>Modul</th>
                    <th>Referensi</th>
                    <th>IP</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paginator->items as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(format_datetime_id($row['created_at'] ?? null)) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($row['user_name'] ?: 'Sistem / anonim') ?></div>
                            <?php if (!empty($row['user_username'])): ?>
                                <div class="text-muted small"><?= e($row['user_username']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['user_role'])): ?>
                                <span class="badge <?= $row['user_role'] === 'admin' ? 'badge-role-admin' : 'badge-role-petugas' ?>">
                                    <?= e(ucfirst((string) $row['user_role'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e(AuditLogger::actionLabel((string) $row['action'])) ?></div>
                            <?php if (!empty($row['description'])): ?>
                                <div class="text-muted small"><?= e($row['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-module"><?= e($row['module'] ?: '—') ?></span></td>
                        <td class="text-nowrap"><?= e($referenceOf($row)) ?></td>
                        <td class="text-muted small"><?= e($row['ip_address'] ?: '—') ?></td>
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary btn-audit-detail"
                                    data-audit-id="<?= (int) $row['id'] ?>">
                                Detail
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'basePath' => $basePath]); ?>
    <?php endif; ?>
</div>

<!-- Detail modal -->
<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-labelledby="auditDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="auditDetailTitle">Detail Audit</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="auditDetailBody">
                <p class="text-muted mb-0">Memuat…</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modalEl = document.getElementById('auditDetailModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const body = document.getElementById('auditDetailBody');
    const detailUrl = <?= json_encode(url('/audit-logs')) ?>;

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderKv(obj) {
        if (!obj || typeof obj !== 'object' || Object.keys(obj).length === 0) return '';
        let html = '<dl class="audit-kv row mb-0">';
        Object.keys(obj).forEach(function (k) {
            let v = obj[k];
            if (v !== null && typeof v === 'object') {
                v = JSON.stringify(v);
            }
            html += '<dt class="col-sm-4">' + esc(k) + '</dt><dd class="col-sm-8">' + esc(v === null || v === undefined ? '—' : v) + '</dd>';
        });
        html += '</dl>';
        return html;
    }

    document.querySelectorAll('.btn-audit-detail').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-audit-id');
            body.innerHTML = '<p class="text-muted mb-0">Memuat…</p>';
            modal.show();
            fetch(detailUrl + '/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j.ok) {
                        body.innerHTML = '<p class="text-danger mb-0">' + esc(res.j.message || 'Gagal memuat detail.') + '</p>';
                        return;
                    }
                    const it = res.j.item;
                    let html = '<div class="audit-detail-grid">';
                    html += '<div><span class="text-muted">Waktu</span><div class="fw-semibold">' + esc(it.created_at) + '</div></div>';
                    html += '<div><span class="text-muted">User</span><div class="fw-semibold">' + esc(it.user_name || 'Sistem / anonim') +
                        (it.user_username ? ' <span class="text-muted">(' + esc(it.user_username) + ')</span>' : '') + '</div></div>';
                    html += '<div><span class="text-muted">Role</span><div class="fw-semibold">' + esc(it.user_role ? (it.user_role.charAt(0).toUpperCase() + it.user_role.slice(1)) : '—') + '</div></div>';
                    html += '<div><span class="text-muted">Action</span><div class="fw-semibold">' + esc(it.action_label) + ' <code class="small">' + esc(it.action) + '</code></div></div>';
                    html += '<div><span class="text-muted">Module</span><div class="fw-semibold">' + esc(it.module || '—') + '</div></div>';
                    html += '<div><span class="text-muted">Entity / Referensi</span><div class="fw-semibold">' + esc(it.reference) + '</div></div>';
                    html += '<div><span class="text-muted">IP Address</span><div class="fw-semibold">' + esc(it.ip_address || '—') + '</div></div>';
                    if (it.description) {
                        html += '<div class="audit-detail-full"><span class="text-muted">Deskripsi</span><div>' + esc(it.description) + '</div></div>';
                    }
                    html += '</div>';

                    if (it.old_values && Object.keys(it.old_values).length) {
                        html += '<hr><h3 class="h6">Perubahan Sebelum</h3>' + renderKv(it.old_values);
                    }
                    if (it.new_values && Object.keys(it.new_values).length) {
                        html += '<hr><h3 class="h6">Perubahan Sesudah</h3>' + renderKv(it.new_values);
                    }
                    body.innerHTML = html;
                })
                .catch(function () {
                    body.innerHTML = '<p class="text-danger mb-0">Gagal memuat detail audit.</p>';
                });
        });
    });
})();
</script>
