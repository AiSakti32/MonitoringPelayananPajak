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
use App\Services\AlertService;
use App\Services\DeadlineClassifier;
use App\Services\FilterNormalizer;
use Throwable;

final class AlertController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly AlertService $alerts = new AlertService(),
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

        $page = (int) Request::input('page', 1);

        try {
            $result = $this->alerts->list($filters, $page, self::PER_PAGE);
            $loadError = null;
        } catch (Throwable $e) {
            $result = [
                'paginator' => null,
                'counts' => ['all' => 0, 'overdue' => 0, 'today' => 0, 'h3' => 0, 'h5' => 0],
                'items' => [],
            ];
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat Alert Center.';
        }

        $quick = [
            ['key' => 'all', 'label' => 'Semua Alert', 'tone' => 'neutral'],
            ['key' => 'overdue', 'label' => 'Terlambat', 'tone' => 'overdue'],
            ['key' => 'today', 'label' => 'Hari Ini', 'tone' => 'critical'],
            ['key' => 'h3', 'label' => 'H-3', 'tone' => 'critical'],
            ['key' => 'h5', 'label' => 'H-5', 'tone' => 'warn'],
        ];

        $this->render('alerts/index', [
            'pageTitle' => 'Alert / Perlu Tindakan',
            'paginator' => $result['paginator'],
            'counts' => $result['counts'],
            'filters' => $filters,
            'quickFilters' => $quick,
            'loadError' => $loadError,
            'basePath' => '/alerts',
            'isAdmin' => ($user['role'] ?? '') === 'admin',
            'officers' => $this->officers->activeOptions(),
            'statuses' => $this->statuses->activeOptions(),
            'types' => $this->types->activeOptions(),
            'sources' => $this->sources->activeOptions(),
            'todayLabel' => format_date_id(today_jakarta()),
            'emptyHint' => $this->emptyHint((string) ($filters['deadline'] ?? 'all')),
        ]);
    }

    private function emptyHint(string $deadline): string
    {
        $key = DeadlineClassifier::normalizeKey($deadline);
        return match ($key) {
            'overdue' => 'Tidak ada case terlambat saat ini.',
            'today' => 'Tidak ada case jatuh tempo hari ini.',
            'h3' => 'Tidak ada case H-3 saat ini.',
            'h5' => 'Tidak ada case H-5 saat ini.',
            default => 'Tidak ada case yang memerlukan tindakan saat ini.',
        };
    }
}
