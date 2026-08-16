<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Paginator;
use PDO;

final class CaseRepository
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::connection();
    }

    public function pdo(): PDO
    {
        return $this->db;
    }

    public function findByNumber(string $caseNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    ct.name AS case_type_name,
                    cs.name AS status_name,
                    cs.slug AS status_slug,
                    cs.is_completed AS status_is_completed,
                    src.name AS source_name,
                    o.name AS officer_name
             FROM cases c
             INNER JOIN case_types ct ON ct.id = c.case_type_id
             INNER JOIN case_statuses cs ON cs.id = c.status_id
             INNER JOIN case_sources src ON src.id = c.source_id
             INNER JOIN officers o ON o.id = c.officer_id
             WHERE c.case_number = :case_number
             LIMIT 1'
        );
        $stmt->execute(['case_number' => $caseNumber]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    ct.name AS case_type_name,
                    cs.name AS status_name,
                    cs.slug AS status_slug,
                    cs.is_completed AS status_is_completed,
                    src.name AS source_name,
                    o.name AS officer_name,
                    cu.full_name AS created_by_name,
                    uu.full_name AS updated_by_name
             FROM cases c
             INNER JOIN case_types ct ON ct.id = c.case_type_id
             INNER JOIN case_statuses cs ON cs.id = c.status_id
             INNER JOIN case_sources src ON src.id = c.source_id
             INNER JOIN officers o ON o.id = c.officer_id
             LEFT JOIN users cu ON cu.id = c.created_by
             LEFT JOIN users uu ON uu.id = c.updated_by
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM cases')->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cases (
                case_number, npwp, taxpayer_name, case_type_id, status_id, source_id,
                created_date, due_date, officer_id, last_note, created_by, updated_by, created_at, updated_at
             ) VALUES (
                :case_number, :npwp, :taxpayer_name, :case_type_id, :status_id, :source_id,
                :created_date, :due_date, :officer_id, :last_note, :created_by, :updated_by, :created_at, :updated_at
             )'
        );
        $now = now_jakarta();
        $stmt->execute([
            'case_number' => $data['case_number'],
            'npwp' => $data['npwp'],
            'taxpayer_name' => $data['taxpayer_name'],
            'case_type_id' => $data['case_type_id'],
            'status_id' => $data['status_id'],
            'source_id' => $data['source_id'],
            'created_date' => $data['created_date'],
            'due_date' => $data['due_date'],
            'officer_id' => $data['officer_id'],
            'last_note' => $data['last_note'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cases SET
                npwp = :npwp,
                taxpayer_name = :taxpayer_name,
                case_type_id = :case_type_id,
                status_id = :status_id,
                source_id = :source_id,
                created_date = :created_date,
                due_date = :due_date,
                officer_id = :officer_id,
                last_note = :last_note,
                updated_by = :updated_by,
                updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'npwp' => $data['npwp'],
            'taxpayer_name' => $data['taxpayer_name'],
            'case_type_id' => $data['case_type_id'],
            'status_id' => $data['status_id'],
            'source_id' => $data['source_id'],
            'created_date' => $data['created_date'],
            'due_date' => $data['due_date'],
            'officer_id' => $data['officer_id'],
            'last_note' => $data['last_note'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
            'updated_at' => now_jakarta(),
        ]);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    public function buildFilterClauses(array $filters, bool $applyDeadline = true): array
    {
        $where = ['1=1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.case_number LIKE :q OR c.npwp LIKE :q2 OR c.taxpayer_name LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $caseNumber = trim((string) ($filters['case_number'] ?? ''));
        if ($caseNumber !== '') {
            $where[] = 'c.case_number LIKE :case_number';
            $params['case_number'] = '%' . strtoupper($caseNumber) . '%';
        }

        $npwp = trim((string) ($filters['npwp'] ?? ''));
        if ($npwp !== '') {
            $where[] = 'c.npwp LIKE :npwp';
            $params['npwp'] = '%' . preg_replace('/\D+/', '', $npwp) . '%';
        }

        $taxpayer = trim((string) ($filters['taxpayer_name'] ?? ''));
        if ($taxpayer !== '') {
            $where[] = 'c.taxpayer_name LIKE :taxpayer_name';
            $params['taxpayer_name'] = '%' . $taxpayer . '%';
        }

        if (!empty($filters['officer_id'])) {
            $where[] = 'c.officer_id = :officer_id';
            $params['officer_id'] = (int) $filters['officer_id'];
        }
        if (!empty($filters['status_id'])) {
            $where[] = 'c.status_id = :status_id';
            $params['status_id'] = (int) $filters['status_id'];
        }
        if (!empty($filters['case_type_id'])) {
            $where[] = 'c.case_type_id = :case_type_id';
            $params['case_type_id'] = (int) $filters['case_type_id'];
        }
        if (!empty($filters['source_id'])) {
            $where[] = 'c.source_id = :source_id';
            $params['source_id'] = (int) $filters['source_id'];
        }

        $createdFrom = $this->normalizeFilterDate($filters['created_from'] ?? null);
        $createdTo = $this->normalizeFilterDate($filters['created_to'] ?? null);
        if ($createdFrom !== null) {
            $where[] = 'c.created_date >= :created_from';
            $params['created_from'] = $createdFrom;
        }
        if ($createdTo !== null) {
            $where[] = 'c.created_date <= :created_to';
            $params['created_to'] = $createdTo;
        }

        $dueFrom = $this->normalizeFilterDate($filters['due_from'] ?? null);
        $dueTo = $this->normalizeFilterDate($filters['due_to'] ?? null);
        if ($dueFrom !== null) {
            $where[] = 'c.due_date >= :due_from';
            $params['due_from'] = $dueFrom;
        }
        if ($dueTo !== null) {
            $where[] = 'c.due_date <= :due_to';
            $params['due_to'] = $dueTo;
        }

        if ($applyDeadline) {
            $deadline = $filters['deadline'] ?? null;
            if (is_string($deadline) && $deadline !== '' && $deadline !== 'all') {
                $this->appendDeadlineClause($where, $params, $deadline);
            }
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 25, string $sort = 'updated'): Paginator
    {
        [$sqlWhere, $params] = $this->buildFilterClauses($filters, true);

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM cases c
             INNER JOIN case_statuses cs ON cs.id = c.status_id
             WHERE {$sqlWhere}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = Paginator::normalizePage($page);
        $offset = Paginator::offset($page, $perPage);
        $orderSql = $this->orderBySql($sort);

        $stmt = $this->db->prepare(
            "SELECT c.*,
                    ct.name AS case_type_name,
                    cs.name AS status_name,
                    cs.is_completed AS status_is_completed,
                    src.name AS source_name,
                    o.name AS officer_name
             FROM cases c
             INNER JOIN case_types ct ON ct.id = c.case_type_id
             INNER JOIN case_statuses cs ON cs.id = c.status_id
             INNER JOIN case_sources src ON src.id = c.source_id
             INNER JOIN officers o ON o.id = c.officer_id
             WHERE {$sqlWhere}
             ORDER BY {$orderSql}
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return new Paginator($stmt->fetchAll(), $total, $page, $perPage);
    }

    /**
     * Lightweight search for case picker autocomplete (by nomor / NPWP / nama WP).
     *
     * @return list<array<string,mixed>>
     */
    public function searchPicker(string $q, ?int $officerId = null, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(1, min(50, $limit));
        $where = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(c.case_number LIKE :q OR c.npwp LIKE :q2 OR c.taxpayer_name LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        if ($officerId !== null && $officerId > 0) {
            $where[] = 'c.officer_id = :officer_id';
            $params['officer_id'] = $officerId;
        }

        $sqlWhere = implode(' AND ', $where);
        $stmt = $this->db->prepare(
            "SELECT c.id, c.case_number, c.npwp, c.taxpayer_name, c.status_id, c.officer_id, c.due_date,
                    cs.name AS status_name, cs.is_completed AS status_is_completed,
                    o.name AS officer_name
             FROM cases c
             INNER JOIN case_statuses cs ON cs.id = c.status_id
             INNER JOIN officers o ON o.id = c.officer_id
             WHERE {$sqlWhere}
             ORDER BY cs.is_completed ASC, c.updated_at DESC, c.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Counts per deadline bucket for current non-deadline filters (for quick filter badges).
     *
     * @param array<string,mixed> $filters
     * @return array{all:int,overdue:int,today:int,h3:int,h5:int,normal:int,selesai:int}
     */
    public function countDeadlineBuckets(array $filters): array
    {
        $baseFilters = $filters;
        unset($baseFilters['deadline']);
        [$sqlWhere, $params] = $this->buildFilterClauses($baseFilters, false);
        $today = today_jakarta();

        $sql = "SELECT
            COUNT(*) AS all_count,
            SUM(CASE WHEN cs.is_completed = 1 THEN 1 ELSE 0 END) AS selesai,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date < :t1 THEN 1 ELSE 0 END) AS overdue,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date = :t2 THEN 1 ELSE 0 END) AS today_count,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t3, INTERVAL 1 DAY) AND DATE_ADD(:t4, INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS h3,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t5, INTERVAL 4 DAY) AND DATE_ADD(:t6, INTERVAL 5 DAY) THEN 1 ELSE 0 END) AS h5,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date > DATE_ADD(:t7, INTERVAL 5 DAY) THEN 1 ELSE 0 END) AS normal_count
            FROM cases c
            INNER JOIN case_statuses cs ON cs.id = c.status_id
            WHERE {$sqlWhere}";

        $params['t1'] = $today;
        $params['t2'] = $today;
        $params['t3'] = $today;
        $params['t4'] = $today;
        $params['t5'] = $today;
        $params['t6'] = $today;
        $params['t7'] = $today;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'all' => (int) ($row['all_count'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'today' => (int) ($row['today_count'] ?? 0),
            'h3' => (int) ($row['h3'] ?? 0),
            'h5' => (int) ($row['h5'] ?? 0),
            'normal' => (int) ($row['normal_count'] ?? 0),
            'selesai' => (int) ($row['selesai'] ?? 0),
        ];
    }

    private function orderBySql(string $sort): string
    {
        if ($sort === 'urgency') {
            return \App\Services\DeadlineClassifier::sqlOrderByUrgency() . ', c.case_number ASC';
        }

        return 'c.updated_at DESC, c.id DESC';
    }

    /**
     * @param list<string> $where
     * @param array<string,mixed> $params
     */
    private function appendDeadlineClause(array &$where, array &$params, string $deadline): void
    {
        $key = \App\Services\DeadlineClassifier::normalizeKey($deadline);
        $today = today_jakarta();

        if ($key === \App\Services\DeadlineClassifier::KEY_SELESAI) {
            $where[] = 'cs.is_completed = 1';
            return;
        }

        if ($key === \App\Services\DeadlineClassifier::KEY_ACTIVE) {
            $where[] = 'cs.is_completed = 0';
            return;
        }

        // Combined alert bucket: overdue + today + h3 + h5
        if ($key === 'alert') {
            $where[] = 'cs.is_completed = 0';
            $where[] = '(
                c.due_date < :dl_today
                OR c.due_date = :dl_today_eq
                OR c.due_date BETWEEN DATE_ADD(:dl_today_h3a, INTERVAL 1 DAY) AND DATE_ADD(:dl_today_h3b, INTERVAL 3 DAY)
                OR c.due_date BETWEEN DATE_ADD(:dl_today_h5a, INTERVAL 4 DAY) AND DATE_ADD(:dl_today_h5b, INTERVAL 5 DAY)
            )';
            $params['dl_today'] = $today;
            $params['dl_today_eq'] = $today;
            $params['dl_today_h3a'] = $today;
            $params['dl_today_h3b'] = $today;
            $params['dl_today_h5a'] = $today;
            $params['dl_today_h5b'] = $today;
            return;
        }

        if ($key === \App\Services\DeadlineClassifier::KEY_ALL) {
            return;
        }

        $where[] = 'cs.is_completed = 0';
        if ($key === \App\Services\DeadlineClassifier::KEY_OVERDUE) {
            $where[] = 'c.due_date < :dl_today';
            $params['dl_today'] = $today;
        } elseif ($key === \App\Services\DeadlineClassifier::KEY_TODAY) {
            $where[] = 'c.due_date = :dl_today';
            $params['dl_today'] = $today;
        } elseif ($key === \App\Services\DeadlineClassifier::KEY_H3) {
            $where[] = 'c.due_date BETWEEN DATE_ADD(:dl_today, INTERVAL 1 DAY) AND DATE_ADD(:dl_today2, INTERVAL 3 DAY)';
            $params['dl_today'] = $today;
            $params['dl_today2'] = $today;
        } elseif ($key === \App\Services\DeadlineClassifier::KEY_H5) {
            $where[] = 'c.due_date BETWEEN DATE_ADD(:dl_today, INTERVAL 4 DAY) AND DATE_ADD(:dl_today2, INTERVAL 5 DAY)';
            $params['dl_today'] = $today;
            $params['dl_today2'] = $today;
        } elseif ($key === \App\Services\DeadlineClassifier::KEY_NORMAL) {
            $where[] = 'c.due_date > DATE_ADD(:dl_today, INTERVAL 5 DAY)';
            $params['dl_today'] = $today;
        }
    }

    private function normalizeFilterDate(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $raw = trim((string) $raw);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($dt && $dt->format('Y-m-d') === $raw) {
            return $raw;
        }
        return null;
    }
}
