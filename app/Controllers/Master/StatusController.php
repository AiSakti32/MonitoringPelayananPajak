<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\StatusRepository;
use App\Services\AuditLogger;
use Throwable;

final class StatusController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(private readonly StatusRepository $repo = new StatusRepository())
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
                : 'Gagal memuat data status kasus.';
        }

        $this->render('master/statuses/index', [
            'pageTitle' => 'Master Status Kasus',
            'paginator' => $paginator,
            'filters' => ['q' => $search, 'status' => $status],
            'loadError' => $loadError,
            'basePath' => '/master/statuses',
        ]);
    }

    public function create(): void
    {
        $this->render('master/statuses/form', [
            'pageTitle' => 'Tambah Status Kasus',
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
            $this->redirectWithErrors('/master/statuses/create', $errors, $data);
        }

        try {
            $id = $this->repo->create($data);
            AuditLogger::log('status_created', Session::userId(), 'case_status', $id, [
                'module' => 'MASTER',
                'description' => 'Status kasus ditambahkan',
                'name' => $data['name'],
                'new_values' => ['name' => $data['name']],
            ]);
            $this->redirectWithSuccess('/master/statuses', 'Status kasus berhasil ditambahkan.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal menyimpan status.');
            $this->redirectWithErrors('/master/statuses/create', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function edit(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Status tidak ditemukan.');
        }

        $this->render('master/statuses/form', [
            'pageTitle' => 'Edit Status Kasus',
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
            abort(404, 'Status tidak ditemukan.');
        }

        $data = $this->payload();
        $errors = $this->validate($data, (int) $id);
        if ($errors !== []) {
            $this->redirectWithErrors('/master/statuses/' . $id . '/edit', $errors, $data);
        }

        try {
            $this->repo->update((int) $id, $data);
            AuditLogger::log('status_updated', Session::userId(), 'case_status', (int) $id, [
                'module' => 'MASTER',
                'description' => 'Status kasus diperbarui',
                'name' => $data['name'],
                'old_values' => ['name' => $item['name']],
                'new_values' => ['name' => $data['name']],
            ]);
            $this->redirectWithSuccess('/master/statuses', 'Status kasus berhasil diperbarui.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal memperbarui status.');
            $this->redirectWithErrors('/master/statuses/' . $id . '/edit', ['form' => [$e->getMessage()]], $data);
        }
    }

    public function toggle(string $id): void
    {
        $item = $this->repo->findById((int) $id);
        if ($item === null) {
            abort(404, 'Status tidak ditemukan.');
        }

        $activate = !(bool) (int) $item['is_active'];
        $this->repo->setActive((int) $id, $activate);
        AuditLogger::log(
            $activate ? 'status_activated' : 'status_deactivated',
            Session::userId(),
            'case_status',
            (int) $id,
            [
                'module' => 'MASTER',
                'description' => $activate ? 'Status diaktifkan' : 'Status dinonaktifkan',
                'name' => $item['name'],
                'old_values' => ['is_active' => (int) $item['is_active'] === 1 ? 'aktif' : 'nonaktif'],
                'new_values' => ['is_active' => $activate ? 'aktif' : 'nonaktif'],
            ]
        );

        $this->redirectWithSuccess(
            '/master/statuses',
            $activate ? 'Status diaktifkan.' : 'Status dinonaktifkan. Case lama tidak berubah.'
        );
    }

    private function payload(): array
    {
        $name = trim((string) Request::input('name', ''));
        $slug = trim((string) Request::input('slug', ''));
        if ($slug === '' && $name !== '') {
            $slug = slugify($name);
        } else {
            $slug = slugify($slug);
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'is_completed' => Request::input('is_completed') === '1' ? 1 : 0,
            'sort_order' => (int) Request::input('sort_order', 0),
            'is_active' => Request::input('is_active') === '1' ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'name' => 'required|max:100',
            'slug' => 'required|max:100',
        ]);
        $errors = $validator->errors();

        if ($data['name'] !== '' && $this->repo->nameExists($data['name'], $excludeId)) {
            $errors['name'][] = 'Nama status sudah digunakan.';
        }
        if ($data['slug'] !== '' && $this->repo->slugExists($data['slug'], $excludeId)) {
            $errors['slug'][] = 'Slug status sudah digunakan.';
        }

        return $errors;
    }
}
