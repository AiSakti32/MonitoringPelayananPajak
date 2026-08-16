<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\SourceRepository;
use App\Services\AuditLogger;
use Throwable;

final class SourceController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(private readonly SourceRepository $repo = new SourceRepository())
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
                : 'Gagal memuat data sumber kasus.';
        }

        $this->render('master/sources/index', [
            'pageTitle' => 'Master Sumber Kasus',
            'paginator' => $paginator,
            'filters' => ['q' => $search, 'status' => $status],
            'loadError' => $loadError,
            'basePath' => '/master/sources',
        ]);
    }

    public function create(): void
    {
        $this->render('master/sources/form', [
            'pageTitle' => 'Tambah Sumber Kasus',
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
            $this->redirectWithErrors('/master/sources/create', $errors, $data);
        }

        try {
            $id = $this->repo->create($data);
            AuditLogger::log('source_created', Session::userId(), 'case_source', $id, [
                'module' => 'MASTER',
                'description' => 'Sumber kasus ditambahkan',
                'name' => $data['name'],
                'new_values' => ['name' => $data['name']],
            ]);
            $this->redirectWithSuccess('/master/sources', 'Sumber kasus berhasil ditambahkan.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal menyimpan sumber.');
            $this->redirectWithErrors('/master/sources/create', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function edit(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Sumber tidak ditemukan.');
        }

        $this->render('master/sources/form', [
            'pageTitle' => 'Edit Sumber Kasus',
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
            abort(404, 'Sumber tidak ditemukan.');
        }

        $data = $this->payload();
        $errors = $this->validate($data, (int) $id);
        if ($errors !== []) {
            $this->redirectWithErrors('/master/sources/' . $id . '/edit', $errors, $data);
        }

        try {
            $this->repo->update((int) $id, $data);
            AuditLogger::log('source_updated', Session::userId(), 'case_source', (int) $id, [
                'module' => 'MASTER',
                'description' => 'Sumber kasus diperbarui',
                'name' => $data['name'],
                'old_values' => ['name' => $item['name']],
                'new_values' => ['name' => $data['name']],
            ]);
            $this->redirectWithSuccess('/master/sources', 'Sumber kasus berhasil diperbarui.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal memperbarui sumber.');
            $this->redirectWithErrors('/master/sources/' . $id . '/edit', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function toggle(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Sumber tidak ditemukan.');
        }

        $activate = !(bool) (int) $item['is_active'];
        $this->repo->setActive((int) $id, $activate);
        AuditLogger::log(
            $activate ? 'source_activated' : 'source_deactivated',
            Session::userId(),
            'case_source',
            (int) $id,
            [
                'module' => 'MASTER',
                'description' => $activate ? 'Sumber diaktifkan' : 'Sumber dinonaktifkan',
                'name' => $item['name'],
                'old_values' => ['is_active' => (int) $item['is_active'] === 1 ? 'aktif' : 'nonaktif'],
                'new_values' => ['is_active' => $activate ? 'aktif' : 'nonaktif'],
            ]
        );

        $this->redirectWithSuccess(
            '/master/sources',
            $activate ? 'Sumber diaktifkan.' : 'Sumber dinonaktifkan. Relasi case lama tetap aman.'
        );
    }

    private function payload(): array
    {
        return [
            'name' => trim((string) Request::input('name', '')),
            'is_active' => Request::input('is_active') === '1' ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $validator = new Validator();
        $validator->validate($data, ['name' => 'required|max:100']);
        $errors = $validator->errors();

        if ($data['name'] !== '' && $this->repo->nameExists($data['name'], $excludeId)) {
            $errors['name'][] = 'Nama sumber sudah digunakan.';
        }

        return $errors;
    }
}
