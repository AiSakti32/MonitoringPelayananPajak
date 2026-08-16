<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\CaseTypeRepository;
use App\Services\AuditLogger;
use Throwable;

final class CaseTypeController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(private readonly CaseTypeRepository $repo = new CaseTypeRepository())
    {
    }

    public function index(): void
    {
        $search = trim((string) Request::input('q', ''));
        $status = (string) Request::input('status', 'all');
        $page = (int) Request::input('page', 1);

        try {
            $paginator = $this->repo->paginate($search, $status, $page, self::PER_PAGE);
            $loadError = null;
        } catch (Throwable $e) {
            $paginator = null;
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat data jenis kasus.';
        }

        $this->render('master/case_types/index', [
            'pageTitle' => 'Master Jenis Kasus',
            'paginator' => $paginator,
            'filters' => ['q' => $search, 'status' => $status],
            'loadError' => $loadError,
            'basePath' => '/master/case-types',
        ]);
    }

    public function create(): void
    {
        $this->render('master/case_types/form', [
            'pageTitle' => 'Tambah Jenis Kasus',
            'mode' => 'create',
            'item' => null,
            'errors' => get_flash('errors', []),
            'old' => get_flash('old', []),
        ]);
    }

    public function store(): void
    {
        $data = $this->payload();
        $errors = $this->validate($data);
        if ($errors !== []) {
            $this->redirectWithErrors('/master/case-types/create', $errors, $data);
        }

        try {
            $id = $this->repo->create($data);
            AuditLogger::log('case_type_created', Session::userId(), 'case_type', $id, [
                'module' => 'MASTER',
                'description' => 'Jenis kasus ditambahkan',
                'name' => $data['name'],
                'new_values' => ['name' => $data['name']],
            ]);
            $this->redirectWithSuccess('/master/case-types', 'Jenis kasus berhasil ditambahkan.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal menyimpan jenis kasus.');
            $this->redirectWithErrors('/master/case-types/create', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function edit(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Jenis kasus tidak ditemukan.');
        }

        $this->render('master/case_types/form', [
            'pageTitle' => 'Edit Jenis Kasus',
            'mode' => 'edit',
            'item' => $item,
            'errors' => get_flash('errors', []),
            'old' => get_flash('old', []),
            'usageCount' => $this->repo->usageCount((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Jenis kasus tidak ditemukan.');
        }

        $data = $this->payload();
        $errors = $this->validate($data, (int) $id);
        if ($errors !== []) {
            $this->redirectWithErrors('/master/case-types/' . $id . '/edit', $errors, $data);
        }

        try {
            $this->repo->update((int) $id, $data);
            AuditLogger::log('case_type_updated', Session::userId(), 'case_type', (int) $id, [
                'module' => 'MASTER',
                'description' => 'Jenis kasus diperbarui',
                'name' => $data['name'],
                'old_values' => ['name' => $item['name']],
                'new_values' => ['name' => $data['name']],
            ]);
            $this->redirectWithSuccess('/master/case-types', 'Jenis kasus berhasil diperbarui.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal memperbarui jenis kasus.');
            $this->redirectWithErrors('/master/case-types/' . $id . '/edit', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function toggle(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Jenis kasus tidak ditemukan.');
        }

        $activate = !(bool) (int) $item['is_active'];
        $this->repo->setActive((int) $id, $activate);
        AuditLogger::log(
            $activate ? 'case_type_activated' : 'case_type_deactivated',
            Session::userId(),
            'case_type',
            (int) $id,
            [
                'module' => 'MASTER',
                'description' => $activate ? 'Jenis kasus diaktifkan' : 'Jenis kasus dinonaktifkan',
                'name' => $item['name'],
                'old_values' => ['is_active' => (int) $item['is_active'] === 1 ? 'aktif' : 'nonaktif'],
                'new_values' => ['is_active' => $activate ? 'aktif' : 'nonaktif'],
                'usage' => $this->repo->usageCount((int) $id),
            ]
        );

        $this->redirectWithSuccess(
            '/master/case-types',
            $activate ? 'Jenis kasus diaktifkan.' : 'Jenis kasus dinonaktifkan. Relasi case lama tetap aman.'
        );
    }

    private function payload(): array
    {
        return [
            'name' => trim((string) Request::input('name', '')),
            'dashboard_group' => trim((string) Request::input('dashboard_group', '')),
            'is_dashboard_priority' => Request::input('is_dashboard_priority') === '1' ? 1 : 0,
            'is_active' => Request::input('is_active') === '1' ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'name' => 'required|max:255',
            'dashboard_group' => 'max:255',
        ]);
        $errors = $validator->errors();

        if ($data['name'] !== '' && $this->repo->nameExists($data['name'], $excludeId)) {
            $errors['name'][] = 'Nama jenis kasus sudah digunakan.';
        }

        return $errors;
    }
}
