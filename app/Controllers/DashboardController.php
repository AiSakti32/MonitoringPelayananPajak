<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Repositories\CaseTypeRepository;
use App\Repositories\OfficerRepository;
use App\Repositories\SourceRepository;
use App\Repositories\StatusRepository;
use App\Services\DashboardService;
use App\Services\FilterNormalizer;
use Throwable;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard = new DashboardService(),
        private readonly OfficerRepository $officers = new OfficerRepository(),
        private readonly CaseTypeRepository $types = new CaseTypeRepository(),
        private readonly StatusRepository $statuses = new StatusRepository(),
        private readonly SourceRepository $sources = new SourceRepository(),
    ) {
    }

    public function index(): void
    {
        $user = Session::user();
        $filters = FilterNormalizer::normalizeCaseFilters();
        $filters = FilterNormalizer::applyRoleScope($filters, $user);
        // Dashboard period filters only — drop deadline/list-only keys from KPI scope
        unset($filters['deadline'], $filters['q'], $filters['case_number'], $filters['npwp'], $filters['taxpayer_name'], $filters['due_from'], $filters['due_to']);

        try {
            $data = $this->dashboard->build($filters);
            $loadError = null;
        } catch (Throwable $e) {
            $data = [
                'kpi' => [
                    'active' => 0, 'dibuat' => 0, 'diproses' => 0, 'selesai' => 0,
                    'h5' => 0, 'h3' => 0, 'today' => 0, 'overdue' => 0,
                ],
                'charts' => [
                    'status' => [], 'types' => [], 'priority' => [], 'workload' => [],
                ],
                'tables' => [
                    'approaching' => [], 'overdue' => [], 'recent' => [], 'top_types' => [], 'workload' => [],
                ],
                'meta' => [
                    'workload_shown' => 0,
                    'workload_total' => 0,
                    'workload_limit' => 10,
                    'types_limit' => 10,
                    'top_types_limit' => 5,
                ],
                'empty' => true,
            ];
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat dashboard. Periksa koneksi database.';
        }

        $links = $this->buildLinks($filters);

        $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'user' => $user,
            'filters' => $filters,
            'kpi' => $data['kpi'],
            'charts' => $data['charts'],
            'tables' => $data['tables'],
            'meta' => $data['meta'] ?? [
                'workload_shown' => count($data['tables']['workload'] ?? []),
                'workload_total' => count($data['tables']['workload'] ?? []),
                'workload_limit' => 10,
                'types_limit' => 10,
                'top_types_limit' => 5,
            ],
            'isEmpty' => (bool) ($data['empty'] ?? false),
            'loadError' => $loadError,
            'links' => $links,
            'isAdmin' => ($user['role'] ?? '') === 'admin',
            'officers' => $this->officers->activeOptions(),
            'types' => $this->types->activeOptions(),
            'statuses' => $this->statuses->activeOptions(),
            'sources' => $this->sources->activeOptions(),
            'todayLabel' => format_date_id(today_jakarta()),
        ]);
    }

    /**
     * Clickable destinations for KPI / widgets (URL query filter aliases).
     *
     * @param array<string,mixed> $filters
     * @return array<string,string>
     */
    private function buildLinks(array $filters): array
    {
        $baseCase = array_filter([
            'officer' => $filters['officer_id'] ?? null,
            'case_type_id' => $filters['case_type_id'] ?? null,
            'source_id' => $filters['source_id'] ?? null,
            'created_from' => $filters['created_from'] ?? null,
            'created_to' => $filters['created_to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $mon = static function (string $deadline) use ($baseCase): string {
            return query_url('/monitoring/deadlines', array_merge($baseCase, ['deadline' => $deadline]), []);
        };
        $cases = static function (array $extra = []) use ($baseCase): string {
            return query_url('/cases', array_merge($baseCase, $extra), []);
        };

        return [
            'active' => $cases(['deadline' => 'active']),
            'active_mon' => query_url('/monitoring/deadlines', array_merge($baseCase, ['deadline' => 'all']), []),
            'dibuat' => $cases(['status' => 'dibuat']),
            'diproses' => $cases(['status' => 'diproses']),
            'selesai' => $mon('selesai'),
            'h5' => $mon('h5'),
            'h3' => $mon('h3'),
            'today' => $mon('today'),
            'overdue' => $mon('overdue'),
            'cases' => $cases(),
            'monitoring' => $mon('all'),
            'alerts' => query_url('/alerts', $baseCase, []),
            'officers' => url('/monitoring/officers'),
        ];
    }
}
