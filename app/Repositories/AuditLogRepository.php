<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Paginator;
use PDO;

final class AuditLogRepository
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::connection();
    }

    /**
     * @param array{
     *   q?: string,
     *   user_id?: int|null,
     *   role?: string|null,
     *   action?: string|null,
     *   module?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null
     * } $filters
     */
    public function paginate(array $filters, int $page, int $perPage): Paginator
    {
        [$where, $params] = $this->buildWhere($filters);
        $page = Paginator::normalizePage($page);
        $offset = Paginator::offset($page, $perPage);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT a.*,
                       u.full_name AS user_name,
                       u.username AS user_username,
                       u.role AS user_role
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE {$where}
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return new Paginator($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*,
                    u.full_name AS user_name,
                    u.username AS user_username,
                    u.role AS user_role
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<string> */
    public function distinctActions(): array
    {
        $rows = $this->db->query(
            'SELECT DISTINCT action FROM audit_logs ORDER BY action ASC'
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $rows));
    }

    /** @return list<string> */
    public function distinctModules(): array
    {
        $rows = $this->db->query(
            "SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL AND module <> '' ORDER BY module ASC"
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $rows));
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $parts = ['1=1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $parts[] = '(u.full_name LIKE :q1 OR u.username LIKE :q2
                OR a.description LIKE :q3
                OR a.action LIKE :q4
                OR CAST(a.entity_id AS CHAR) LIKE :q5
                OR JSON_UNQUOTE(JSON_EXTRACT(a.meta, "$.case_number")) LIKE :q6
                OR JSON_UNQUOTE(JSON_EXTRACT(a.new_values, "$.case_number")) LIKE :q7
                OR JSON_UNQUOTE(JSON_EXTRACT(a.old_values, "$.case_number")) LIKE :q8)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
            $params['q7'] = $like;
            $params['q8'] = $like;
        }

        if (!empty($filters['user_id'])) {
            $parts[] = 'a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '' && $role !== 'all') {
            $parts[] = 'u.role = :role';
            $params['role'] = $role;
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '' && $action !== 'all') {
            $parts[] = 'a.action = :action';
            $params['action'] = $action;
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '' && $module !== 'all') {
            $parts[] = 'a.module = :module';
            $params['module'] = strtoupper($module);
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $parts[] = 'a.created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $parts[] = 'a.created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        return [implode(' AND ', $parts), $params];
    }
}
