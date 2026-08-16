<?php
/** @var string $mode */
/** @var array|null $item */
/** @var array $errors */
/** @var array $old */
/** @var array $options */
/** @var mixed $lockedOfficerId */
/** @var bool $needsConfirm */
/** @var array|null $existingCase */
/** @var string|null $confirmMessage */
/** @var bool $forceConfirmExisting */
$forceConfirmExisting = $forceConfirmExisting ?? false;
$needsConfirm = $needsConfirm ?? false;

$v = static function (string $key, mixed $default = '') use ($old, $item) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($item !== null && array_key_exists($key, $item)) {
        return $item[$key];
    }
    return $default;
};

$action = $mode === 'edit'
    ? url('/cases/' . ($item['id'] ?? '') . '/update')
    : url('/cases/upsert');

$isUpdateMode = $mode === 'edit' || $forceConfirmExisting || $needsConfirm;
$confirmValue = $isUpdateMode || (string) $v('confirm_existing') === '1' ? '1' : '0';
$defaultOfficer = $lockedOfficerId !== null ? (string) $lockedOfficerId : (string) $v('officer_id');

$headerTitle = 'Simpan/Update Kasus';
$headerDesc = $mode === 'edit'
    ? 'Ubah data yang perlu diperbarui. Perubahan status dan field lain akan tercatat di riwayat.'
    : 'Cari kasus yang sudah ada, atau ketik nomor baru. Jika nomor sudah terdaftar, form otomatis beralih ke mode update.';
