<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CaseTypeRepository;
use App\Repositories\OfficerRepository;
use App\Repositories\SourceRepository;
use App\Repositories\StatusRepository;

/**
 * Enrich case history rows for human-readable timeline display.
 */
final class CaseHistoryPresenter
{
    /** @var array<int,string> */
    private array $statusNames = [];
    /** @var array<int,string> */
    private array $typeNames = [];
    /** @var array<int,string> */
    private array $sourceNames = [];
    /** @var array<int,string> */
    private array $officerNames = [];

    private bool $loaded = false;

    public function __construct(
        private readonly StatusRepository $statuses = new StatusRepository(),
        private readonly CaseTypeRepository $types = new CaseTypeRepository(),
        private readonly SourceRepository $sources = new SourceRepository(),
        private readonly OfficerRepository $officers = new OfficerRepository(),
    ) {
    }

    /**
     * @param list<array> $histories
     * @return list<array>
     */
    public function presentMany(array $histories): array
    {
        $this->ensureMaps();
        return array_map(fn (array $h): array => $this->present($h), $histories);
    }

    /**
     * @param array<string,mixed> $history
     * @return array<string,mixed>
     */
    public function present(array $history): array
    {
        $this->ensureMaps();

        $fields = $history['changed_fields'] ?? null;
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            $fields = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($fields)) {
            $fields = [];
        }

        $changes = [];
        foreach ($fields as $key => $change) {
            if (!is_array($change)) {
                continue;
            }
            $label = self::fieldLabel((string) $key);
            $oldRaw = $change['old'] ?? null;
            $newRaw = $change['new'] ?? null;

            // Prefer explicit status names from history columns when status_id changed
            if ($key === 'status_id') {
                $oldDisplay = $change['old_label']
                    ?? $history['old_status_name']
                    ?? $this->resolveValue('status_id', $oldRaw)
                    ?? '—';
                $newDisplay = $change['new_label']
                    ?? $history['new_status_name']
                    ?? $this->resolveValue('status_id', $newRaw)
                    ?? '—';
            } else {
                $oldDisplay = $change['old_label'] ?? $this->resolveValue((string) $key, $oldRaw);
                $newDisplay = $change['new_label'] ?? $this->resolveValue((string) $key, $newRaw);
            }

            $isInitial = ($history['event_type'] ?? '') === 'CREATED'
                || ($oldRaw === null && ($change['old_label'] ?? null) === null && ($oldDisplay === '—' || $oldDisplay === ''));

            $changes[] = [
                'field' => (string) $key,
                'label' => $label,
                'old' => $oldDisplay,
                'new' => $newDisplay,
                'is_initial' => $isInitial && ($history['event_type'] ?? '') === 'CREATED',
            ];
        }

        // If status changed via columns but not in changed_fields (edge), add it
        if (
            $changes === []
            && (!empty($history['old_status_name']) || !empty($history['new_status_name']))
            && in_array(($history['event_type'] ?? ''), ['STATUS_CHANGED', 'UPDATED', 'REOPENED', 'CREATED'], true)
        ) {
            if (($history['event_type'] ?? '') !== 'CREATED' || !empty($history['old_status_name'])) {
                $changes[] = [
                    'field' => 'status_id',
                    'label' => 'Status',
                    'old' => (string) ($history['old_status_name'] ?: '—'),
                    'new' => (string) ($history['new_status_name'] ?: '—'),
                    'is_initial' => false,
                ];
            }
        }

        // CREATED with only new status
        if (($history['event_type'] ?? '') === 'CREATED' && $changes === [] && !empty($history['new_status_name'])) {
            $changes[] = [
                'field' => 'status_id',
                'label' => 'Status',
                'old' => '—',
                'new' => (string) $history['new_status_name'],
                'is_initial' => true,
            ];
        }

        // Prefer status / officer first in the list for scanning
        usort($changes, static function (array $a, array $b): int {
            $priority = ['status_id' => 0, 'officer_id' => 1, 'due_date' => 2, 'last_note' => 99];
            $pa = $priority[$a['field']] ?? 50;
            $pb = $priority[$b['field']] ?? 50;
            return $pa <=> $pb;
        });

