<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Services\AuditLogger;
use Throwable;

final class AuditLogController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly AuditLogRepository $logs = new AuditLogRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function index(): void
    {
        $filters = [
            'q' => trim((string) Request::input('q', '')),
            'user_id' => Request::input('user_id', '') !== '' && Request::input('user_id', '') !== null
                ? (int) Request::input('user_id')
                : null,
            'role' => (string) Request::input('role', 'all'),
            'action' => (string) Request::input('action', 'all'),
            'module' => (string) Request::input('module', 'all'),
            'date_from' => trim((string) Request::input('date_from', '')),
            'date_to' => trim((string) Request::input('date_to', '')),
        ];
        $page = (int) Request::input('page', 1);

        try {
            $paginator = $this->logs->paginate($filters, $page, self::PER_PAGE);
            $actions = $this->logs->distinctActions();
            $modules = $this->logs->distinctModules();
            $userOptions = $this->users->paginate('', 'all', 'all', 1, 500)->items;
            $loadError = null;
        } catch (Throwable $e) {
            $paginator = null;
            $actions = [];
            $modules = [];
            $userOptions = [];
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat audit log. Periksa koneksi database.';
        }

        $this->render('audit_logs/index', [
            'pageTitle' => 'Audit Log',
            'paginator' => $paginator,
            'filters' => $filters,
            'actions' => $actions,
            'modules' => $modules,
            'userOptions' => $userOptions,
            'loadError' => $loadError,
            'basePath' => '/audit-logs',
        ]);
    }

    public function show(string $id): void
    {
        $row = $this->logs->findById((int) $id);
        if ($row === null) {
            json_response(['ok' => false, 'message' => 'Audit log tidak ditemukan.'], 404);
        }

        $old = $this->decodeJson($row['old_values'] ?? null);
        $new = $this->decodeJson($row['new_values'] ?? null);
        $meta = $this->decodeJson($row['meta'] ?? null);

        // Legacy fallback: older rows may only have meta
        if ($old === null && $new === null && is_array($meta) && $meta !== []) {
            $new = $meta;
        }

        $reference = $this->buildReference($row, $meta, $new);

        json_response([
            'ok' => true,
            'item' => [
                'id' => (int) $row['id'],
                'created_at' => format_datetime_id($row['created_at'] ?? null),
                'created_at_raw' => $row['created_at'] ?? null,
                'user_name' => $row['user_name'] ?? null,
                'user_username' => $row['user_username'] ?? null,
                'user_role' => $row['user_role'] ?? null,
                'action' => $row['action'],
                'action_label' => AuditLogger::actionLabel((string) $row['action']),
                'module' => $row['module'] ?? null,
                'entity_type' => $row['entity_type'] ?? null,
                'entity_id' => $row['entity_id'] ?? null,
                'reference' => $reference,
                'description' => $row['description'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
                'user_agent' => $row['user_agent'] ?? null,
                'old_values' => $old,
                'new_values' => $new,
            ],
        ]);
    }

    /** @return array<string,mixed>|null */
    private function decodeJson(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw)) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed>|null $meta
     * @param array<string,mixed>|null $new
     */
    private function buildReference(array $row, ?array $meta, ?array $new): string
    {
        $caseNumber = $meta['case_number'] ?? $new['case_number'] ?? null;
        if (is_string($caseNumber) && $caseNumber !== '') {
            return $caseNumber;
        }

        $username = $meta['username'] ?? $new['username'] ?? null;
        if (is_string($username) && $username !== '') {
            return $username;
        }

        $name = $meta['name'] ?? $new['name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $type = (string) ($row['entity_type'] ?? '');
        $id = $row['entity_id'] ?? null;
        if ($type !== '' && $id !== null) {
            return $type . ' #' . $id;
        }
        if ($id !== null) {
            return '#' . $id;
        }

        return '—';
    }
}
