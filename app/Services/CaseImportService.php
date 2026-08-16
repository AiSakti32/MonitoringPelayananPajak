<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

/**
 * Import kasus dari Excel/CSV sesuai format client.
 * Satu Nomor Kasus = upsert (create / update + riwayat).
 */
final class CaseImportService
{
    public const MAX_ROWS = 1000;
    public const MAX_BYTES = 5_242_880; // 5 MB

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'case_number' => ['nomor kasus', 'no kasus', 'nomor', 'case number'],
        'npwp' => ['npwp wajib pajak pusat', 'npwp', 'npwp wp pusat'],
        'taxpayer_name' => ['nama wajib pajak pusat', 'nama wp', 'nama wajib pajak', 'nama wp pusat'],
        'case_type' => ['jenis kasus', 'jenis'],
        'status' => ['status kasus', 'status'],
        'source' => ['sumber kasus', 'sumber ka', 'sumber'],
        'created_date' => ['dibuat', 'tanggal dibuat', 'tgl dibuat', 'created'],
        'due_date' => ['tanggal jatuh tempo tertinggi', 'tanggal jatuh tempo', 'jatuh tempo', 'due date', 'deadline'],
        'officer' => ['nama petugas', 'petugas', 'officer'],
    ];

    public function __construct(
        private readonly SpreadsheetReader $reader = new SpreadsheetReader(),
        private readonly CaseUpsertService $upsert = new CaseUpsertService(),
        private readonly \App\Repositories\CaseTypeRepository $types = new \App\Repositories\CaseTypeRepository(),
        private readonly \App\Repositories\StatusRepository $statuses = new \App\Repositories\StatusRepository(),
        private readonly \App\Repositories\SourceRepository $sources = new \App\Repositories\SourceRepository(),
        private readonly \App\Repositories\OfficerRepository $officers = new \App\Repositories\OfficerRepository(),
        private readonly \App\Repositories\CaseRepository $cases = new \App\Repositories\CaseRepository(),
    ) {
    }

    /**
     * @param array<string,mixed> $user
     * @return array{
     *   ok:bool,
     *   message:string,
     *   summary: array{total:int,created:int,updated:int,unchanged:int,failed:int},
     *   errors: list<array{row:int,case_number:string,message:string}>,
     *   successes: list<array{row:int,case_number:string,action:string}>
     * }
     */
    public function importUploadedFile(array $file, array $user): array
    {
        $this->assertUpload($file);

        $tmp = (string) $file['tmp_name'];
        $name = (string) ($file['name'] ?? 'upload.xlsx');
        $rows = $this->reader->read($tmp, $name);

        if ($rows === []) {
            throw new RuntimeException('File kosong.');
        }

        $header = array_shift($rows);
        $map = $this->mapHeaders($header);
        $missing = [];
        foreach (array_keys(self::HEADER_ALIASES) as $key) {
            if (!isset($map[$key])) {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException(
                'Header Excel tidak lengkap. Pastikan ada kolom: Nomor Kasus, NPWP, Nama WP, Jenis Kasus, Status Kasus, Sumber Kasus, Dibuat, Tanggal Jatuh Tempo, Nama Petugas.'
            );
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Maksimal ' . self::MAX_ROWS . ' baris data per import.');
        }

        $lookups = $this->buildLookups();
        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        $lockedOfficerId = $role === 'petugas' ? (int) ($user['officer_id'] ?? 0) : null;
        if ($role === 'petugas' && $lockedOfficerId < 1) {
            throw new RuntimeException('Akun petugas belum dikaitkan ke master petugas.');
        }

        $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];
        $errors = [];
        $successes = [];

        foreach ($rows as $offset => $row) {
            $excelRow = $offset + 2; // header = 1
            if ($this->rowIsEmpty($row)) {
                continue;
            }
            $summary['total']++;

            try {
                $payload = $this->rowToPayload($row, $map, $lookups, $lockedOfficerId, $user);
                $existing = $this->cases->findByNumber($payload['case_number']);
                if ($existing !== null && $role === 'petugas' && (int) $existing['officer_id'] !== $lockedOfficerId) {
                    throw new RuntimeException('Kasus milik petugas lain — tidak boleh diperbarui.');
                }
                $payload['note'] = $existing === null ? 'Import dari Excel' : '';

                $validated = $this->upsert->validate($payload);
                if (!$validated['ok']) {
                    throw new RuntimeException($this->flattenErrors($validated['errors']));
                }

                $result = $this->upsert->upsert($validated['data'], $userId, true);
                $action = (string) $result['action'];
                if (isset($summary[$action])) {
                    $summary[$action]++;
                }
                $successes[] = [
                    'row' => $excelRow,
                    'case_number' => $payload['case_number'],
                    'action' => $action,
                ];
            } catch (Throwable $e) {
                $summary['failed']++;
                $caseNumber = '';
                if (isset($map['case_number'], $row[$map['case_number']])) {
                    $caseNumber = strtoupper(trim((string) $row[$map['case_number']]));
                }
                $errors[] = [
                    'row' => $excelRow,
                    'case_number' => $caseNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $ok = $summary['failed'] === 0 && $summary['total'] > 0;
        $message = sprintf(
            'Import selesai: %d baris diproses (%d baru, %d diperbarui, %d tanpa perubahan, %d gagal).',
            $summary['total'],
            $summary['created'],
            $summary['updated'],
            $summary['unchanged'],
            $summary['failed']
        );
        if ($summary['total'] === 0) {
            $message = 'Tidak ada baris data yang bisa diimport.';
            $ok = false;
        }

        return [
            'ok' => $ok,
            'message' => $message,
            'summary' => $summary,
            'errors' => $errors,
            'successes' => $successes,
        ];
    }

    /** @return list<string> */
    public static function templateHeaders(): array
    {
        return [
            'Nomor Kasus',
            'NPWP Wajib Pajak Pusat',
            'Nama Wajib Pajak Pusat',
            'Jenis Kasus',
            'Status Kasus',
            'Sumber Kasus',
            'Dibuat',
            'Tanggal Jatuh Tempo Tertinggi',
            'Nama Petugas',
        ];
    }

    /** @param array<string,mixed> $file */
    private function assertUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload gagal. Pilih file Excel (.xlsx) atau CSV.');
        }
        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('File kosong.');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Ukuran file maksimal 5 MB.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('File upload tidak valid.');
        }
    }

    /**
     * @param list<string> $header
     * @return array<string,int>
     */
    private function mapHeaders(array $header): array
    {
        $map = [];
        foreach ($header as $i => $label) {
            $norm = $this->normalizeHeader((string) $label);
            if ($norm === '') {
                continue;
            }
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }
                if (in_array($norm, $aliases, true)) {
                    $map[$field] = (int) $i;
                }
            }
        }
        return $map;
    }

    private function normalizeHeader(string $label): string
    {
        $label = strtolower(trim($label));
        $label = str_replace(["\xC2\xA0", '_'], [' ', ' '], $label);
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        return $label;
    }

    /**
     * @return array{
     *   types: array<string,int>,
     *   statuses: array<string,int>,
     *   sources: array<string,int>,
     *   officers: array<string,int>
     * }
     */
    private function buildLookups(): array
    {
        return [
            'types' => $this->indexByName($this->types->activeOptions()),
            'statuses' => $this->indexByName($this->statuses->activeOptions()),
            'sources' => $this->indexByName($this->sources->activeOptions()),
            'officers' => $this->indexByName($this->officers->activeOptions()),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private function indexByName(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $name = $this->normalizeName((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $map[$name] = (int) $row['id'];
            }
        }
        return $map;
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return $name;
    }

    /** @param list<string> $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<string> $row
     * @param array<string,int> $map
     * @param array{types:array<string,int>,statuses:array<string,int>,sources:array<string,int>,officers:array<string,int>} $lookups
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function rowToPayload(array $row, array $map, array $lookups, ?int $lockedOfficerId, array $user): array
    {
        $get = static function (string $key) use ($row, $map): string {
            $idx = $map[$key] ?? null;
            if ($idx === null) {
                return '';
            }
            return trim((string) ($row[$idx] ?? ''));
        };

        $caseNumber = strtoupper($get('case_number'));
        $typeName = $get('case_type');
        $statusName = $get('status');
        $sourceName = $get('source');
        $officerName = $get('officer');

        $typeId = $lookups['types'][$this->normalizeName($typeName)] ?? null;
        if ($typeId === null) {
            throw new RuntimeException('Jenis kasus tidak dikenal: "' . $typeName . '". Samakan dengan master Jenis Kasus.');
        }
        $statusId = $lookups['statuses'][$this->normalizeName($statusName)] ?? null;
        if ($statusId === null) {
            throw new RuntimeException('Status tidak dikenal: "' . $statusName . '". Gunakan Dibuat / Diproses / Selesai.');
        }
        $sourceId = $lookups['sources'][$this->normalizeName($sourceName)] ?? null;
        if ($sourceId === null) {
            throw new RuntimeException('Sumber tidak dikenal: "' . $sourceName . '". Gunakan Portal / Core.');
        }

        if ($lockedOfficerId !== null) {
            $officerId = $lockedOfficerId;
            if ($officerName !== '') {
                $namedId = $lookups['officers'][$this->normalizeName($officerName)] ?? null;
                if ($namedId !== null && $namedId !== $lockedOfficerId) {
                    throw new RuntimeException('Nama petugas di Excel berbeda dengan akun Anda.');
                }
            }
        } else {
            $officerId = $lookups['officers'][$this->normalizeName($officerName)] ?? null;
            if ($officerId === null) {
                throw new RuntimeException('Petugas tidak dikenal: "' . $officerName . '". Samakan dengan master Petugas.');
            }
        }

        return [
            'case_number' => $caseNumber,
            'npwp' => $get('npwp'),
            'taxpayer_name' => $get('taxpayer_name'),
            'case_type_id' => $typeId,
            'status_id' => $statusId,
            'source_id' => $sourceId,
            'created_date' => $this->normalizeImportDate($get('created_date')),
            'due_date' => $this->normalizeImportDate($get('due_date')),
            'officer_id' => $officerId,
            'note' => '',
        ];
    }

    private function normalizeImportDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // ISO / Excel text datetime: 2026-08-10T08:39:57
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]\d{2}:\d{2}/', $raw, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        // Excel serial date (e.g. 45912 or 45912.3604)
        if (preg_match('/^\d+(\.\d+)?$/', $raw)) {
            $serial = (float) $raw;
            if ($serial > 20000 && $serial < 80000) {
                $days = (int) floor($serial);
                $base = new \DateTimeImmutable('1899-12-30', new \DateTimeZone('UTC'));
                return $base->modify('+' . $days . ' days')->format('Y-m-d');
            }
        }

        return $raw;
    }

    /** @param array<string,list<string>> $errors */
    private function flattenErrors(array $errors): string
    {
        $parts = [];
        foreach ($errors as $field => $msgs) {
            foreach ($msgs as $msg) {
                $parts[] = $msg;
            }
        }
        return $parts !== [] ? implode(' ', $parts) : 'Data baris tidak valid.';
    }
}
