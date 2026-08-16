<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

/**
 * Central audit logger. Never stores passwords, tokens, or DB credentials.
 */
final class AuditLogger
{
    private const SECRET_KEYS = [
        'password',
        'password_hash',
        'password_confirmation',
        'token',
        'secret',
        'csrf',
        'csrf_token',
        'session',
        'db_pass',
        'DB_PASS',
        'remember_token',
    ];

    private const RESERVED_CONTEXT = [
        'module',
        'description',
        'old_values',
        'new_values',
        'meta',
    ];

    /**
     * @param array{
     *   module?: string|null,
     *   description?: string|null,
     *   old_values?: array<string,mixed>|null,
     *   new_values?: array<string,mixed>|null,
     *   meta?: array<string,mixed>|null
     * }|array<string,mixed> $context
     */
    public static function log(
        string $action,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $context = []
    ): void {
        try {
            $module = isset($context['module']) && is_string($context['module']) && $context['module'] !== ''
                ? strtoupper($context['module'])
                : self::inferModule($action, $entityType);

            $description = isset($context['description']) && is_string($context['description'])
                ? mb_substr(trim($context['description']), 0, 500)
                : self::defaultDescription($action, $context);

            $oldValues = self::normalizeValues($context['old_values'] ?? null);
            $newValues = self::normalizeValues($context['new_values'] ?? null);

            $meta = [];
            if (isset($context['meta']) && is_array($context['meta'])) {
                $meta = $context['meta'];
            }
            foreach ($context as $key => $value) {
                if (in_array($key, self::RESERVED_CONTEXT, true)) {
                    continue;
                }
                $meta[$key] = $value;
            }
            $meta = self::scrub($meta);
            if ($meta === []) {
                $meta = null;
            }

            $db = Database::connection();
            $stmt = $db->prepare(
                'INSERT INTO audit_logs
                    (user_id, action, module, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent, meta, created_at)
                 VALUES
                    (:user_id, :action, :module, :entity_type, :entity_id, :description, :old_values, :new_values, :ip_address, :user_agent, :meta, :created_at)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'action' => mb_substr($action, 0, 100),
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description !== '' ? $description : null,
                'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => Request::ip(),
                'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
                'meta' => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now_jakarta(),
            ]);
        } catch (\Throwable $e) {
            $logFile = base_path('storage/logs/audit-fallback.log');
            @file_put_contents(
                $logFile,
                '[' . date('c') . '] Failed audit log: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'login_success' => 'Login berhasil',
            'login_failed' => 'Login gagal',
            'login_rate_limited' => 'Login dibatasi (rate limit)',
            'logout' => 'Logout',
            'case_created' => 'Buat kasus',
            'case_updated' => 'Perbarui kasus',
            'case_completed' => 'Selesaikan kasus',
            'officer_created' => 'Tambah petugas',
            'officer_updated' => 'Edit petugas',
            'officer_activated' => 'Aktifkan petugas',
            'officer_deactivated' => 'Nonaktifkan petugas',
            'case_type_created' => 'Tambah jenis kasus',
            'case_type_updated' => 'Edit jenis kasus',
            'case_type_activated' => 'Aktifkan jenis kasus',
            'case_type_deactivated' => 'Nonaktifkan jenis kasus',
            'status_created' => 'Tambah status kasus',
            'status_updated' => 'Edit status kasus',
            'status_activated' => 'Aktifkan status',
            'status_deactivated' => 'Nonaktifkan status',
            'source_created' => 'Tambah sumber kasus',
            'source_updated' => 'Edit sumber kasus',
            'source_activated' => 'Aktifkan sumber',
            'source_deactivated' => 'Nonaktifkan sumber',
            'user_created' => 'Buat user',
            'user_updated' => 'Perbarui user',
            'user_activated' => 'Aktifkan user',
            'user_deactivated' => 'Nonaktifkan user',
            default => str_replace('_', ' ', $action),
        };
    }

    private static function inferModule(string $action, ?string $entityType): string
    {
        if (str_starts_with($action, 'login') || $action === 'logout') {
            return 'AUTH';
        }
        if (str_starts_with($action, 'case_') && ($entityType === null || $entityType === 'case')) {
            return 'CASE';
        }
        if (str_starts_with($action, 'user_') || $entityType === 'user') {
            return 'USER';
        }
        if (in_array($entityType, ['officer', 'case_type', 'case_status', 'case_source'], true)
            || str_starts_with($action, 'officer_')
            || str_starts_with($action, 'case_type_')
            || str_starts_with($action, 'status_')
            || str_starts_with($action, 'source_')
        ) {
            return 'MASTER';
        }

        return 'SYSTEM';
    }

    /** @param array<string,mixed> $context */
    private static function defaultDescription(string $action, array $context): string
    {
        $label = self::actionLabel($action);
        if (isset($context['case_number']) && is_string($context['case_number']) && $context['case_number'] !== '') {
            return $label . ' — ' . $context['case_number'];
        }
        if (isset($context['username']) && is_string($context['username']) && $context['username'] !== '') {
            return $label . ' — ' . $context['username'];
        }
        if (isset($context['name']) && is_string($context['name']) && $context['name'] !== '') {
            return $label . ' — ' . $context['name'];
        }

        return $label;
    }

    /** @return array<string,mixed>|null */
    private static function normalizeValues(mixed $values): ?array
    {
        if (!is_array($values) || $values === []) {
            return null;
        }
        return self::scrub($values);
    }

    /** @param array<string,mixed> $data */
    private static function scrub(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $keyStr = (string) $key;
            if (self::isSecretKey($keyStr)) {
                continue;
            }
            if (is_array($value)) {
                $nested = self::scrub($value);
                if ($nested !== []) {
                    $clean[$keyStr] = $nested;
                }
                continue;
            }
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $clean[$keyStr] = $value;
            } else {
                $clean[$keyStr] = (string) $value;
            }
        }
        return $clean;
    }

    private static function isSecretKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SECRET_KEYS as $secret) {
            if ($lower === strtolower($secret) || str_contains($lower, 'password') || str_contains($lower, 'secret')) {
                return true;
            }
        }
        return false;
    }
}
