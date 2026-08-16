<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\CaseTypeRepository;
use App\Repositories\OfficerRepository;
use App\Repositories\SourceRepository;
use App\Repositories\StatusRepository;
use App\Services\DeadlineClassifier;
use App\Services\FilterNormalizer;
use App\Services\MonitoringService;
use Throwable;

final class MonitoringController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly MonitoringService $monitoring = new MonitoringService(),
        private readonly OfficerRepository $officers = new OfficerRepository(),
        private readonly CaseTypeRepository $types = new CaseTypeRepository(),
        private readonly StatusRepository $statuses = new StatusRepository(),
        private readonly SourceRepository $sources = new SourceRepository(),
    ) {
    }

    public function deadlines(): void
    {
        $user = Session::user();
        $filters = FilterNormalizer::normalizeCaseFilters();
        $filters = FilterNormalizer::applyRoleScope($filters, $user);

        $page = (int) Request::input('page', 1);

        try {
            $result = $this->monitoring->listDeadlines($filters, $page, self::PER_PAGE);
            $loadError = null;
        } catch (Throwable $e) {
            $result = [
                'paginator' => null,
                'counts' => [
                    'all' => 0, 'overdue' => 0, 'today' => 0, 'h3' => 0, 'h5' => 0, 'normal' => 0, 'selesai' => 0,
                ],
                'items' => [],
            ];
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat data monitoring.';
        }

        $this->render('monitoring/deadlines', [
            'pageTitle' => 'Monitoring Permohonan',
            'paginator' => $result['paginator'],
            'counts' => $result['counts'],
            'filters' => $filters,
            'loadError' => $loadError,
            'basePath' => '/monitoring/deadlines',
            'quickFilters' => DeadlineClassifier::quickFilters(),
            'isAdmin' => ($user['role'] ?? '') === 'admin',
            'officers' => $this->officers->activeOptions(),
            'statuses' => $this->statuses->activeOptions(),
            'types' => $this->types->activeOptions(),
            'sources' => $this->sources->activeOptions(),
            'todayLabel' => format_date_id(today_jakarta()),
        ]);
    }

    public function officers(): void
    {
        $user = Session::user();
        $filters = FilterNormalizer::normalizeCaseFilters();
        $filters = FilterNormalizer::applyRoleScope($filters, $user);

        $selectedOfficerId = $filters['officer_id'] ?? null;
        $page = (int) Request::input('page', 1);

        try {
            $workload = $this->monitoring->workloadSummary($filters);
            $detail = null;
            $paginator = null;
            if ($selectedOfficerId) {
                $caseFilters = $filters;
                $caseFilters['officer_id'] = (int) $selectedOfficerId;
                $result = $this->monitoring->listDeadlines($caseFilters, $page, self::PER_PAGE);
                $paginator = $result['paginator'];
                foreach ($workload as $w) {
                    if ((int) $w['officer_id'] === (int) $selectedOfficerId) {
                        $detail = $w;
                        break;
                    }
                }
            }
            $loadError = null;
        } catch (Throwable $e) {
            $workload = [];
            $paginator = null;
            $detail = null;
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat monitoring petugas.';
        }

        $this->render('monitoring/officers', [
            'pageTitle' => 'Monitoring Petugas',
            'workload' => $workload,
            'paginator' => $paginator,
            'detail' => $detail,
            'filters' => $filters,
            'selectedOfficerId' => $selectedOfficerId,
            'loadError' => $loadError,
            'basePath' => '/monitoring/officers',
            'isAdmin' => ($user['role'] ?? '') === 'admin',
            'officers' => $this->officers->activeOptions(),
            'statuses' => $this->statuses->activeOptions(),
            'types' => $this->types->activeOptions(),
            'sources' => $this->sources->activeOptions(),
        ]);
    }

    /** @return array<string,mixed> */
    private function readFilters(): array
    {
        return FilterNormalizer::normalizeCaseFilters();
    }
}
