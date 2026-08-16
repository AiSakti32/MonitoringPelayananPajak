<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\OfficerRepository;
use App\Services\AuditLogger;
use Throwable;

final class OfficerController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(private readonly OfficerRepository $repo = new OfficerRepository())
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
                : 'Gagal memuat data petugas. Periksa koneksi database.';
        }

        $this->render('master/officers/index', [
            'pageTitle' => 'Master Petugas',
            'paginator' => $paginator,
            'filters' => ['q' => $search, 'status' => $status],
            'loadError' => $loadError,
            'basePath' => '/master/officers',
        ]);
    }

    public function create(): void
    {
        $this->render('master/officers/form', [
            'pageTitle' => 'Tambah Petugas',
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
            $this->redirectWithErrors('/master/officers/create', $errors, $data);
        }

        try {
            $id = $this->repo->create($data);
            AuditLogger::log('officer_created', Session::userId(), 'officer', $id, [
                'module' => 'MASTER',
                'description' => 'Petugas ditambahkan',
                'name' => $data['name'],
                'new_values' => [
                    'name' => $data['name'],
                    'employee_code' => $data['employee_code'] ?: null,
                    'is_active' => (int) $data['is_active'] === 1 ? 'aktif' : 'nonaktif',
                ],
            ]);
            $this->redirectWithSuccess('/master/officers', 'Petugas berhasil ditambahkan.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal menyimpan petugas.');
            $this->redirectWithErrors('/master/officers/create', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function edit(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Petugas tidak ditemukan.');
        }

        $this->render('master/officers/form', [
            'pageTitle' => 'Edit Petugas',
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
            abort(404, 'Petugas tidak ditemukan.');
        }

        $data = $this->payload();
        $errors = $this->validate($data, (int) $id);
        if ($errors !== []) {
            $this->redirectWithErrors('/master/officers/' . $id . '/edit', $errors, $data);
        }

        try {
            $this->repo->update((int) $id, $data);
            AuditLogger::log('officer_updated', Session::userId(), 'officer', (int) $id, [
                'module' => 'MASTER',
                'description' => 'Petugas diperbarui',
                'name' => $data['name'],
                'old_values' => [
                    'name' => $item['name'],
                    'employee_code' => $item['employee_code'] ?: null,
                    'is_active' => (int) $item['is_active'] === 1 ? 'aktif' : 'nonaktif',
                ],
                'new_values' => [
                    'name' => $data['name'],
                    'employee_code' => $data['employee_code'] ?: null,
                    'is_active' => (int) $data['is_active'] === 1 ? 'aktif' : 'nonaktif',
                ],
            ]);
            $this->redirectWithSuccess('/master/officers', 'Petugas berhasil diperbarui.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal memperbarui petugas.');
            $this->redirectWithErrors('/master/officers/' . $id . '/edit', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function toggle(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Petugas tidak ditemukan.');
        }

        $activate = !(bool) (int) $item['is_active'];
        $this->repo->setActive((int) $id, $activate);
        AuditLogger::log(
            $activate ? 'officer_activated' : 'officer_deactivated',
            Session::userId(),
            'officer',
            (int) $id,
            [
                'module' => 'MASTER',
                'description' => $activate ? 'Petugas diaktifkan' : 'Petugas dinonaktifkan',
                'name' => $item['name'],
                'old_values' => ['is_active' => (int) $item['is_active'] === 1 ? 'aktif' : 'nonaktif'],
                'new_values' => ['is_active' => $activate ? 'aktif' : 'nonaktif'],
                'usage' => $this->repo->usageCount((int) $id),
            ]
        );

        $msg = $activate
            ? 'Petugas diaktifkan kembali.'
            : 'Petugas dinonaktifkan (soft deactivate). Data historis tetap aman.';
        $this->redirectWithSuccess('/master/officers', $msg);
    }

    private function payload(): array
    {
        return [
            'name' => trim((string) Request::input('name', '')),
            'employee_code' => trim((string) Request::input('employee_code', '')),
            'is_active' => Request::input('is_active') === '1' ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'name' => 'required|max:150',
            'employee_code' => 'max:50',
        ]);
        $errors = $validator->errors();

        if ($data['name'] !== '' && $this->repo->nameExists($data['name'], $excludeId)) {
            $errors['name'][] = 'Nama petugas sudah digunakan.';
        }

        return $errors;
    }
}
