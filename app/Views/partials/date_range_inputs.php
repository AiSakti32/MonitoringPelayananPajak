<?php
/**
 * Date range pair for filter panels.
 *
 * @var string $fromName
 * @var string $toName
 * @var string $fromValue
 * @var string $toValue
 * @var string $fromLabel
 * @var string $toLabel
 * @var string|null $fromId
 * @var string|null $toId
 */
$fromName = (string) ($fromName ?? 'date_from');
$toName = (string) ($toName ?? 'date_to');
$fromValue = (string) ($fromValue ?? '');
$toValue = (string) ($toValue ?? '');
$fromLabel = (string) ($fromLabel ?? 'Tanggal Mulai');
$toLabel = (string) ($toLabel ?? 'Tanggal Akhir');
$fromId = $fromId ?? null;
$toId = $toId ?? null;
$fromPh = 'Pilih tanggal';
$toPh = 'Pilih tanggal';
?>
<div class="app-filter-period" role="group" aria-label="Rentang tanggal">
    <div class="app-filter-period__item<?= $fromValue === '' ? ' is-empty' : '' ?>">
        <label class="app-filter-period__label" <?= $fromId ? 'for="' . e((string) $fromId) . '"' : '' ?>><?= e($fromLabel) ?></label>
        <div class="app-filter-date-wrap">
            <span class="app-filter-date-ph" aria-hidden="true"><?= e($fromPh) ?></span>
            <input type="date"
                   <?= $fromId ? 'id="' . e((string) $fromId) . '"' : '' ?>
                   name="<?= e($fromName) ?>"
                   class="form-control app-filter-control app-filter-date"
                   value="<?= e($fromValue) ?>"
                   aria-label="<?= e($fromLabel) ?>"
                   title="<?= e($fromLabel) ?>">
        </div>
    </div>
    <span class="app-filter-period__sep" aria-hidden="true">—</span>
    <div class="app-filter-period__item<?= $toValue === '' ? ' is-empty' : '' ?>">
        <label class="app-filter-period__label" <?= $toId ? 'for="' . e((string) $toId) . '"' : '' ?>><?= e($toLabel) ?></label>
        <div class="app-filter-date-wrap">
            <span class="app-filter-date-ph" aria-hidden="true"><?= e($toPh) ?></span>
            <input type="date"
                   <?= $toId ? 'id="' . e((string) $toId) . '"' : '' ?>
                   name="<?= e($toName) ?>"
                   class="form-control app-filter-control app-filter-date"
                   value="<?= e($toValue) ?>"
                   aria-label="<?= e($toLabel) ?>"
                   title="<?= e($toLabel) ?>">
        </div>
    </div>
</div>
