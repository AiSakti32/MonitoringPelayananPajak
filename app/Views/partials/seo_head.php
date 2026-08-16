<?php
/**
 * SEO / privacy metadata for Kajang Lako (internal app: noindex).
 *
 * @var string|null $pageTitle
 */
$appName = (string) config('app.name', 'Kajang Lako');
$appUrl = rtrim((string) config('app.url', 'https://pelayanan331.com'), '/');
$pageTitle = trim((string) ($pageTitle ?? ''));
if ($pageTitle === '') {
    $pageTitle = $appName;
}

$lowerTitle = mb_strtolower($pageTitle);
$lowerApp = mb_strtolower($appName);
$documentTitle = $pageTitle;
if ($appName !== '' && !str_contains($lowerTitle, $lowerApp)) {
    $documentTitle = $pageTitle . ' | ' . $appName;
}

$description = 'Kajang Lako - Sistem Monitoring Permohonan dan Deadline Pelayanan.';
$ogDescription = 'Sistem Monitoring Permohonan dan Deadline Pelayanan.';

$logoCandidates = [
    'img/kajanglakologobersih.png',
    'img/logo.png',
];
$logoRel = $logoCandidates[0];
foreach ($logoCandidates as $candidate) {
    if (is_file(public_path('assets/' . $candidate))) {
        $logoRel = $candidate;
        break;
    }
}

$faviconHref = asset($logoRel);
$ogImage = $appUrl . '/assets/' . $logoRel;
$canonical = $appUrl !== '' ? $appUrl : 'https://pelayanan331.com';
?>
<title><?= e($documentTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
<link rel="icon" type="image/png" href="<?= e($faviconHref) ?>">
<link rel="apple-touch-icon" href="<?= e($faviconHref) ?>">
<meta property="og:title" content="<?= e($appName) ?>">
<meta property="og:description" content="<?= e($ogDescription) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($appName) ?>">
<meta name="twitter:description" content="<?= e($ogDescription) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