?>
<div class="form-page case-form-page">
    <a href="<?= e(url('/cases')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Kembali ke daftar</a>

    <div class="panel-card form-card case-form-card mt-3">
        <div class="case-form-header">
            <div class="case-form-header__icon" aria-hidden="true" id="caseFormHeaderIcon">
                <i class="bi <?= $isUpdateMode ? 'bi-pencil-square' : 'bi-journal-plus' ?>"></i>
            </div>
            <div>
                <h3 class="case-form-header__title" id="caseFormTitle"><?= e($headerTitle) ?></h3>
                <p class="case-form-header__desc" id="caseFormDesc"><?= e($headerDesc) ?></p>
            </div>
        </div>

        <?php if (!empty($errors['form'][0])): ?>
            <div class="alert alert-danger"><?= e($errors['form'][0]) ?></div>
        <?php endif; ?>

        <div id="caseModeBanner"
             class="case-mode-banner <?= $isUpdateMode ? 'case-mode-banner--update' : 'case-mode-banner--idle' ?>"
             role="status"
             data-mode="<?= $isUpdateMode ? 'update' : 'idle' ?>">
            <div class="case-mode-banner__badge" id="caseModeBadge">
                <?= $isUpdateMode ? 'Mode Perbarui' : 'Siap input' ?>
            </div>
            <div class="case-mode-banner__body">
                <strong id="caseModeTitle">
                    <?php if ($isUpdateMode): ?>
                        Nomor kasus sudah terdaftar — data utama akan diperbarui, riwayat tetap disimpan.
                    <?php else: ?>
                    Ketik sebagian nomor / nama WP di pencarian, atau isi Nomor Kasus manual. Jika nomor sudah ada, form otomatis mode perbarui.
                    <?php endif; ?>
                </strong>
                <div id="caseLookupSummary" class="case-mode-summary <?= $isUpdateMode && is_array($existingCase) ? '' : 'd-none' ?>">
                    <?php if (is_array($existingCase)): ?>
                        <div class="existing-summary grid">
                            <div><span>Nomor</span><strong><?= e((string) ($existingCase['case_number'] ?? '')) ?></strong></div>
                            <div><span>NPWP</span><strong><?= e((string) ($existingCase['npwp'] ?? '')) ?></strong></div>
                            <div><span>Nama WP</span><strong><?= e((string) ($existingCase['taxpayer_name'] ?? '')) ?></strong></div>
                            <div><span>Jenis</span><strong><?= e((string) ($existingCase['case_type_name'] ?? '')) ?></strong></div>
                            <div><span>Status saat ini</span><strong><?= e((string) ($existingCase['status_name'] ?? '')) ?></strong></div>
                            <div><span>Sumber</span><strong><?= e((string) ($existingCase['source_name'] ?? '')) ?></strong></div>
                            <div><span>Dibuat</span><strong><?= e(format_date_id($existingCase['created_date'] ?? null)) ?></strong></div>
                            <div><span>Jatuh Tempo</span><strong><?= e(format_date_id($existingCase['due_date'] ?? null)) ?></strong></div>
                            <div><span>Petugas</span><strong><?= e((string) ($existingCase['officer_name'] ?? '')) ?></strong></div>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="case-mode-banner__hint mb-0" id="caseModeHint">
                    <?php if ($isUpdateMode): ?>
                        Ubah field yang perlu (misalnya status Diproses → Selesai), isi catatan progress, lalu klik <strong>Perbarui Kasus</strong>.
                    <?php else: ?>
                        Setelah nomor valid, petunjuk mode akan muncul di sini.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <form method="post" action="<?= e($action) ?>" id="caseUpsertForm" class="master-form case-upsert-form" data-loading-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="confirm_existing" id="confirm_existing" value="<?= e($confirmValue) ?>">

            <section class="case-form-section">
                <h4 class="case-form-section__title">1. Nomor Kasus</h4>

                <?php if ($mode !== 'edit'): ?>
                <div class="case-picker-box mb-3">
                    <label class="case-form-label" for="case_picker">Cari &amp; pilih kasus yang sudah ada</label>
                    <select id="case_picker" class="form-select case-form-control" autocomplete="off">
                        <option value=""></option>
                    </select>
                    <div class="case-form-hint">
                        Ketik di kotak di atas (sebagian nomor / NPWP / nama WP), lalu pilih dari daftar.
                        Alternatif: dari <a href="<?= e(url('/cases')) ?>">Daftar Kasus</a> klik <strong>Perbarui</strong>.
                    </div>
                </div>
                <div class="case-or-divider" aria-hidden="true"><span>atau ketik nomor manual</span></div>
                <?php endif; ?>

                <div class="case-form-grid case-form-grid--single">
                    <div class="case-form-field">
                        <label class="case-form-label" for="case_number">
                            Nomor Kasus <span class="text-danger">*</span>
                            <?php if ($mode !== 'edit'): ?>
                                <span class="case-form-optional">(untuk kasus baru / isi manual)</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="case_number" name="case_number" maxlength="11" required
                               class="form-control case-form-control text-uppercase <?= is_invalid($errors, 'case_number') ?>"
                               value="<?= e((string) $v('case_number')) ?>"
                               placeholder="P0000000001"
                               pattern="[A-Za-z][0-9]{10}"
                               <?= $mode === 'edit' ? 'readonly' : '' ?>
                               autocomplete="off">
                        <div class="case-form-hint">Format: 1 huruf + 10 angka. Disimpan UPPERCASE. Satu nomor = satu data utama.</div>
                        <?= field_error($errors, 'case_number') ?>
                        <div id="caseNumberHint" class="case-form-hint case-form-hint--accent"></div>
                    </div>
                </div>
            </section>

            <section class="case-form-section" id="caseFormFields">
                <h4 class="case-form-section__title">2. Data Permohonan</h4>
                <div class="case-form-grid">
                    <div class="case-form-field">
                        <label class="case-form-label" for="npwp">NPWP Wajib Pajak Pusat <span class="text-danger">*</span></label>
                        <input type="text" id="npwp" name="npwp" maxlength="16" required inputmode="numeric"
                               class="form-control case-form-control <?= is_invalid($errors, 'npwp') ?>"
                               value="<?= e((string) $v('npwp')) ?>"
                               placeholder="16 digit">
                        <?= field_error($errors, 'npwp') ?>
                    </div>

                    <div class="case-form-field">
                        <label class="case-form-label" for="taxpayer_name">Nama Wajib Pajak Pusat <span class="text-danger">*</span></label>
                        <input type="text" id="taxpayer_name" name="taxpayer_name" maxlength="255" required
                               class="form-control case-form-control <?= is_invalid($errors, 'taxpayer_name') ?>"
                               value="<?= e((string) $v('taxpayer_name')) ?>"
                               placeholder="Nama wajib pajak">
                        <?= field_error($errors, 'taxpayer_name') ?>
                    </div>

                    <div class="case-form-field">
                        <label class="case-form-label" for="case_type_id">Jenis Kasus <span class="text-danger">*</span></label>
                        <select id="case_type_id" name="case_type_id" class="form-select case-form-control searchable <?= is_invalid($errors, 'case_type_id') ?>" required>
                            <option value="">— Pilih jenis kasus —</option>
                            <?php foreach ($options['types'] as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (string) $v('case_type_id') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'case_type_id') ?>
                    </div>

                    <div class="case-form-field">
                        <label class="case-form-label" for="status_id">Status Kasus <span class="text-danger">*</span></label>
                        <select id="status_id" name="status_id" class="form-select case-form-control <?= is_invalid($errors, 'status_id') ?>" required>
                            <option value="">— Pilih status —</option>
                            <?php foreach ($options['statuses'] as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (string) $v('status_id') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="case-form-hint">Contoh progres: Dibuat → Diproses → Selesai</div>
                        <?= field_error($errors, 'status_id') ?>
                    </div>

                    <div class="case-form-field">
                        <label class="case-form-label" for="source_id">Sumber Kasus <span class="text-danger">*</span></label>
                        <select id="source_id" name="source_id" class="form-select case-form-control <?= is_invalid($errors, 'source_id') ?>" required>
                            <option value="">— Pilih sumber —</option>
                            <?php foreach ($options['sources'] as $src): ?>
                                <option value="<?= (int) $src['id'] ?>" <?= (string) $v('source_id') === (string) $src['id'] ? 'selected' : '' ?>><?= e($src['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'source_id') ?>
                    </div>
                </div>
            </section>

            <section class="case-form-section">
                <h4 class="case-form-section__title">3. Jadwal & Petugas</h4>
                <div class="case-form-grid">
                    <div class="case-form-field">
                        <label class="case-form-label" for="created_date">Tanggal Dibuat <span class="text-danger">*</span></label>
                        <input type="date" id="created_date" name="created_date" required
                               class="form-control case-form-control <?= is_invalid($errors, 'created_date') ?>"
                               value="<?= e((string) $v('created_date')) ?>">
                        <?= field_error($errors, 'created_date') ?>
                    </div>

                    <div class="case-form-field">
                        <label class="case-form-label" for="due_date">Tanggal Jatuh Tempo Tertinggi <span class="text-danger">*</span></label>
                        <input type="date" id="due_date" name="due_date" required
                               class="form-control case-form-control <?= is_invalid($errors, 'due_date') ?>"
                               value="<?= e((string) $v('due_date')) ?>">
                        <?= field_error($errors, 'due_date') ?>
                    </div>

                    <div class="case-form-field">
                        <label class="case-form-label" for="officer_id">Nama Petugas <span class="text-danger">*</span></label>
                        <select id="officer_id" name="officer_id" class="form-select case-form-control searchable <?= is_invalid($errors, 'officer_id') ?>" required
                            <?= $lockedOfficerId !== null ? 'disabled' : '' ?>>
                            <option value="">— Pilih petugas —</option>
                            <?php foreach ($options['officers'] as $o): ?>
                                <option value="<?= (int) $o['id'] ?>" <?= $defaultOfficer === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($lockedOfficerId !== null): ?>
                            <input type="hidden" name="officer_id" value="<?= e((string) $lockedOfficerId) ?>">
                        <?php endif; ?>
                        <?= field_error($errors, 'officer_id') ?>
                    </div>
                </div>
            </section>

            <section class="case-form-section case-form-section--last">
                <h4 class="case-form-section__title">4. Catatan Progress</h4>
                <div class="case-form-field">
                    <label class="case-form-label" for="note">
                        Catatan perubahan
                        <span class="case-form-optional" id="noteOptionalLabel"><?= $isUpdateMode ? '(disarankan saat update)' : '(opsional)' ?></span>
                    </label>
                    <textarea id="note" name="note" rows="4" class="form-control case-form-control case-form-textarea"
                              placeholder="Contoh: status diubah ke Selesai setelah verifikasi dokumen selesai"
                              ><?= e((string) ($old['note'] ?? '')) ?></textarea>
                    <div class="case-form-hint" id="noteHint">
                        <?= $isUpdateMode
                            ? 'Catatan ini masuk ke riwayat perubahan. Kosongkan jika tidak ada keterangan tambahan — catatan terakhir kasus tidak akan terhapus.'
                            : 'Opsional untuk kasus baru. Akan tersimpan sebagai catatan awal.' ?>
                    </div>
                </div>
            </section>

            <div class="case-form-actions">
                <button type="submit" class="btn case-form-btn case-form-btn--primary" id="btnSubmitCase" data-loading-btn>
                    <i class="bi <?= $isUpdateMode ? 'bi-arrow-repeat' : 'bi-check2-circle' ?>" aria-hidden="true" id="btnSubmitIcon"></i>
                    <span id="btnSubmitLabel"><?= $isUpdateMode ? 'Perbarui Kasus' : 'Simpan Kasus Baru' ?></span>
                </button>
                <a href="<?= e(url('/cases')) ?>" class="btn case-form-btn case-form-btn--ghost">Batal</a>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
window.KAJANG_CASE_FORM = {
  lookupUrl: <?= json_encode(url('/api/cases/by-number')) ?>,
  searchUrl: <?= json_encode(url('/api/cases/search')) ?>,
  needsConfirm: <?= $needsConfirm && is_array($existingCase) ? 'true' : 'false' ?>,
  existingCase: <?= json_encode($existingCase, JSON_UNESCAPED_UNICODE) ?>,
  mode: <?= json_encode($mode) ?>,
  hasOldInput: <?= $old !== [] ? 'true' : 'false' ?>
};
</script>
<script src="<?= e(asset('js/cases-form.js')) ?>"></script>
