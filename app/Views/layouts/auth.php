<?php
/** @var string $content */
/** @var string $pageTitle */
$appName = (string) config('app.name', 'Kajang Lako');
$tagline = (string) config('app.tagline', '');
$hasLogo = is_file(public_path('assets/img/logo.png'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php \App\Core\View::partial('partials/seo_head', ['pageTitle' => $pageTitle ?? 'Login']); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-atmosphere" aria-hidden="true">
    <span class="auth-orb auth-orb-a"></span>
    <span class="auth-orb auth-orb-b"></span>
    <span class="auth-orb auth-orb-c"></span>
    <span class="auth-wave"></span>
</div>
<div class="auth-shell">
    <div class="auth-panel">
        <header class="auth-welcome">
            <h1>Selamat datang,<br><span class="auth-welcome-accent">Login</span> untuk melanjutkan!</h1>
        </header>

        <div class="auth-brand">
            <?php if ($hasLogo): ?>
                <img src="<?= e(asset('img/logo.png')) ?>" alt="<?= e($appName) ?>" class="auth-logo">
            <?php else: ?>
                <div class="auth-logo-fallback" aria-hidden="true">KL</div>
                <div class="auth-brand-name"><?= e($appName) ?></div>
                <?php if ($tagline !== ''): ?>
                    <p class="tagline"><?= e($tagline) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php
        // Alert login ditampilkan di dalam view auth/login (lebih jelas).
        // Flash sukses/error lain tetap bisa dipakai di halaman auth lain.
        $isLoginPage = (current_path() === '/login');
        if (!$isLoginPage) {
            \App\Core\View::partial('partials/flash');
        }
        ?>
        <?= $content ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var btn = document.getElementById('togglePassword');
  var input = document.getElementById('password');
  if (!btn || !input) return;
  btn.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
})();
</script>
</body>
</html>
