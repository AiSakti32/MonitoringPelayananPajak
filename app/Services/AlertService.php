<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Paginator;
use App\Core\Session;
use App\Repositories\CaseRepository;

/**
 * Alert Center — cases needing attention (overdue, today, H-3, H-5).
 * Uses DeadlineClassifier + CaseRepository (no duplicate deadline math).
 */
final class AlertService
{
    private static ?int $requestCountCache = null;

    public function __construct(private readonly CaseRepository $cases = new CaseRepository())
    {
    }

    /**
     * Realtime alert count for sidebar/topbar badge (scoped by current user).
     */
    public static function countForCurrentUser(): int
    {
        if (self::$requestCountCache !== null) {
            return self::$requestCountCache;
        }

        try {
            if (!Session::isLoggedIn()) {
                return self::$requestCountCache = 0;
            }
            $service = new self();
            $filters = FilterNormalizer::applyRoleScope([], Session::user());
            self::$requestCountCache = $service->count($filters);
            return self::$requestCountCache;
        } catch (\Throwable) {
            return self::$requestCountCache = 0;
        }
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function count(array $filters): int
    {
        $buckets = $this->cases->countDeadlineBuckets($filters);
        $total = 0;
        foreach (DeadlineClassifier::ALERT_KEYS as $key) {
            $total += (int) ($buckets[$key] ?? 0);
        }
        return $total;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{paginator:Paginator,counts:array<string,int>,items:list<array>}
     */
    public function list(array $filters, int $page = 1, int $perPage = 25): array
    {
        $deadline = DeadlineClassifier::normalizeKey((string) ($filters['deadline'] ?? 'all'));

        // Default: all alert categories (exclude normal/selesai)
        if ($deadline === DeadlineClassifier::KEY_ALL || $deadline === '') {
            $filters['deadline'] = 'alert';
        } elseif (!in_array($deadline, DeadlineClassifier::ALERT_KEYS, true)) {
            // If user picks selesai/normal on alert page, still allow but empty-ish
            $filters['deadline'] = $deadline;
        } else {
            $filters['deadline'] = $deadline;
        }

        $paginator = $this->cases->paginate($filters, $page, $perPage, 'urgency');
        $counts = $this->cases->countDeadlineBuckets(
            array_merge($filters, ['deadline' => DeadlineClassifier::KEY_ALL])
        );

        $alertCounts = [
            'all' => 0,
            'overdue' => (int) ($counts['overdue'] ?? 0),
            'today' => (int) ($counts['today'] ?? 0),
            'h3' => (int) ($counts['h3'] ?? 0),
            'h5' => (int) ($counts['h5'] ?? 0),
        ];
        $alertCounts['all'] = $alertCounts['overdue'] + $alertCounts['today'] + $alertCounts['h3'] + $alertCounts['h5'];

        $items = [];
        foreach ($paginator->items as $row) {
            $dl = DeadlineClassifier::classify(
                (string) $row['due_date'],
                (bool) (int) $row['status_is_completed']
            );
            $row['deadline'] = $dl;
            $row['days_remaining'] = DeadlineClassifier::daysRemaining(
                (string) $row['due_date'],
                (bool) (int) $row['status_is_completed']
            );
            $row['priority_label'] = DeadlineClassifier::priorityLabel($dl['key']);
            $items[] = $row;
        }

        return [
            'paginator' => new Paginator($items, $paginator->total, $paginator->page, $paginator->perPage),
            'counts' => $alertCounts,
            'items' => $items,
        ];
    }
}
