<?php

declare(strict_types=1);

/**
 * Build query string preserving filters, overriding given keys.
 */
function query_url(string $path, array $overrides = [], array $current = null): string
{
    $current ??= $_GET;
    $params = array_merge($current, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    $qs = http_build_query($params);
    return url($path) . ($qs !== '' ? '?' . $qs : '');
}

/**
 * Resolve option name by id from master-data option lists.
 *
 * @param list<array{id?:mixed,name?:string}> $options
 */
function option_label_by_id(array $options, mixed $id, string $fallback = ''): string
{
    foreach ($options as $opt) {
        if ((string) ($opt['id'] ?? '') === (string) $id) {
            return (string) ($opt['name'] ?? $fallback);
        }
    }
    return $fallback !== '' ? $fallback : (string) $id;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function field_error(array $errors, string $field): string
{
    if (empty($errors[$field][0])) {
        return '';
    }
    return '<div class="invalid-feedback d-block">' . e((string) $errors[$field][0]) . '</div>';
}

function is_invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? 'is-invalid' : '';
}

function active_badge(int|bool $active): string
{
    if ((int) $active === 1) {
        return '<span class="badge badge-active">Aktif</span>';
    }
    return '<span class="badge badge-inactive">Nonaktif</span>';
}

/**
 * @param array{key?:string,label:string,tone:string} $deadline
 */
function deadline_badge(array $deadline): string
{
    $tone = $deadline['tone'] ?? 'normal';
    $label = $deadline['label'] ?? '—';
    return '<span class="badge badge-deadline tone-' . e($tone) . '">' . e($label) . '</span>';
}

/**
 * Humanize changed_fields JSON for timeline.
 *
 * @param array<string,mixed>|string|null $fields
 */
function format_changed_fields(array|string|null $fields): string
{
    if ($fields === null || $fields === '') {
        return '';
    }
    if (is_string($fields)) {
        $decoded = json_decode($fields, true);
        $fields = is_array($decoded) ? $decoded : [];
    }
    if ($fields === []) {
        return '';
    }

    $labels = [
        'npwp' => 'NPWP',
        'taxpayer_name' => 'Nama WP',
        'case_type_id' => 'Jenis Kasus',
        'status_id' => 'Status',
        'source_id' => 'Sumber',
        'created_date' => 'Tanggal Dibuat',
        'due_date' => 'Jatuh Tempo',
        'officer_id' => 'Petugas',
        'last_note' => 'Catatan',
    ];

    $parts = [];
    foreach ($fields as $key => $change) {
        if (!is_array($change)) {
            continue;
        }
        $label = $labels[$key] ?? $key;
        $old = $change['old'] ?? '—';
        $new = $change['new'] ?? '—';
        if (in_array($key, ['created_date', 'due_date'], true)) {
            $old = is_string($old) ? format_date_id($old) : '—';
            $new = is_string($new) ? format_date_id($new) : '—';
        }
        $parts[] = '<li><strong>' . e($label) . ':</strong> '
            . e((string) ($old ?? '—')) . ' → ' . e((string) ($new ?? '—')) . '</li>';
    }

    if ($parts === []) {
        return '';
    }
    return '<ul class="changed-fields">' . implode('', $parts) . '</ul>';
}
