<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\OfficerRepository;
use App\Repositories\UserRepository;
use App\Services\AuditLogger;
use Throwable;

final class UserController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly OfficerRepository $officers = new OfficerRepository(),
    ) {
    }

    public function index(): void
    {
        $search = trim((string) Request::input('q', ''));
        $status = (string) Request::input('status', 'all');
        $role = (string) Request::input('role', 'all');
        $page = (int) Request::input('page', 1);

        try {
            $paginator = $this->users->paginate($search, $status, $role, $page, self::PER_PAGE);
            $loadError = null;
        } catch (Throwable $e) {
            $paginator = null;
            $loadError = (bool) config('app.debug', false)
                ? $e->getMessage()
                : 'Gagal memuat data user.';
        }

        $this->render('users/index', [
            'pageTitle' => 'User Management',
            'paginator' => $paginator,
            'filters' => ['q' => $search, 'status' => $status, 'role' => $role],
            'loadError' => $loadError,
            'basePath' => '/users',
        ]);
    }

    public function create(): void
    {
        $this->render('users/form', [
            'pageTitle' => 'Tambah User',
            'mode' => 'create',
            'item' => null,
            'officers' => $this->officerOptionsForForm(null),
            'errors' => get_flash('errors', []),
            'old' => get_flash('old', []),
        ]);
    }

    public function store(): void
    {
        $data = $this->payload(true);
        $errors = $this->validate($data, true);
        if ($errors !== []) {
            $this->redirectWithErrors('/users/create', $errors, $this->oldSafe($data));
        }

        try {
            $id = $this->users->create([
                'username' => $data['username'],
                'email' => $data['email'] !== '' ? $data['email'] : null,
                'password_hash' => hash_password($data['password']),
                'full_name' => $data['full_name'],
                'role' => $data['role'],
                'officer_id' => $data['officer_id'],
                'is_active' => $data['is_active'],
            ]);
            AuditLogger::log('user_created', Session::userId(), 'user', $id, [
                'module' => 'USER',
                'description' => 'User baru dibuat',
                'username' => $data['username'],
                'new_values' => [
                    'username' => $data['username'],
                    'full_name' => $data['full_name'],
                    'role' => $data['role'],
                    'officer_id' => $data['officer_id'],
                    'is_active' => (int) $data['is_active'] === 1 ? 'aktif' : 'nonaktif',
                ],
            ]);
            $this->redirectWithSuccess('/users', 'User berhasil ditambahkan.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal menyimpan user.');
            $this->redirectWithErrors('/users/create', ['form' => [$e->getMessage()]], $this->oldSafe($data));
        }
    }

    public function edit(string $id): void
    {
        $item = $this->users->findById((int) $id);
        if ($item === null) {
            abort(404, 'User tidak ditemukan.');
        }

        $this->render('users/form', [
            'pageTitle' => 'Edit User',
            'mode' => 'edit',
            'item' => $item,
            'officers' => $this->officerOptionsForForm(
                $item['officer_id'] !== null ? (int) $item['officer_id'] : null
            ),
            'errors' => get_flash('errors', []),
            'old' => get_flash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $item = $this->users->findById((int) $id);
        if ($item === null) {
            abort(404, 'User tidak ditemukan.');
        }

        $data = $this->payload(false);
        $errors = $this->validate($data, false, (int) $id, $item);

        if ($errors !== []) {
            $this->redirectWithErrors('/users/' . $id . '/edit', $errors, $this->oldSafe($data));
        }

        try {
            $payload = [
                'username' => $data['username'],
                'email' => $data['email'] !== '' ? $data['email'] : null,
                'full_name' => $data['full_name'],
                'role' => $data['role'],
                'officer_id' => $data['officer_id'],
                'is_active' => $data['is_active'],
            ];
            if ($data['password'] !== '') {
                $payload['password_hash'] = hash_password($data['password']);
            }

            $this->users->update((int) $id, $payload);
            $oldValues = [
                'username' => $item['username'],
                'full_name' => $item['full_name'],
                'role' => $item['role'],
                'officer_id' => $item['officer_id'],
                'is_active' => (int) $item['is_active'] === 1 ? 'aktif' : 'nonaktif',
            ];
            $newValues = [
                'username' => $data['username'],
                'full_name' => $data['full_name'],
                'role' => $data['role'],
                'officer_id' => $data['officer_id'],
                'is_active' => (int) $data['is_active'] === 1 ? 'aktif' : 'nonaktif',
            ];
            if ($data['password'] !== '') {
                $newValues['password_changed'] = 'ya';
            }
            $desc = $item['role'] !== $data['role']
                ? 'User diperbarui (perubahan role)'
                : 'User diperbarui';
            AuditLogger::log('user_updated', Session::userId(), 'user', (int) $id, [
                'module' => 'USER',
                'description' => $desc,
                'username' => $data['username'],
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);
            $this->redirectWithSuccess('/users', 'User berhasil diperbarui.');
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal memperbarui user.');
            $this->redirectWithErrors('/users/' . $id . '/edit', ['form' => [$e->getMessage()]], $this->oldSafe($data));
        }
    }

    public function toggle(string $id): void
    {
        $item = $this->users->findById((int) $id);
        if ($item === null) {
            abort(404, 'User tidak ditemukan.');
        }

        $currentUserId = Session::userId();
        if ($currentUserId !== null && (int) $id === $currentUserId) {
            Session::flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
            redirect('/users');
        }

        $activate = !(bool) (int) $item['is_active'];

        if (!$activate && $item['role'] === 'admin') {
            $activeAdmins = $this->users->countAdmins(true);
            if ($activeAdmins <= 1) {
                Session::flash('error', 'Tidak dapat menonaktifkan admin aktif terakhir.');
                redirect('/users');
            }
        }

        $this->users->setActive((int) $id, $activate);
        AuditLogger::log(
            $activate ? 'user_activated' : 'user_deactivated',
            Session::userId(),
            'user',
            (int) $id,
            [
                'module' => 'USER',
                'description' => $activate ? 'User diaktifkan' : 'User dinonaktifkan',
                'username' => $item['username'],
                'old_values' => ['is_active' => (int) $item['is_active'] === 1 ? 'aktif' : 'nonaktif'],
                'new_values' => ['is_active' => $activate ? 'aktif' : 'nonaktif'],
            ]
        );

        $this->redirectWithSuccess(
            '/users',
            $activate ? 'User diaktifkan.' : 'User dinonaktifkan.'
        );
    }

    private function payload(bool $requirePassword): array
    {
        $role = (string) Request::input('role', 'petugas');
        $officerRaw = Request::input('officer_id', '');
        $officerId = ($officerRaw === '' || $officerRaw === null) ? null : (int) $officerRaw;

        if ($role === 'admin') {
            $officerId = null;
        }

        return [
            'username' => trim((string) Request::input('username', '')),
            'email' => trim((string) Request::input('email', '')),
            'full_name' => trim((string) Request::input('full_name', '')),
            'role' => $role,
            'officer_id' => $officerId,
            'password' => (string) Request::input('password', ''),
            'password_confirmation' => (string) Request::input('password_confirmation', ''),
            'is_active' => Request::input('is_active') === '1' ? 1 : 0,
            '_require_password' => $requirePassword,
        ];
    }

    private function validate(array $data, bool $creating, ?int $excludeId = null, ?array $existing = null): array
    {
        $rules = [
            'username' => 'required|max:100',
            'full_name' => 'required|max:150',
            'role' => 'required|in:admin,petugas',
            'email' => 'max:190',
        ];

        if ($creating || $data['password'] !== '') {
            $rules['password'] = 'required|min:8|confirmed';
        }

        $validator = new Validator();
        $validator->validate($data, $rules);
        $errors = $validator->errors();

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Format email tidak valid.';
        }

        if ($data['username'] !== '' && $this->users->usernameExists($data['username'], $excludeId)) {
            $errors['username'][] = 'Username sudah digunakan.';
        }

        if ($data['email'] !== '' && $this->users->findByEmail($data['email'], $excludeId) !== null) {
            $errors['email'][] = 'Email sudah digunakan.';
        }

        if ($data['role'] === 'petugas' && empty($data['officer_id'])) {
            $errors['officer_id'][] = 'Petugas wajib dikaitkan ke master petugas.';
        }

        if ($data['role'] === 'petugas' && !empty($data['officer_id'])) {
            $officer = $this->officers->findById((int) $data['officer_id']);
            if ($officer === null) {
                $errors['officer_id'][] = 'Petugas tidak valid.';
            } elseif (!(int) $officer['is_active']) {
                $keepingCurrent = $existing !== null
                    && $existing['officer_id'] !== null
                    && (int) $existing['officer_id'] === (int) $data['officer_id'];
                if (!$keepingCurrent) {
                    $errors['officer_id'][] = 'Petugas tidak valid atau nonaktif.';
                }
            }
        }

        // Prevent demoting/deactivating last admin via edit form
        if ($existing !== null && $existing['role'] === 'admin') {
            $becomingNonAdmin = $data['role'] !== 'admin';
            $becomingInactive = (int) $data['is_active'] === 0;
            if (($becomingNonAdmin || $becomingInactive) && $this->users->countAdmins(true) <= 1 && (int) $existing['is_active'] === 1) {
                $errors['role'][] = 'Tidak dapat mengubah role/status admin aktif terakhir.';
            }
        }

        return $errors;
    }

    private function oldSafe(array $data): array
    {
        unset($data['password'], $data['password_confirmation'], $data['_require_password']);
        return $data;
    }

    /** @return array<int, array{id:int|string,name:string}> */
    private function officerOptionsForForm(?int $currentOfficerId): array
    {
        $options = $this->officers->activeOptions();
        if ($currentOfficerId === null) {
            return $options;
        }

        foreach ($options as $opt) {
            if ((int) $opt['id'] === $currentOfficerId) {
                return $options;
            }
        }

        $current = $this->officers->findById($currentOfficerId);
        if ($current !== null) {
            array_unshift($options, [
                'id' => $current['id'],
                'name' => $current['name'] . ' (nonaktif)',
            ]);
        }

        return $options;
    }
}
