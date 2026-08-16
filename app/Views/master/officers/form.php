<?php
/** @var string $mode */
/** @var array|null $item */
/** @var array $errors */
/** @var array $old */
$v = static function (string $key, mixed $default = '') use ($old, $item) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($item !== null && array_key_exists($key, $item)) {
        return $item[$key];
    }
    return $default;
};
$action = $mode === 'create' ? url('/master/officers') : url('/master/officers/' . ($item['id'] ?? '') . '/update');
?>
<div class="form-page">
    <a href="<?= e(url('/master/officers')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Kembali</a>

    <div class="panel-card form-card mt-3">
        <?php if (!empty($errors['form'][0])): ?>
            <div class="alert alert-danger"><?= e($errors['form'][0]) ?></div>
        <?php endif; ?>
        <?php if ($mode === 'edit' && !empty($usageCount)): ?>
            <div class="alert alert-info">Data ini dipakai di <?= (int) $usageCount ?> relasi. Nonaktifkan (jangan hapus) jika sudah tidak digunakan.</div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" class="master-form" data-loading-form>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Petugas <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" maxlength="150" required
                           class="form-control <?= is_invalid($errors, 'name') ?>"
                           value="<?= e((string) $v('name')) ?>">
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="employee_code">Kode Pegawai</label>
                    <input type="text" id="employee_code" name="employee_code" maxlength="50"
                           class="form-control <?= is_invalid($errors, 'employee_code') ?>"
                           value="<?= e((string) $v('employee_code')) ?>">
                    <?= field_error($errors, 'employee_code') ?>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                            <?= (string) $v('is_active', '1') === '1' || (int) $v('is_active', 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary" data-loading-btn><?= $mode === 'create' ? 'Simpan' : 'Perbarui' ?></button>
                <a href="<?= e(url('/master/officers')) ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
