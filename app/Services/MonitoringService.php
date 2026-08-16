<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Paginator;
use App\Repositories\CaseRepository;

final class MonitoringService
{
    public function __construct(private readonly CaseRepository $cases = new CaseRepository())
    {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{paginator:Paginator,counts:array<string,int>,items:list<array>}
     */
    public function listDeadlines(array $filters, int $page = 1, int $perPage = 25): array
    {
        $paginator = $this->cases->paginate($filters, $page, $perPage, 'urgency');
        $counts = $this->cases->countDeadlineBuckets($filters);

        $items = [];
        foreach ($paginator->items as $row) {
            $deadline = DeadlineClassifier::classify(
                (string) $row['due_date'],
                (bool) (int) $row['status_is_completed']
            );
            $row['deadline'] = $deadline;
            $row['days_remaining'] = DeadlineClassifier::daysRemaining(
                (string) $row['due_date'],
                (bool) (int) $row['status_is_completed']
            );
            $items[] = $row;
        }

        return [
            'paginator' => new Paginator($items, $paginator->total, $paginator->page, $paginator->perPage),
            'counts' => $counts,
            'items' => $items,
        ];
    }

    /**
     * Workload per petugas — active cases + deadline buckets + selesai bulan ini.
     * Bucket logic mirrors DeadlineClassifier (Asia/Jakarta date-only).
     *
     * @param array<string,mixed> $filters
     * @return list<array{
     *   officer_id:int,label:string,aktif:int,normal:int,h5:int,h3:int,today:int,overdue:int,selesai:int,selesai_bulan_ini:int
     * }>
     */
    public function workloadSummary(array $filters): array
    {
        $base = $filters;
        unset($base['deadline'], $base['officer_id']);

        [$where, $params] = $this->cases->buildFilterClauses($base, false);
        $today = today_jakarta();
        $monthStart = substr($today, 0, 8) . '01';
        $db = $this->cases->pdo();

        $select = "SELECT
                        o.id AS officer_id,
                        o.name AS label,
                        SUM(CASE WHEN cs.is_completed = 0 THEN 1 ELSE 0 END) AS aktif,
                        SUM(CASE WHEN cs.is_completed = 0 AND c.due_date > DATE_ADD(:t_normal, INTERVAL 5 DAY) THEN 1 ELSE 0 END) AS normal,
                        SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t_h5a, INTERVAL 4 DAY) AND DATE_ADD(:t_h5b, INTERVAL 5 DAY) THEN 1 ELSE 0 END) AS h5,
                        SUM(CASE WHEN cs.is_completed = 0 AND c.due_date BETWEEN DATE_ADD(:t_h3a, INTERVAL 1 DAY) AND DATE_ADD(:t_h3b, INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS h3,
                        SUM(CASE WHEN cs.is_completed = 0 AND c.due_date = :t_today THEN 1 ELSE 0 END) AS today_count,
                        SUM(CASE WHEN cs.is_completed = 0 AND c.due_date < :t_overdue THEN 1 ELSE 0 END) AS overdue,
                        SUM(CASE WHEN cs.is_completed = 1 THEN 1 ELSE 0 END) AS selesai,
                        SUM(CASE WHEN cs.is_completed = 1 AND DATE(c.updated_at) >= :month_start THEN 1 ELSE 0 END) AS selesai_bulan_ini";

        $bindDates = [
            't_normal' => $today,
            't_h5a' => $today,
            't_h5b' => $today,
            't_h3a' => $today,
            't_h3b' => $today,
            't_today' => $today,
            't_overdue' => $today,
            'month_start' => $monthStart,
        ];

        if ($where !== '1=1' || $params !== []) {
            $sql = "{$select}
                    FROM cases c
                    INNER JOIN officers o ON o.id = c.officer_id
                    INNER JOIN case_statuses cs ON cs.id = c.status_id
                    WHERE {$where}
                    GROUP BY o.id, o.name
                    ORDER BY aktif DESC, o.name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge($params, $bindDates));
        } else {
            $sql = "{$select}
                    FROM officers o
                    LEFT JOIN cases c ON c.officer_id = o.id
                    LEFT JOIN case_statuses cs ON cs.id = c.status_id
                    WHERE o.is_active = 1
                    GROUP BY o.id, o.name
                    ORDER BY aktif DESC, o.name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($bindDates);
        }

        return array_map(static fn (array $r): array => [
            'officer_id' => (int) $r['officer_id'],
            'label' => (string) $r['label'],
            'aktif' => (int) $r['aktif'],
            'normal' => (int) $r['normal'],
            'h5' => (int) $r['h5'],
            'h3' => (int) $r['h3'],
            'today' => (int) $r['today_count'],
            'overdue' => (int) $r['overdue'],
            'selesai' => (int) $r['selesai'],
            'selesai_bulan_ini' => (int) $r['selesai_bulan_ini'],
        ], $stmt->fetchAll());
    }
}
