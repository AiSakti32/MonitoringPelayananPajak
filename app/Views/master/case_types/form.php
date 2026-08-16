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
$action = $mode === 'create' ? url('/master/case-types') : url('/master/case-types/' . ($item['id'] ?? '') . '/update');
?>
<div class="form-page">
    <a href="<?= e(url('/master/case-types')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Kembali</a>
    <div class="panel-card form-card mt-3">
        <?php if (!empty($errors['form'][0])): ?>
            <div class="alert alert-danger"><?= e($errors['form'][0]) ?></div>
        <?php endif; ?>
        <?php if ($mode === 'edit' && !empty($usageCount)): ?>
            <div class="alert alert-info">Dipakai oleh <?= (int) $usageCount ?> case. Gunakan nonaktifkan jika tidak ingin tampil di dropdown baru.</div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" class="master-form" data-loading-form>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="name">Nama Jenis Kasus <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" maxlength="255" required
                           class="form-control <?= is_invalid($errors, 'name') ?>"
                           value="<?= e((string) $v('name')) ?>">
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="dashboard_group">Dashboard Group</label>
                    <input type="text" id="dashboard_group" name="dashboard_group" maxlength="255"
                           class="form-control <?= is_invalid($errors, 'dashboard_group') ?>"
                           value="<?= e((string) $v('dashboard_group')) ?>"
                           placeholder="Contoh: Penetapan Wajib Pajak Nonaktif">
                    <div class="form-text">Kosongkan jika tidak digabung di dashboard prioritas.</div>
                    <?= field_error($errors, 'dashboard_group') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">&nbsp;</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_dashboard_priority" name="is_dashboard_priority" value="1"
                            <?= (string) $v('is_dashboard_priority', '0') === '1' || (int) $v('is_dashboard_priority', 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_dashboard_priority">Prioritas Dashboard</label>
                    </div>
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
                <a href="<?= e(url('/master/case-types')) ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
