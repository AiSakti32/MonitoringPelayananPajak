<?php
/** @var string|null $error */
/** @var string|null $success */
/** @var array $errors */
/** @var string|null $loginCode */
$fieldErrors = $errors ?? [];
$loginCode = $loginCode ?? null;
$flashError = $error ?? null;
$flashSuccess = $success ?? null;
$userOk = old('username') !== null && old('username') !== '' && empty($fieldErrors['username']) && $flashError === null;
$isLoginAttemptError = $loginCode !== null && $loginCode !== '';
$hasAuthAlert = !empty($flashSuccess) || ($isLoginAttemptError && !empty($flashError));

$alertIcon = 'bi-exclamation-triangle-fill';
$alertTone = 'danger';
$alertTitle = 'Login gagal';
if (!empty($flashSuccess)) {
    $alertIcon = 'bi-check-circle-fill';
    $alertTone = 'success';
    $alertTitle = 'Berhasil';
} elseif ($loginCode === 'rate_limited') {
    $alertIcon = 'bi-hourglass-split';
    $alertTone = 'warning';
} elseif ($loginCode === 'inactive') {
    $alertIcon = 'bi-person-x-fill';
    $alertTone = 'warning';
} elseif ($loginCode === 'bad_username' || $loginCode === 'bad_password' || $loginCode === 'validation') {
    $alertIcon = 'bi-x-circle-fill';
    $alertTone = 'danger';
}
?>

<?php if ($hasAuthAlert): ?>
    <div class="auth-alert auth-alert--<?= e($alertTone) ?>" role="alert" aria-live="assertive">
        <div class="auth-alert__icon" aria-hidden="true"><i class="bi <?= e($alertIcon) ?>"></i></div>
        <div class="auth-alert__body">
            <strong class="auth-alert__title">
                <?= e($alertTitle) ?>
            </strong>
            <p class="auth-alert__text mb-0">
                <?= e((string) (!empty($flashSuccess) ? $flashSuccess : $flashError)) ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/login')) ?>" class="auth-form" autocomplete="on" novalidate>
    <?= csrf_field() ?>

    <div class="auth-field <?= isset($fieldErrors['username']) || $loginCode === 'bad_username' ? 'is-invalid' : ($userOk ? 'is-valid' : '') ?>">
        <label for="username" class="auth-label">Username</label>
        <div class="auth-input-row">
            <input
                type="text"
                class="auth-input"
                id="username"
                name="username"
                value="<?= e((string) old('username')) ?>"
                placeholder="Masukkan username"
                required
                autofocus
                maxlength="100"
                autocomplete="username"
            >
            <?php if ($userOk): ?>
                <span class="auth-input-icon auth-input-ok" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($fieldErrors['username'])): ?>
            <div class="auth-field-error"><?= e($fieldErrors['username'][0]) ?></div>
        <?php endif; ?>
    </div>

    <div class="auth-field <?= isset($fieldErrors['password']) || $loginCode === 'bad_password' ? 'is-invalid' : '' ?>">
        <label for="password" class="auth-label">Password</label>
        <div class="auth-input-row">
            <input
                type="password"
                class="auth-input"
                id="password"
                name="password"
                placeholder="Masukkan password"
                required
                minlength="6"
                autocomplete="current-password"
            >
            <button type="button" class="auth-input-icon auth-input-toggle" id="togglePassword" aria-label="Tampilkan password">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <?php if (!empty($fieldErrors['password'])): ?>
            <div class="auth-field-error"><?= e($fieldErrors['password'][0]) ?></div>
        <?php endif; ?>
    </div>

    <div class="auth-row-meta">
        <label class="auth-check">
            <input type="checkbox" value="1" id="remember" name="remember" <?= old('remember') === '1' ? 'checked' : '' ?>>
            <span>Ingat saya</span>
        </label>
        <span class="auth-meta-hint">Akses internal kantor</span>
    </div>

    <button type="submit" class="btn-login">Masuk</button>
</form>

<p class="auth-footnote">Tidak ada registrasi publik. Hubungi admin jika belum memiliki akun.</p>
