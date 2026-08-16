<?php

declare(strict_types=1);

function app_timezone(): string
{
    return (string) config('app.timezone', 'Asia/Jakarta');
}

function today_jakarta(): string
{
    $dt = new DateTimeImmutable('now', new DateTimeZone(app_timezone()));
    return $dt->format('Y-m-d');
}

function now_jakarta(): string
{
    $dt = new DateTimeImmutable('now', new DateTimeZone(app_timezone()));
    return $dt->format('Y-m-d H:i:s');
}

/**
 * Display date as dd-mm-yyyy.
 */
function format_date_id(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '—';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($date, 0, 10));
    if ($dt === false) {
        return e($date);
    }

    return $dt->format('d-m-Y');
}

/**
 * Compact date for dense tables: "28 Jul 2026" (display only).
 */
function format_date_short_id(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '—';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($date, 0, 10));
    if ($dt === false) {
        return e($date);
    }

    static $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    $month = $months[(int) $dt->format('n')] ?? $dt->format('M');
    return $dt->format('j') . ' ' . $month . ' ' . $dt->format('Y');
}

/**
 * NPWP display helper (does not alter stored value).
 */
function format_npwp_display(?string $npwp): string
{
    $raw = preg_replace('/\D+/', '', (string) $npwp) ?? '';
    if ($raw === '') {
        return (string) ($npwp ?: '—');
    }

    if (strlen($raw) === 15) {
        return substr($raw, 0, 2) . '.' . substr($raw, 2, 3) . '.' . substr($raw, 5, 3)
            . '.' . substr($raw, 8, 1) . '-' . substr($raw, 9, 3) . '.' . substr($raw, 12, 3);
    }

    if (strlen($raw) === 16) {
        return rtrim(chunk_split($raw, 3, '.'), '.');
    }

    return (string) $npwp;
}

/**
 * Display datetime as dd-mm-yyyy HH:ii.
 */
function format_datetime_id(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($datetime, new DateTimeZone(app_timezone()));
        return $dt->format('d-m-Y H:i');
    } catch (Throwable) {
        return e($datetime);
    }
}
