<?php
/** @var string $content */
/** @var string $pageTitle */
$appName = (string) config('app.name', 'Kajang Lako');
$tagline = (string) config('app.tagline', '');
$user = auth_user();
$logoPath = public_path('assets/img/kajanglakologobersih.png');
$hasLogo = is_file($logoPath);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php \App\Core\View::partial('partials/seo_head', ['pageTitle' => $pageTitle ?? 'Dashboard']); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
<div class="app-shell" id="appShell">
    <aside class="app-sidebar" id="appSidebar">
        <a href="<?= e(url('/dashboard')) ?>" class="sidebar-brand" title="Ke Dashboard" aria-label="Kajang Lako — Dashboard">
            <?php if ($hasLogo): ?>
                <img src="<?= e(asset('img/kajanglakologobersih.png')) ?>" alt="<?= e($appName) ?>" class="sidebar-logo">
            <?php else: ?>
                <div class="sidebar-logo-fallback" aria-hidden="true">KL</div>
            <?php endif; ?>
            <div class="sidebar-brand-text">
                <strong><?= e($appName) ?></strong>
                <small><?= e($tagline) ?></small>
            </div>
        </a>

        <nav class="sidebar-nav">
            <a class="nav-link <?= nav_active('/dashboard') ?>" href="<?= e(url('/dashboard')) ?>">
                <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
            </a>
            <a class="nav-link <?= (current_path() === '/cases' || (str_starts_with(current_path(), '/cases/') && current_path() !== '/cases/create' && !str_starts_with(current_path(), '/cases/import'))) ? 'active' : '' ?>" href="<?= e(url('/cases')) ?>">
                <i class="bi bi-folder2-open"></i><span>Kasus</span>
            </a>
            <a class="nav-link <?= current_path() === '/cases/create' ? 'active' : '' ?>" href="<?= e(url('/cases/create')) ?>">
                <i class="bi bi-plus-square"></i><span>Simpan/Update Kasus</span>
            </a>
            <a class="nav-link <?= str_starts_with(current_path(), '/cases/import') ? 'active' : '' ?>" href="<?= e(url('/cases/import')) ?>">
                <i class="bi bi-file-earmark-excel"></i><span>Import Excel</span>
            </a>
            <a class="nav-link <?= nav_active('/monitoring/deadlines') ?>" href="<?= e(url('/monitoring/deadlines')) ?>">
                <i class="bi bi-alarm"></i><span>Monitoring Deadline</span>
            </a>
            <a class="nav-link <?= nav_active('/monitoring/officers') ?>" href="<?= e(url('/monitoring/officers')) ?>">
                <i class="bi bi-people"></i><span>Monitoring Petugas</span>
            </a>
            <a class="nav-link <?= nav_active('/alerts') ?>" href="<?= e(url('/alerts')) ?>">
                <i class="bi bi-bell"></i>
                <span>Alert / Perlu Tindakan</span>
                <?php $ac = alert_count(); if ($ac > 0): ?>
                    <span class="nav-badge" aria-label="<?= (int) $ac ?> alert"><?= (int) $ac ?></span>
                <?php endif; ?>
            </a>

            <?php if (is_admin()): ?>
                <div class="nav-section">Master Data</div>
                <a class="nav-link <?= nav_active('/master/officers') ?>" href="<?= e(url('/master/officers')) ?>">
                    <i class="bi bi-person-badge"></i><span>Petugas</span>
                </a>
                <a class="nav-link <?= nav_active('/master/case-types') ?>" href="<?= e(url('/master/case-types')) ?>">
                    <i class="bi bi-tags"></i><span>Jenis Kasus</span>
                </a>
                <a class="nav-link <?= nav_active('/master/statuses') ?>" href="<?= e(url('/master/statuses')) ?>">
                    <i class="bi bi-flag"></i><span>Status</span>
                </a>
                <a class="nav-link <?= nav_active('/master/sources') ?>" href="<?= e(url('/master/sources')) ?>">
                    <i class="bi bi-diagram-3"></i><span>Sumber</span>
                </a>
                <a class="nav-link <?= nav_active('/users') ?>" href="<?= e(url('/users')) ?>">
                    <i class="bi bi-person-gear"></i><span>User Management</span>
                </a>
                <a class="nav-link <?= nav_active('/audit-logs') ?>" href="<?= e(url('/audit-logs')) ?>">
                    <i class="bi bi-journal-text"></i><span>Audit Log</span>
                </a>
            <?php endif; ?>

            <div class="nav-section">Akun</div>
            <a class="nav-link <?= nav_active('/profile') ?>" href="<?= e(url('/profile')) ?>">
                <i class="bi bi-person-circle"></i><span>Profil</span>
            </a>
            <form method="post" action="<?= e(url('/logout')) ?>" class="sidebar-logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="nav-link btn-logout">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </button>
            </form>
        </nav>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="btn btn-icon sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-title">
                <h1><?= e($pageTitle ?? '') ?></h1>
                <span class="topbar-date"><?= e(format_date_id(today_jakarta())) ?></span>
            </div>
            <div class="topbar-actions">
                <?php $alertCount = alert_count(); ?>
                <a href="<?= e(url('/alerts')) ?>" class="topbar-alert <?= $alertCount > 0 ? 'has-alerts' : '' ?>" title="Alert / Perlu Tindakan" aria-label="Alert <?= (int) $alertCount ?>">
                    <i class="bi bi-bell"></i>
                    <?php if ($alertCount > 0): ?>
                        <span class="topbar-alert-badge"><?= $alertCount > 99 ? '99+' : (int) $alertCount ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-user dropdown">
                    <button class="btn btn-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu akun">
                        <span class="user-avatar"><?= e(mb_strtoupper(mb_substr((string) ($user['full_name'] ?? 'U'), 0, 1))) ?></span>
                        <span class="user-meta d-none d-md-inline">
                            <strong><?= e($user['full_name'] ?? '') ?></strong>
                            <small><?= e(ucfirst((string) ($user['role'] ?? ''))) ?></small>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= e(url('/profile')) ?>">Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(url('/logout')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item">Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <?php \App\Core\View::partial('partials/flash'); ?>
            <?= $content ?>
        </main>
    </div>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
