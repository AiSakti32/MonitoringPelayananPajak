<?php
/**
 * Opens collapsible filter shell + toolbar.
 *
 * Layout (konsisten semua halaman):
 * 1) Heading opsional (subtitle/helper)
 * 2) Tombol Filter Data (+ CTA opsional)
 * 3) Chip filter aktif
 * 4) Konten setelah toolbar (quick tabs)
 * 5) Panel filter collapsible
 *
 * @var list<array{label:string,removeUrl:string}> $activeChips
 * @var string $resetUrl
 * @var string|null $headingHtml   Optional left-side page heading HTML
 * @var string|null $endActions    Optional HTML after Filter Data button (e.g. primary CTA)
 * @var string|null $afterToolbarHtml Optional HTML between toolbar and collapse (e.g. quick tabs)
 */
$activeChips = $activeChips ?? [];
$resetUrl = $resetUrl ?? '#';
$headingHtml = $headingHtml ?? null;
$endActions = $endActions ?? null;
$afterToolbarHtml = $afterToolbarHtml ?? null;
$activeFilterCount = count($activeChips);
$hasHeading = is_string($headingHtml) && $headingHtml !== '';
$hasEndActions = is_string($endActions) && $endActions !== '';
?>
<div class="dash-filter-shell mb-3<?= $hasHeading ? ' dash-filter-shell--headed' : '' ?><?= $hasEndActions ? ' dash-filter-shell--with-cta' : '' ?>">
    <div class="dash-filter-toolbar<?= $hasHeading ? ' dash-filter-toolbar--headed' : '' ?>">
        <?php if ($hasHeading): ?>
            <div class="dash-filter-heading"><?= $headingHtml ?></div>
        <?php endif; ?>

        <div class="dash-filter-toolbar__actions">
            <button type="button"
                    class="dash-filter-toggle"
                    aria-expanded="false">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                <span>Filter Data<?= $activeFilterCount > 0 ? ' (' . $activeFilterCount . ')' : '' ?></span>
                <i class="bi bi-chevron-down dash-filter-toggle__chevron" aria-hidden="true"></i>
            </button>
            <?php if ($hasEndActions): ?>
                <?= $endActions ?>
            <?php endif; ?>
        </div>

        <?php if ($activeChips !== []): ?>
            <div class="dash-filter-active" aria-label="Filter aktif">
                <?php foreach ($activeChips as $chip): ?>
                    <a href="<?= e((string) ($chip['removeUrl'] ?? '#')) ?>"
                       class="dash-filter-chip dash-filter-chip--dismiss"
                       title="Hapus filter ini">
                        <span class="dash-filter-chip__text"><?= e((string) ($chip['label'] ?? '')) ?></span>
                        <span class="dash-filter-chip__x" aria-hidden="true">&times;</span>
                    </a>
                <?php endforeach; ?>
                <a href="<?= e($resetUrl) ?>" class="dash-filter-chip-reset">Reset</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (is_string($afterToolbarHtml) && $afterToolbarHtml !== ''): ?>
        <div class="dash-filter-after-toolbar">
            <?= $afterToolbarHtml ?>
        </div>
    <?php endif; ?>

    <div class="dash-filter-collapse" data-collapsed="true">
        <div class="dash-filter-collapse__inner">
