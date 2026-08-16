<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Repositories\StatusRepository;

/**
 * Normalize URL query filters so KPI deep-links and forms stay consistent.
 *
 * Supports:
 *   officer=3 | officer_id=3
 *   status=diproses | status_id=2
 *   deadline=h3 | terlambat | hari_ini
 *   case_type / case_type_id, source / source_id
 *   q, case_number, npwp, taxpayer_name
 *   created_from/to, due_from/to, periode
 */
final class FilterNormalizer
{
    /**
     * @param array<string,mixed>|null $input
     * @return array<string,mixed>
     */
    public static function normalizeCaseFilters(?array $input = null): array
    {
        $input ??= Request::all();

        $deadline = DeadlineClassifier::normalizeKey(
            (string) ($input['deadline'] ?? $input['dl'] ?? 'all')
        );

        $officerFromId = self::intOrNull($input['officer_id'] ?? null);
        $officerFromAlias = self::intOrNull($input['officer'] ?? null);
        // If both exist and differ (e.g. stale officer_id + new officer= from name click), prefer officer=
        $officerId = $officerFromAlias ?? $officerFromId;
        if ($officerFromId !== null && $officerFromAlias !== null) {
            $officerId = $officerFromAlias;
        }
        $typeId = self::intOrNull($input['case_type_id'] ?? $input['case_type'] ?? $input['type'] ?? null);
        $sourceId = self::intOrNull($input['source_id'] ?? $input['source'] ?? null);
        $statusId = self::intOrNull($input['status_id'] ?? null);

        if ($statusId === null) {
            $statusRaw = trim((string) ($input['status'] ?? ''));
            if ($statusRaw !== '' && !ctype_digit($statusRaw)) {
                $statusId = self::resolveStatusIdBySlug($statusRaw);
            } elseif ($statusRaw !== '' && ctype_digit($statusRaw)) {
                $statusId = (int) $statusRaw;
            }
        }

        return [
            'q' => trim((string) ($input['q'] ?? '')),
            'case_number' => trim((string) ($input['case_number'] ?? $input['nomor'] ?? '')),
            'npwp' => trim((string) ($input['npwp'] ?? '')),
            'taxpayer_name' => trim((string) ($input['taxpayer_name'] ?? $input['nama'] ?? '')),
            'officer_id' => $officerId,
            'case_type_id' => $typeId,
            'source_id' => $sourceId,
            'status_id' => $statusId,
            'deadline' => $deadline,
            'created_from' => trim((string) ($input['created_from'] ?? $input['periode_from'] ?? '')),
            'created_to' => trim((string) ($input['created_to'] ?? $input['periode_to'] ?? '')),
            'due_from' => trim((string) ($input['due_from'] ?? '')),
            'due_to' => trim((string) ($input['due_to'] ?? '')),
        ];
    }

    /**
     * Scope filters for petugas role (backend enforcement).
     *
     * @param array<string,mixed> $filters
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public static function applyRoleScope(array $filters, ?array $user): array
    {
        if (($user['role'] ?? '') === 'petugas') {
            $filters['officer_id'] = $user['officer_id'] !== null ? (int) $user['officer_id'] : -1;
        }
        return $filters;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }

    private static function resolveStatusIdBySlug(string $slug): ?int
    {
        try {
            $slug = strtolower($slug);
            foreach ((new StatusRepository())->activeOptions() as $row) {
                if (strtolower((string) $row['slug']) === $slug || strtolower((string) $row['name']) === $slug) {
                    return (int) $row['id'];
                }
            }
            // inactive too
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id FROM case_statuses WHERE slug = :slug OR LOWER(name) = :name LIMIT 1');
            $stmt->execute(['slug' => $slug, 'name' => $slug]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
