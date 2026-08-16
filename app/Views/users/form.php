<?php
/** @var string $mode */
/** @var array|null $item */
/** @var array $errors */
/** @var array $old */
/** @var array $officers */
$v = static function (string $key, mixed $default = '') use ($old, $item) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($item !== null && array_key_exists($key, $item)) {
        return $item[$key];
    }
    return $default;
};
$role = (string) $v('role', 'petugas');
$action = $mode === 'create' ? url('/users') : url('/users/' . ($item['id'] ?? '') . '/update');
?>
<div class="form-page">
    <a href="<?= e(url('/users')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Kembali</a>
    <div class="panel-card form-card mt-3">
        <?php if (!empty($errors['form'][0])): ?>
            <div class="alert alert-danger"><?= e($errors['form'][0]) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" class="master-form" data-loading-form id="userForm">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" id="full_name" name="full_name" maxlength="150" required
                           class="form-control <?= is_invalid($errors, 'full_name') ?>"
                           value="<?= e((string) $v('full_name')) ?>">
                    <?= field_error($errors, 'full_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                    <input type="text" id="username" name="username" maxlength="100" required
                           class="form-control <?= is_invalid($errors, 'username') ?>"
                           value="<?= e((string) $v('username')) ?>" autocomplete="off">
                    <?= field_error($errors, 'username') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" maxlength="190"
                           class="form-control <?= is_invalid($errors, 'email') ?>"
                           value="<?= e((string) $v('email')) ?>">
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                    <select id="role" name="role" class="form-select <?= is_invalid($errors, 'role') ?>">
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin / Supervisor</option>
                        <option value="petugas" <?= $role === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                    </select>
                    <?= field_error($errors, 'role') ?>
                </div>
                <div class="col-md-6" id="officerField">
                    <label class="form-label" for="officer_id">Petugas terkait</label>
                    <select id="officer_id" name="officer_id" class="form-select <?= is_invalid($errors, 'officer_id') ?>">
                        <option value="">— Pilih petugas —</option>
                        <?php foreach ($officers as $officer): ?>
                            <option value="<?= (int) $officer['id'] ?>" <?= (string) $v('officer_id') === (string) $officer['id'] ? 'selected' : '' ?>>
                                <?= e($officer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Wajib untuk role Petugas.</div>
                    <?= field_error($errors, 'officer_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Password <?= $mode === 'create' ? '<span class="text-danger">*</span>' : '' ?></label>
                    <input type="password" id="password" name="password" minlength="8"
                           class="form-control <?= is_invalid($errors, 'password') ?>"
                           autocomplete="new-password"
                           <?= $mode === 'create' ? 'required' : '' ?>>
                    <div class="form-text"><?= $mode === 'edit' ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.' ?></div>
                    <?= field_error($errors, 'password') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">Konfirmasi Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8"
                           class="form-control" autocomplete="new-password"
                           <?= $mode === 'create' ? 'required' : '' ?>>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                            <?= (string) $v('is_active', '1') === '1' || (int) $v('is_active', 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary" data-loading-btn><?= $mode === 'create' ? 'Simpan' : 'Perbarui' ?></button>
                <a href="<?= e(url('/users')) ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
  const role = document.getElementById('role');
  const officerField = document.getElementById('officerField');
  function sync() {
    if (!role || !officerField) return;
    officerField.style.display = role.value === 'petugas' ? '' : 'none';
  }
  if (role) role.addEventListener('change', sync);
  sync();
})();
</script>
