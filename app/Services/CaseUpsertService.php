<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CaseHistoryRepository;
use App\Repositories\CaseRepository;
use App\Repositories\CaseTypeRepository;
use App\Repositories\OfficerRepository;
use App\Repositories\SourceRepository;
use App\Repositories\StatusRepository;
use PDOException;
use RuntimeException;
use Throwable;

final class CaseUpsertService
{
    public const TRACKED_FIELDS = [
        'npwp',
        'taxpayer_name',
        'case_type_id',
        'status_id',
        'source_id',
        'created_date',
        'due_date',
        'officer_id',
        'last_note',
    ];

    public function __construct(
        private readonly CaseRepository $cases = new CaseRepository(),
        private readonly CaseHistoryRepository $histories = new CaseHistoryRepository(),
        private readonly CaseTypeRepository $types = new CaseTypeRepository(),
        private readonly StatusRepository $statuses = new StatusRepository(),
        private readonly SourceRepository $sources = new SourceRepository(),
        private readonly OfficerRepository $officers = new OfficerRepository(),
    ) {
    }

    /**
     * Normalize & validate payload. Returns ['ok'=>bool, 'errors'=>[], 'data'=>[]].
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,errors:array,data:array}
     */
    public function validate(array $input): array
    {
        $errors = [];

        $caseNumber = strtoupper(trim((string) ($input['case_number'] ?? '')));
        $npwp = preg_replace('/\D+/', '', (string) ($input['npwp'] ?? '')) ?? '';
        $taxpayer = trim((string) ($input['taxpayer_name'] ?? ''));
        $note = trim((string) ($input['note'] ?? ($input['last_note'] ?? '')));
        $createdDate = $this->normalizeDate((string) ($input['created_date'] ?? ''));
        $dueDate = $this->normalizeDate((string) ($input['due_date'] ?? ''));

        $caseTypeId = (int) ($input['case_type_id'] ?? 0);
        $statusId = (int) ($input['status_id'] ?? 0);
        $sourceId = (int) ($input['source_id'] ?? 0);
        $officerId = (int) ($input['officer_id'] ?? 0);

        if ($caseNumber === '') {
            $errors['case_number'][] = 'Nomor kasus wajib diisi.';
        } elseif (!preg_match('/^[A-Z][0-9]{10}$/', $caseNumber)) {
            $errors['case_number'][] = 'Nomor kasus harus 1 huruf + 10 angka (contoh: P0000000001).';
        }

        if ($npwp === '') {
            $errors['npwp'][] = 'NPWP wajib diisi.';
        } elseif (!preg_match('/^[0-9]{16}$/', $npwp)) {
            $errors['npwp'][] = 'NPWP harus tepat 16 digit angka.';
        }

        if ($taxpayer === '') {
            $errors['taxpayer_name'][] = 'Nama wajib pajak wajib diisi.';
        } elseif (mb_strlen($taxpayer) > 255) {
            $errors['taxpayer_name'][] = 'Nama wajib pajak maksimal 255 karakter.';
        }

        if ($caseTypeId < 1 || $this->types->findById($caseTypeId) === null) {
            $errors['case_type_id'][] = 'Jenis kasus tidak valid.';
        }
        if ($statusId < 1 || $this->statuses->findById($statusId) === null) {
            $errors['status_id'][] = 'Status kasus tidak valid.';
        }
        if ($sourceId < 1 || $this->sources->findById($sourceId) === null) {
            $errors['source_id'][] = 'Sumber kasus tidak valid.';
        }
        if ($officerId < 1 || $this->officers->findById($officerId) === null) {
            $errors['officer_id'][] = 'Petugas tidak valid.';
        }

        if ($createdDate === null) {
            $errors['created_date'][] = 'Tanggal dibuat tidak valid. Gunakan format dd-mm-yyyy atau yyyy-mm-dd.';
        }
        if ($dueDate === null) {
            $errors['due_date'][] = 'Tanggal jatuh tempo tidak valid. Gunakan format dd-mm-yyyy atau yyyy-mm-dd.';
        }

        $data = [
            'case_number' => $caseNumber,
            'npwp' => $npwp,
            'taxpayer_name' => $taxpayer,
            'case_type_id' => $caseTypeId,
            'status_id' => $statusId,
            'source_id' => $sourceId,
            'created_date' => $createdDate,
            'due_date' => $dueDate,
            'officer_id' => $officerId,
            'last_note' => $note !== '' ? $note : null,
        ];

        return ['ok' => $errors === [], 'errors' => $errors, 'data' => $data];
    }

