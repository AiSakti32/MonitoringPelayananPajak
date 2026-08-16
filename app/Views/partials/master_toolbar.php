<?php
/** @var string $basePath */
/** @var array $filters */
/** @var string $createUrl */
/** @var string $createLabel */
/** @var bool $showRoleFilter */
$showRoleFilter = $showRoleFilter ?? false;
?>
<div class="master-toolbar">
    <a href="<?= e(url($createUrl)) ?>" class="btn btn-primary btn-add master-toolbar__cta">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        <span><?= e($createLabel) ?></span>
    </a>

    <form method="get" action="<?= e(url($basePath)) ?>" class="master-filters" id="masterFilterForm">
        <div class="filter-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>"
                   class="form-control" placeholder="Cari..." aria-label="Cari">
        </div>

        <div class="master-filters__meta">
            <select name="status" class="form-select" aria-label="Filter status">
                <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>Semua status</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>

            <?php if ($showRoleFilter): ?>
                <select name="role" class="form-select" aria-label="Filter role">
                    <option value="all" <?= ($filters['role'] ?? '') === 'all' ? 'selected' : '' ?>>Semua role</option>
                    <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="petugas" <?= ($filters['role'] ?? '') === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                </select>
            <?php endif; ?>

            <div class="master-filters__actions">
                <button type="submit" class="btn btn-outline-secondary master-filter-btn" data-loading-btn>Filter</button>
                <a href="<?= e(url($basePath)) ?>" class="btn btn-link master-reset-btn">Reset</a>
            </div>
        </div>
    </form>
</div>
