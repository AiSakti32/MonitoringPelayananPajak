<?php
/** @var array|null $result */
/** @var array $errors */
$result = is_array($result ?? null) ? $result : null;
$summary = is_array($result['summary'] ?? null) ? $result['summary'] : null;
$resultErrors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
$successes = is_array($result['successes'] ?? null) ? $result['successes'] : [];
?>
<div class="form-page case-import-page">
    <a href="<?= e(url('/cases')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Kembali ke daftar</a>

    <div class="panel-card form-card case-form-card mt-3">
        <div class="case-form-header">
            <div class="case-form-header__icon" aria-hidden="true"><i class="bi bi-file-earmark-excel"></i></div>
            <div>
                <h3 class="case-form-header__title">Import Kasus dari Excel</h3>
                <p class="case-form-header__desc">
                    Upload file sesuai format client. Satu <strong>Nomor Kasus</strong> = satu data utama:
                    nomor baru akan dibuat, nomor yang sudah ada akan diperbarui + tercatat di riwayat.
                </p>
            </div>
        </div>

        <div class="case-import-steps mb-4">
            <div class="case-import-step">
                <span class="case-import-step__num">1</span>
                <div>
                    <strong>Unduh template</strong>
                    <p class="mb-0">Samakan header kolom dengan format Excel client.</p>
                </div>
            </div>
            <div class="case-import-step">
                <span class="case-import-step__num">2</span>
                <div>
                    <strong>Isi data</strong>
                    <p class="mb-0">Jenis / Status / Sumber / Petugas harus sama dengan master di sistem.</p>
                </div>
            </div>
            <div class="case-import-step">
                <span class="case-import-step__num">3</span>
                <div>
                    <strong>Upload &amp; proses</strong>
                    <p class="mb-0">Sistem otomatis create atau update per nomor kasus.</p>
                </div>
            </div>
        </div>

        <div class="case-import-actions mb-4">
            <a class="btn case-form-btn case-form-btn--ghost" href="<?= e(url('/cases/import/template')) ?>">
                <i class="bi bi-download" aria-hidden="true"></i> Unduh template CSV
            </a>
        </div>

        <div class="case-import-columns mb-4">
            <h4 class="case-form-section__title">Kolom yang wajib ada</h4>
            <ol class="case-import-columns__list">
                <li>Nomor Kasus</li>
                <li>NPWP Wajib Pajak Pusat</li>
                <li>Nama Wajib Pajak Pusat</li>
                <li>Jenis Kasus</li>
                <li>Status Kasus</li>
                <li>Sumber Kasus</li>
                <li>Dibuat</li>
                <li>Tanggal Jatuh Tempo Tertinggi</li>
                <li>Nama Petugas</li>
            </ol>
            <p class="case-form-hint mb-0">
                Format tanggal didukung: <code>YYYY-MM-DD</code>, <code>YYYY-MM-DDTHH:MM:SS</code>, atau <code>DD-MM-YYYY</code>.
                File: <strong>.xlsx</strong> atau <strong>.csv</strong> (maks. 5 MB / 1000 baris).
            </p>
        </div>

        <form method="post" action="<?= e(url('/cases/import')) ?>" enctype="multipart/form-data" class="master-form" data-loading-form>
            <?= csrf_field() ?>
            <div class="case-form-field mb-3">
                <label class="case-form-label" for="excel_file">Pilih file Excel / CSV <span class="text-danger">*</span></label>
                <input type="file" id="excel_file" name="excel_file" class="form-control case-form-control case-import-file"
                       accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
            </div>
            <div class="case-form-actions">
                <button type="submit" class="btn case-form-btn case-form-btn--primary" data-loading-btn>
                    <i class="bi bi-upload" aria-hidden="true"></i> Proses Import
                </button>
                <a href="<?= e(url('/cases')) ?>" class="btn case-form-btn case-form-btn--ghost">Batal</a>
            </div>
        </form>
    </div>

    <?php if ($summary !== null): ?>
        <div class="panel-card form-card case-form-card mt-3">
            <h3 class="h6 mb-3">Hasil Import</h3>
            <div class="case-import-summary">
                <div><span>Diproses</span><strong><?= (int) $summary['total'] ?></strong></div>
                <div><span>Baru</span><strong><?= (int) $summary['created'] ?></strong></div>
                <div><span>Diperbarui</span><strong><?= (int) $summary['updated'] ?></strong></div>
                <div><span>Tanpa perubahan</span><strong><?= (int) $summary['unchanged'] ?></strong></div>
                <div><span>Gagal</span><strong class="<?= (int) $summary['failed'] > 0 ? 'text-danger' : '' ?>"><?= (int) $summary['failed'] ?></strong></div>
            </div>

            <?php if ($resultErrors !== []): ?>
                <h4 class="case-form-section__title mt-4">Baris gagal</h4>
                <div class="table-responsive">
                    <table class="table table-sm align-middle case-import-table">
                        <thead>
                        <tr>
                            <th>Baris</th>
                            <th>Nomor Kasus</th>
                            <th>Pesan</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resultErrors as $err): ?>
                            <tr>
                                <td><?= (int) ($err['row'] ?? 0) ?></td>
                                <td><?= e((string) ($err['case_number'] ?? '—')) ?></td>
                                <td><?= e((string) ($err['message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($successes !== [] && count($successes) <= 50): ?>
                <h4 class="case-form-section__title mt-4">Baris berhasil</h4>
                <div class="table-responsive">
                    <table class="table table-sm align-middle case-import-table">
                        <thead>
                        <tr>
                            <th>Baris</th>
                            <th>Nomor Kasus</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($successes as $okRow): ?>
                            <tr>
                                <td><?= (int) ($okRow['row'] ?? 0) ?></td>
                                <td><?= e((string) ($okRow['case_number'] ?? '')) ?></td>
                                <td>
                                    <?php
                                    $act = (string) ($okRow['action'] ?? '');
                                    echo e(match ($act) {
                                        'created' => 'Dibuat',
                                        'updated' => 'Diperbarui',
                                        'unchanged' => 'Tanpa perubahan',
                                        default => $act,
                                    });
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