    /**
     * Upsert by case_number inside a transaction.
     *
     * @param array<string,mixed> $validated Data from validate()['data']
     * @return array{
     *   action: 'created'|'updated'|'unchanged',
     *   case_id: int,
     *   case: array,
     *   changed_fields: array,
     *   message: string
     * }
     */
    public function upsert(array $validated, int $userId, bool $confirmExisting = false): array
    {
        $db = Database::connection();
        $caseNumber = $validated['case_number'];

        try {
            $db->beginTransaction();

            // Lock existing row if present (prevents race duplicates)
            $stmt = $db->prepare('SELECT * FROM cases WHERE case_number = :n FOR UPDATE');
            $stmt->execute(['n' => $caseNumber]);
            $existing = $stmt->fetch();

            if ($existing === false) {
                $id = $this->cases->create(array_merge($validated, [
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]));

                $this->histories->create([
                    'case_id' => $id,
                    'event_type' => 'CREATED',
                    'old_status_id' => null,
                    'new_status_id' => $validated['status_id'],
                    'changed_fields' => $this->enrichDiffLabels($this->snapshotFields($validated)),
                    'note' => $validated['last_note'],
                    'changed_by' => $userId,
                ]);

                $db->commit();

                $case = $this->cases->findById($id);
                AuditLogger::log('case_created', $userId, 'case', $id, [
                    'module' => 'CASE',
                    'description' => 'Kasus baru dibuat — ' . $caseNumber,
                    'case_number' => $caseNumber,
                    'new_values' => [
                        'case_number' => $caseNumber,
                        'npwp' => $validated['npwp'],
                        'taxpayer_name' => $validated['taxpayer_name'],
                        'status' => $this->labelForField('status_id', $validated['status_id']),
                        'officer' => $this->labelForField('officer_id', $validated['officer_id']),
                        'due_date' => $this->labelForField('due_date', $validated['due_date']),
                        'case_type' => $this->labelForField('case_type_id', $validated['case_type_id']),
                        'source' => $this->labelForField('source_id', $validated['source_id']),
                    ],
                ]);

                return [
                    'action' => 'created',
                    'case_id' => $id,
                    'case' => $case ?? [],
                    'changed_fields' => [],
                    'message' => 'Kasus baru berhasil disimpan.',
                ];
            }

            if (!$confirmExisting) {
                $db->rollBack();
                throw new CaseNeedsConfirmationException(
                    'Nomor kasus sudah terdaftar. Lanjutkan untuk memperbarui data utama (bukan membuat duplikat); riwayat tetap dicatat.',
                    $this->cases->findByNumber($caseNumber) ?? $existing
                );
            }

            $existingId = (int) $existing['id'];

            // Empty note on update = keep previous last_note (progress note is optional)
            $historyNote = $validated['last_note'];
            if ($validated['last_note'] === null || $validated['last_note'] === '') {
                $validated['last_note'] = $existing['last_note'] ?? null;
            }

            $diff = $this->diff($existing, $validated);

            if ($diff === []) {
                $db->commit();
                $case = $this->cases->findById($existingId);
                return [
                    'action' => 'unchanged',
                    'case_id' => $existingId,
                    'case' => $case ?? [],
                    'changed_fields' => [],
                    'message' => 'Data sudah sama, tidak ada perubahan yang perlu disimpan.',
                ];
            }

            $this->cases->update($existingId, array_merge($validated, [
                'updated_by' => $userId,
            ]));

            $oldStatusId = (int) $existing['status_id'];
            $newStatusId = (int) $validated['status_id'];
            $statusChanged = $oldStatusId !== $newStatusId;
            $officerChanged = (int) $existing['officer_id'] !== (int) $validated['officer_id'];

            $eventType = 'UPDATED';
            if ($statusChanged) {
                $eventType = 'STATUS_CHANGED';
            } elseif ($officerChanged) {
                $eventType = 'ASSIGNED';
            }

            // Reopen detection
            $oldStatus = $this->statuses->findById($oldStatusId);
            $newStatus = $this->statuses->findById($newStatusId);
            if (
                $statusChanged
                && $oldStatus
                && $newStatus
                && (int) $oldStatus['is_completed'] === 1
                && (int) $newStatus['is_completed'] === 0
            ) {
                $eventType = 'REOPENED';
            }

            // Prefer storing human-readable status in parallel for audit clarity
            // (IDs remain in changed_fields for machine use)
            $this->histories->create([
                'case_id' => $existingId,
                'event_type' => $eventType,
                'old_status_id' => $statusChanged ? $oldStatusId : null,
                'new_status_id' => $statusChanged ? $newStatusId : null,
                'changed_fields' => $this->enrichDiffLabels($diff),
                'note' => $historyNote,
                'changed_by' => $userId,
            ]);

            $db->commit();

            $case = $this->cases->findById($existingId);
            $enriched = $this->enrichDiffLabels($diff);
            [$oldFlat, $newFlat] = $this->flattenDiffForAudit($enriched);
            $newStatus = $this->statuses->findById($newStatusId);
            $completed = $newStatus && (int) $newStatus['is_completed'] === 1 && $statusChanged;
            $auditAction = $completed ? 'case_completed' : 'case_updated';
            $descParts = [];
            if ($statusChanged) {
                $descParts[] = 'status';
            }
            if ($officerChanged) {
                $descParts[] = 'petugas';
            }
            if (isset($diff['due_date'])) {
                $descParts[] = 'deadline';
            }
            if (isset($diff['npwp']) || isset($diff['taxpayer_name'])) {
                $descParts[] = 'data WP';
            }
            $desc = $completed
                ? 'Kasus diselesaikan — ' . $caseNumber
                : ('Kasus diperbarui' . ($descParts !== [] ? ' (' . implode(', ', $descParts) . ')' : '') . ' — ' . $caseNumber);

            AuditLogger::log($auditAction, $userId, 'case', $existingId, [
                'module' => 'CASE',
                'description' => $desc,
                'case_number' => $caseNumber,
                'event_type' => $eventType,
                'old_values' => $oldFlat + ['case_number' => $caseNumber],
                'new_values' => $newFlat + ['case_number' => $caseNumber],
            ]);

            return [
                'action' => 'updated',
                'case_id' => $existingId,
                'case' => $case ?? [],
                'changed_fields' => $diff,
                'message' => 'Kasus berhasil diperbarui. Riwayat perubahan telah dicatat.',
            ];
        } catch (CaseNeedsConfirmationException $e) {
            throw $e;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Duplicate key race — treat as update path conflict
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'uk_cases_case_number')) {
                throw new RuntimeException(
                    'Nomor kasus bentrok (UNIQUE). Muat ulang dan konfirmasi pembaruan.',
                    0,
                    $e
                );
            }
            throw $e;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $incoming
     * @return array<string,array{old:mixed,new:mixed}>
     */
    private function diff(array $existing, array $incoming): array
    {
        $changed = [];
        foreach (self::TRACKED_FIELDS as $field) {
            $old = $existing[$field] ?? null;
            $new = $incoming[$field] ?? null;

            if (in_array($field, ['case_type_id', 'status_id', 'source_id', 'officer_id'], true)) {
                $old = (int) $old;
                $new = (int) $new;
            } elseif (in_array($field, ['created_date', 'due_date'], true)) {
                $old = $old !== null ? substr((string) $old, 0, 10) : null;
                $new = $new !== null ? substr((string) $new, 0, 10) : null;
            } elseif ($field === 'last_note') {
                $old = $old === null || $old === '' ? null : (string) $old;
                $new = $new === null || $new === '' ? null : (string) $new;
            } else {
                $old = $old === null ? null : (string) $old;
                $new = $new === null ? null : (string) $new;
            }

            if ($old !== $new) {
                $changed[$field] = ['old' => $old, 'new' => $new];
            }
        }
        return $changed;
    }