        $history['changes'] = $changes;
        $history['change_count'] = count($changes);
        $history['event_label'] = self::eventLabel((string) ($history['event_type'] ?? ''));
        $history['event_tone'] = self::eventTone((string) ($history['event_type'] ?? ''));
        $actor = (string) ($history['changed_by_name'] ?? $history['actor_name'] ?? '');
        $history['actor'] = $actor !== '' ? $actor : 'Sistem';
        $history['narrative'] = $this->buildNarrative($history, $changes);
        $history['summary'] = $this->buildSummary($history, $changes);

        return $history;
    }

    /**
     * Short Indonesian summary for timeline cards.
     *
     * @param array<string,mixed> $history
     * @param list<array{field:string,label:string,old:string,new:string,is_initial?:bool}> $changes
     */
    private function buildSummary(array $history, array $changes): string
    {
        $event = (string) ($history['event_type'] ?? '');
        $actor = (string) ($history['actor'] ?? 'Sistem');

        if ($event === 'CREATED') {
            $status = '';
            foreach ($changes as $c) {
                if ($c['field'] === 'status_id') {
                    $status = $c['new'];
                    break;
                }
            }
            return $status !== ''
                ? $actor . ' membuat kasus dengan status ' . $status
                : $actor . ' membuat kasus baru';
        }

        if ($event === 'REOPENED') {
            foreach ($changes as $c) {
                if ($c['field'] === 'status_id') {
                    return $actor . ' membuka kembali kasus: ' . $c['old'] . ' → ' . $c['new'];
                }
            }
            return $actor . ' membuka kembali kasus';
        }

        if ($event === 'STATUS_CHANGED') {
            foreach ($changes as $c) {
                if ($c['field'] === 'status_id') {
                    $extra = count($changes) > 1 ? ' (+' . (count($changes) - 1) . ' field lain)' : '';
                    return $actor . ' mengubah status: ' . $c['old'] . ' → ' . $c['new'] . $extra;
                }
            }
        }

        if ($event === 'ASSIGNED') {
            foreach ($changes as $c) {
                if ($c['field'] === 'officer_id') {
                    return $actor . ' mengubah petugas: ' . $c['old'] . ' → ' . $c['new'];
                }
            }
        }

        if ($changes === []) {
            return $actor . ' menyimpan tanpa perubahan field';
        }

        $labels = [];
        foreach (array_slice($changes, 0, 4) as $c) {
            $labels[] = $c['label'];
        }
        $more = count($changes) > 4 ? ' +' . (count($changes) - 4) . ' lainnya' : '';
        return $actor . ' memperbarui ' . implode(', ', $labels) . $more;
    }

    public static function eventTone(string $event): string
    {
        return match ($event) {
            'CREATED' => 'created',
            'STATUS_CHANGED' => 'status',
            'ASSIGNED' => 'assigned',
            'REOPENED' => 'reopened',
            'UPDATED' => 'updated',
            default => 'updated',
        };
    }

    /**
     * Prose for Recent Activity / dashboard.
     * Example: "Cindy memperbarui P1234567890 — Status: Diproses → Selesai"
     *
     * @param array<string,mixed> $history
     * @param list<array{field:string,label:string,old:string,new:string}> $changes
     */
    private function buildNarrative(array $history, array $changes): string
    {
        $actor = (string) ($history['actor'] ?? 'Sistem');
        $caseNumber = (string) ($history['case_number'] ?? '');
        $event = (string) ($history['event_type'] ?? '');

        $verb = match ($event) {
            'CREATED' => 'membuat',
            'STATUS_CHANGED' => 'memperbarui',
            'ASSIGNED' => 'menugaskan',
            'REOPENED' => 'membuka kembali',
            default => 'memperbarui',
        };

        $head = trim($actor . ' ' . $verb . ($caseNumber !== '' ? ' ' . $caseNumber : ''));

        if ($changes === []) {
            return $head;
        }

        // Prefer status change line when present
        foreach ($changes as $c) {
            if ($c['field'] === 'status_id') {
                return $head . "\nStatus: {$c['old']} → {$c['new']}";
            }
        }

        $parts = [];
        foreach (array_slice($changes, 0, 3) as $c) {
            $parts[] = "{$c['label']}: {$c['old']} → {$c['new']}";
        }

        return $head . "\n" . implode('; ', $parts);
    }

    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'npwp' => 'NPWP',
            'taxpayer_name' => 'Nama WP',
            'case_type_id' => 'Jenis Kasus',
            'status_id' => 'Status',
            'source_id' => 'Sumber',
            'created_date' => 'Tanggal Dibuat',
            'due_date' => 'Jatuh Tempo',
            'officer_id' => 'Petugas',
            'last_note' => 'Catatan',
            default => str_replace('_', ' ', $field),
        };
    }

    public static function eventLabel(string $event): string
    {
        return match ($event) {
            'CREATED' => 'Dibuat',
            'UPDATED' => 'Diperbarui',
            'STATUS_CHANGED' => 'Perubahan Status',
            'ASSIGNED' => 'Penugasan Petugas',
            'REOPENED' => 'Dibuka Kembali',
            default => $event !== '' ? $event : 'Perubahan',
        };
    }

    private function resolveValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($field, ['created_date', 'due_date'], true) && is_string($value)) {
            return format_date_id($value);
        }

        if ($field === 'status_id') {
            $id = (int) $value;
            return $this->statusNames[$id] ?? ('#' . $id);
        }
        if ($field === 'case_type_id') {
            $id = (int) $value;
            return $this->typeNames[$id] ?? ('#' . $id);
        }
        if ($field === 'source_id') {
            $id = (int) $value;
            return $this->sourceNames[$id] ?? ('#' . $id);
        }
        if ($field === 'officer_id') {
            $id = (int) $value;
            return $this->officerNames[$id] ?? ('#' . $id);
        }

        return (string) $value;
    }

    private function ensureMaps(): void
    {
        if ($this->loaded) {
            return;
        }

        foreach ($this->statuses->activeOptions() as $row) {
            $this->statusNames[(int) $row['id']] = (string) $row['name'];
        }
        // Also load inactive statuses that may appear in history
        $this->loadAllStatuses();

        foreach ($this->types->activeOptions() as $row) {
            $this->typeNames[(int) $row['id']] = (string) $row['name'];
        }
        $this->loadAllTypes();

        foreach ($this->sources->activeOptions() as $row) {
            $this->sourceNames[(int) $row['id']] = (string) $row['name'];
        }
        $this->loadAllSources();

        foreach ($this->officers->activeOptions() as $row) {
            $this->officerNames[(int) $row['id']] = (string) $row['name'];
        }
        $this->loadAllOfficers();

        $this->loaded = true;
    }

    private function loadAllStatuses(): void
    {
        try {
            $pdo = \App\Core\Database::connection();
            foreach ($pdo->query('SELECT id, name FROM case_statuses')->fetchAll() as $row) {
                $this->statusNames[(int) $row['id']] = (string) $row['name'];
            }
        } catch (\Throwable) {
            // keep active-only map
        }
    }

    private function loadAllTypes(): void
    {
        try {
            $pdo = \App\Core\Database::connection();
            foreach ($pdo->query('SELECT id, name FROM case_types')->fetchAll() as $row) {
                $this->typeNames[(int) $row['id']] = (string) $row['name'];
            }
        } catch (\Throwable) {
        }
    }

    private function loadAllSources(): void
    {
        try {
            $pdo = \App\Core\Database::connection();
            foreach ($pdo->query('SELECT id, name FROM case_sources')->fetchAll() as $row) {
                $this->sourceNames[(int) $row['id']] = (string) $row['name'];
            }
        } catch (\Throwable) {
        }
    }

    private function loadAllOfficers(): void
    {
        try {
            $pdo = \App\Core\Database::connection();
            foreach ($pdo->query('SELECT id, name FROM officers')->fetchAll() as $row) {
                $this->officerNames[(int) $row['id']] = (string) $row['name'];
            }
        } catch (\Throwable) {
        }
    }
}
