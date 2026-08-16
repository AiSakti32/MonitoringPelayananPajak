<?php
/**
 * @var array $profile
 * @var array $errors
 * @var array $old
 * @var string|null $success
 */
$v = static function (string $key, mixed $default = '') use ($old, $profile) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if (array_key_exists($key, $profile)) {
        return $profile[$key] ?? $default;
    }
    return $default;
};
?>
<div class="profile-page">
    <div class="page-intro mb-3">
        <p class="text-muted mb-0">Kelola informasi akun Anda. Role dan username tidak dapat diubah dari halaman ini.</p>
    </div>

    <?php if (!empty($errors['form'][0])): ?>
        <div class="alert alert-danger"><?= e($errors['form'][0]) ?></div>
    <?php endif; ?>

    <div class="profile-layout">
        <section class="panel-card profile-summary-card">
            <div class="profile-summary">
                <div class="profile-summary__avatar" aria-hidden="true">
                    <?= e(mb_strtoupper(mb_substr((string) ($profile['full_name'] ?? 'U'), 0, 1))) ?>
                </div>
                <div>
                    <h3 class="profile-summary__name"><?= e((string) ($profile['full_name'] ?? '')) ?></h3>
                    <p class="profile-summary__meta mb-0">
                        <span class="badge text-bg-primary"><?= e(ucfirst((string) ($profile['role'] ?? ''))) ?></span>
                        <span class="text-muted">@<?= e((string) ($profile['username'] ?? '')) ?></span>
                    </p>
                </div>
            </div>

            <dl class="profile-dl profile-dl--compact">
                <div>
                    <dt>Username</dt>
                    <dd><?= e((string) ($profile['username'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt>Role</dt>
                    <dd><?= e(ucfirst((string) ($profile['role'] ?? ''))) ?></dd>
                </div>
                <div>
                    <dt>Petugas terkait</dt>
                    <dd><?= e((string) ($profile['officer_name'] ?? '—')) ?></dd>
                </div>
                <div>
                    <dt>Login terakhir</dt>
                    <dd><?= e(format_datetime_id($profile['last_login_at'] ?? null)) ?></dd>
                </div>
            </dl>
        </section>

        <section class="panel-card profile-edit-card">
            <div class="panel-card-header mb-3">
                <h3 class="mb-1">Edit Profil</h3>
                <p class="mb-0">Perbarui nama, email, atau password akun Anda.</p>
            </div>

            <form method="post" action="<?= e(url('/profile/update')) ?>" class="profile-form" data-loading-form novalidate>
                <?= csrf_field() ?>

                <div class="profile-form-grid">
                    <div class="profile-field">
                        <label class="form-label" for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" id="full_name" name="full_name" maxlength="150" required
                               class="form-control <?= is_invalid($errors, 'full_name') ?>"
                               value="<?= e((string) $v('full_name')) ?>"
                               autocomplete="name">
                        <?= field_error($errors, 'full_name') ?>
                    </div>

                    <div class="profile-field">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="190"
                               class="form-control <?= is_invalid($errors, 'email') ?>"
                               value="<?= e((string) $v('email')) ?>"
                               placeholder="nama@contoh.go.id"
                               autocomplete="email">
                        <?= field_error($errors, 'email') ?>
                    </div>
                </div>

                <div class="profile-password-block">
                    <h4 class="profile-password-block__title">Ubah Password</h4>
                    <p class="profile-password-block__hint">Kosongkan jika tidak ingin mengganti password. Isi password saat ini bila mengganti.</p>

                    <div class="profile-form-grid">
                        <div class="profile-field profile-field--full">
                            <label class="form-label" for="current_password">Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password"
                                   class="form-control <?= is_invalid($errors, 'current_password') ?>"
                                   autocomplete="current-password">
                            <?= field_error($errors, 'current_password') ?>
                        </div>

                        <div class="profile-field">
                            <label class="form-label" for="password">Password Baru</label>
                            <input type="password" id="password" name="password" minlength="8"
                                   class="form-control <?= is_invalid($errors, 'password') ?>"
                                   autocomplete="new-password">
                            <div class="form-text">Minimal 8 karakter.</div>
                            <?= field_error($errors, 'password') ?>
                        </div>

                        <div class="profile-field">
                            <label class="form-label" for="password_confirmation">Konfirmasi Password Baru</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" minlength="8"
                                   class="form-control"
                                   autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="profile-form-actions">
                    <button type="submit" class="btn case-form-btn case-form-btn--primary" data-loading-btn>
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        Simpan Perubahan
                    </button>
                    <a href="<?= e(url('/dashboard')) ?>" class="btn case-form-btn case-form-btn--ghost">Batal</a>
                </div>
            </form>
        </section>
    </div>
</div>