    /** @param array<string,mixed> $data */
    private function snapshotFields(array $data): array
    {
        $snap = [];
        foreach (self::TRACKED_FIELDS as $field) {
            $snap[$field] = ['old' => null, 'new' => $data[$field] ?? null];
        }
        return $snap;
    }

    /**
     * @param array<string,array{old:mixed,new:mixed}> $diff
     * @return array<string,array{old:mixed,new:mixed,old_label?:string,new_label?:string}>
     */
    private function enrichDiffLabels(array $diff): array
    {
        foreach ($diff as $field => &$change) {
            $change['old_label'] = $this->labelForField((string) $field, $change['old'] ?? null);
            $change['new_label'] = $this->labelForField((string) $field, $change['new'] ?? null);
        }
        unset($change);
        return $diff;
    }

    private function labelForField(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($field, ['created_date', 'due_date'], true)) {
            return format_date_id((string) $value);
        }

        if ($field === 'status_id') {
            $row = $this->statuses->findById((int) $value);
            return $row['name'] ?? ('#' . (int) $value);
        }
        if ($field === 'case_type_id') {
            $row = $this->types->findById((int) $value);
            return $row['name'] ?? ('#' . (int) $value);
        }
        if ($field === 'source_id') {
            $row = $this->sources->findById((int) $value);
            return $row['name'] ?? ('#' . (int) $value);
        }
        if ($field === 'officer_id') {
            $row = $this->officers->findById((int) $value);
            return $row['name'] ?? ('#' . (int) $value);
        }

