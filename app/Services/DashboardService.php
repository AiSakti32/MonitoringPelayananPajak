<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CaseRepository;
use PDO;

final class DashboardService
{
    private PDO $db;

    public function __construct(private readonly CaseRepository $cases = new CaseRepository())
    {
        $this->db = $this->cases->pdo();
    }

    /**
     * Full dashboard payload from real DB aggregates.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function build(array $filters): array
    {
        // Deadline KPIs ignore status_id filter for deadline buckets (use base filters without deadline)
        $base = $filters;
        unset($base['deadline']);

        $kpi = $this->kpi($base);
        $statusChart = $this->casesByStatus($base);
        $typeChart = $this->casesByType($base, 10);
        $priorityGroups = $this->priorityGroups($base);
        $workloadAll = $this->workloadByOfficer($base);
        $workloadLimit = 10;
        $workloadTotal = count($workloadAll);
        $workload = array_slice($workloadAll, 0, $workloadLimit);
        $approaching = $this->urgentCases($base, ['today', 'h3', 'h5'], 10);
        $overdue = $this->urgentCases($base, ['overdue'], 10);
        $recent = $this->recentActivity($base, 12);
        $topTypes = $this->casesByType($base, 5);

        return [
            'kpi' => $kpi,
            'charts' => [
                'status' => $statusChart,
                'types' => $typeChart,
                'priority' => $priorityGroups,
                'workload' => $workload,
            ],
            'tables' => [
                'approaching' => $approaching,
                'overdue' => $overdue,
                'recent' => $recent,
                'top_types' => $topTypes,
                'workload' => $workload,
            ],
            'meta' => [
                'workload_shown' => count($workload),
                'workload_total' => $workloadTotal,
                'workload_limit' => $workloadLimit,
                'types_limit' => 10,
                'top_types_limit' => 5,
            ],
            'empty' => ($kpi['active'] + $kpi['selesai']) === 0,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{
     *   active:int,dibuat:int,diproses:int,selesai:int,
     *   h5:int,h3:int,today:int,overdue:int
     * }
     */
    public function kpi(array $filters): array
    {
        [$where, $params] = $this->cases->buildFilterClauses($filters, false);
        $today = today_jakarta();

        $sql = "SELECT
            SUM(CASE WHEN cs.is_completed = 0 THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN cs.slug = 'dibuat' THEN 1 ELSE 0 END) AS dibuat,
            SUM(CASE WHEN cs.slug = 'diproses' THEN 1 ELSE 0 END) AS diproses,
            SUM(CASE WHEN cs.is_completed = 1 THEN 1 ELSE 0 END) AS selesai,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date < :t1 THEN 1 ELSE 0 END) AS overdue,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date = :t2 THEN 1 ELSE 0 END) AS today_count,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t3, INTERVAL 1 DAY) AND DATE_ADD(:t4, INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS h3,
            SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t5, INTERVAL 4 DAY) AND DATE_ADD(:t6, INTERVAL 5 DAY) THEN 1 ELSE 0 END) AS h5
            FROM cases c
            INNER JOIN case_statuses cs ON cs.id = c.status_id
            WHERE {$where}";

        $params['t1'] = $today;
        $params['t2'] = $today;
        $params['t3'] = $today;
        $params['t4'] = $today;
        $params['t5'] = $today;
        $params['t6'] = $today;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'active' => (int) ($row['active_count'] ?? 0),
            'dibuat' => (int) ($row['dibuat'] ?? 0),
            'diproses' => (int) ($row['diproses'] ?? 0),
            'selesai' => (int) ($row['selesai'] ?? 0),
            'h5' => (int) ($row['h5'] ?? 0),
            'h3' => (int) ($row['h3'] ?? 0),
            'today' => (int) ($row['today_count'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array{label:string,value:int,status_id:?int,slug:?string}>
     */
    public function casesByStatus(array $filters): array
    {
        [$where, $params] = $this->cases->buildFilterClauses($filters, false);

        $sql = "SELECT cs.id AS status_id, cs.name AS label, cs.slug, COUNT(*) AS value
                FROM cases c
                INNER JOIN case_statuses cs ON cs.id = c.status_id
                WHERE {$where}
                GROUP BY cs.id, cs.name, cs.slug, cs.sort_order
                ORDER BY cs.sort_order ASC, cs.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static fn (array $r): array => [
            'label' => (string) $r['label'],
            'value' => (int) $r['value'],
            'status_id' => (int) $r['status_id'],
            'slug' => (string) $r['slug'],
        ], $rows);
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array{label:string,value:int,case_type_id:?int}>
     */
    public function casesByType(array $filters, int $limit = 10): array
    {
        [$where, $params] = $this->cases->buildFilterClauses($filters, false);
        $limit = max(1, min(50, $limit));

        $sql = "SELECT ct.id AS case_type_id, ct.name AS label, COUNT(*) AS value
                FROM cases c
                INNER JOIN case_types ct ON ct.id = c.case_type_id
                INNER JOIN case_statuses cs ON cs.id = c.status_id
                WHERE {$where}
                GROUP BY ct.id, ct.name
                ORDER BY value DESC, ct.name ASC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $r): array => [
            'label' => (string) $r['label'],
            'value' => (int) $r['value'],
            'case_type_id' => (int) $r['case_type_id'],
        ], $stmt->fetchAll());
    }

    /**
     * 5 dashboard priority groups via dashboard_group.
     *
     * @param array<string,mixed> $filters
     * @return list<array{label:string,value:int}>
     */
    public function priorityGroups(array $filters): array
    {
        [$where, $params] = $this->cases->buildFilterClauses($filters, false);

        $sql = "SELECT
                    COALESCE(NULLIF(ct.dashboard_group, ''), ct.name) AS label,
                    COUNT(*) AS value
                FROM cases c
                INNER JOIN case_types ct ON ct.id = c.case_type_id
                INNER JOIN case_statuses cs ON cs.id = c.status_id
                WHERE {$where}
                  AND ct.is_dashboard_priority = 1
                GROUP BY COALESCE(NULLIF(ct.dashboard_group, ''), ct.name)
                ORDER BY value DESC, label ASC
                LIMIT 5";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $r): array => [
            'label' => (string) $r['label'],
            'value' => (int) $r['value'],
        ], $stmt->fetchAll());
    }

    /**
     * Active case workload per officer.
     *
     * @param array<string,mixed> $filters
     * @return list<array{officer_id:int,label:string,value:int,overdue:int,h3:int,h5:int}>
     */
    public function workloadByOfficer(array $filters): array
    {
        [$where, $params] = $this->cases->buildFilterClauses($filters, false);
        $today = today_jakarta();

        // Active only in value; still show officers with 0 if they appear in filtered set
        $sql = "SELECT
                    o.id AS officer_id,
                    o.name AS label,
                    SUM(CASE WHEN cs.is_completed = 0 THEN 1 ELSE 0 END) AS value,
                    SUM(CASE WHEN cs.is_completed = 0 AND c.due_date < :t1 THEN 1 ELSE 0 END) AS overdue,
                    SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t2, INTERVAL 1 DAY) AND DATE_ADD(:t3, INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS h3,
                    SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t4, INTERVAL 4 DAY) AND DATE_ADD(:t5, INTERVAL 5 DAY) THEN 1 ELSE 0 END) AS h5
                FROM cases c
                INNER JOIN officers o ON o.id = c.officer_id
                INNER JOIN case_statuses cs ON cs.id = c.status_id
                WHERE {$where}
                GROUP BY o.id, o.name
                HAVING value > 0 OR overdue > 0
                ORDER BY value DESC, o.name ASC";

        $params['t1'] = $today;
        $params['t2'] = $today;
        $params['t3'] = $today;
        $params['t4'] = $today;
        $params['t5'] = $today;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $r): array => [
            'officer_id' => (int) $r['officer_id'],
            'label' => (string) $r['label'],
            'value' => (int) $r['value'],
            'overdue' => (int) $r['overdue'],
            'h3' => (int) $r['h3'],
            'h5' => (int) $r['h5'],
        ], $stmt->fetchAll());
    }

    /**
     * @param array<string,mixed> $filters
     * @param list<string> $deadlineKeys
     * @return list<array>
     */
    public function urgentCases(array $filters, array $deadlineKeys, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $rows = [];

        // Fetch per bucket to preserve mutual exclusivity then merge in urgency order
        $order = ['overdue', 'today', 'h3', 'h5'];
        foreach ($order as $key) {
            if (!in_array($key, $deadlineKeys, true)) {
                continue;
            }
            $f = $filters;
            $f['deadline'] = $key;
            $page = $this->cases->paginate($f, 1, $limit, 'urgency');
            foreach ($page->items as $row) {
                $row['deadline'] = DeadlineClassifier::classify(
                    (string) $row['due_date'],
                    (bool) (int) $row['status_is_completed']
                );
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    return array_slice($rows, 0, $limit);
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array>
     */
    public function recentActivity(array $filters, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        [$where, $params] = $this->cases->buildFilterClauses($filters, false);

        $sql = "SELECT h.id, h.event_type, h.created_at, h.note, h.changed_fields,
                       h.old_status_id, h.new_status_id,
                       c.id AS case_id, c.case_number,
                       u.full_name AS actor_name,
                       u.full_name AS changed_by_name,
                       os.name AS old_status_name,
                       ns.name AS new_status_name
                FROM case_histories h
                INNER JOIN cases c ON c.id = h.case_id
                INNER JOIN case_statuses cs ON cs.id = c.status_id
                LEFT JOIN users u ON u.id = h.changed_by
                LEFT JOIN case_statuses os ON os.id = h.old_status_id
                LEFT JOIN case_statuses ns ON ns.id = h.new_status_id
                WHERE {$where}
                ORDER BY h.created_at DESC, h.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return (new CaseHistoryPresenter())->presentMany($rows);
    }
}