        return (string) $value;
    }

    /**
     * @param array<string,array{old:mixed,new:mixed,old_label?:string,new_label?:string}> $enriched
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function flattenDiffForAudit(array $enriched): array
    {
        $labels = [
            'status_id' => 'status',
            'officer_id' => 'officer',
            'due_date' => 'due_date',
            'created_date' => 'created_date',
            'npwp' => 'npwp',
            'taxpayer_name' => 'taxpayer_name',
            'case_type_id' => 'case_type',
            'source_id' => 'source',
            'last_note' => 'note',
        ];
        $oldFlat = [];
        $newFlat = [];
        foreach ($enriched as $field => $change) {
            $key = $labels[$field] ?? $field;
            $oldFlat[$key] = $change['old_label'] ?? $change['old'];
            $newFlat[$key] = $change['new_label'] ?? $change['new'];
        }
        return [$oldFlat, $newFlat];
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // yyyy-mm-dd (HTML date input)
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $raw) {
            return $raw;
        }

        // dd-mm-yyyy
        $dt = \DateTimeImmutable::createFromFormat('d-m-Y', $raw);
        if ($dt instanceof \DateTimeImmutable && $dt->format('d-m-Y') === $raw) {
            return $dt->format('Y-m-d');
        }

        // dd/mm/yyyy
        $dt = \DateTimeImmutable::createFromFormat('d/m/Y', $raw);
        if ($dt instanceof \DateTimeImmutable && $dt->format('d/m/Y') === $raw) {
            return $dt->format('Y-m-d');
        }

        return null;
    }
}
