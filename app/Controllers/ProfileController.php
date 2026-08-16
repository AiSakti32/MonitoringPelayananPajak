<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\UserRepository;
use App\Services\AuditLogger;
use Throwable;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function index(): void
    {
        $sessionUser = Session::user();
        $fresh = $this->users->findById((int) $sessionUser['id']);

        $this->render('profile/index', [
            'pageTitle' => 'Profil',
            'profile' => $fresh ?? $sessionUser,
            'errors' => get_flash('errors', []),
            'old' => get_flash('old', []),
            'success' => get_flash('success'),
        ]);
    }

    public function update(): void
    {
        $sessionUser = Session::user();
        if ($sessionUser === null) {
            redirect('/login');
        }

        $userId = (int) $sessionUser['id'];
        $item = $this->users->findById($userId);
        if ($item === null) {
            abort(404, 'Profil tidak ditemukan.');
        }

        $data = [
            'full_name' => trim((string) Request::input('full_name', '')),
            'email' => trim((string) Request::input('email', '')),
            'current_password' => (string) Request::input('current_password', ''),
            'password' => (string) Request::input('password', ''),
            'password_confirmation' => (string) Request::input('password_confirmation', ''),
        ];

        $errors = $this->validate($data, $item, $userId);
        if ($errors !== []) {
            $this->redirectWithErrors('/profile', $errors, [
                'full_name' => $data['full_name'],
                'email' => $data['email'],
            ]);
        }

        try {
            $payload = [
                'full_name' => $data['full_name'],
                'email' => $data['email'] !== '' ? $data['email'] : null,
            ];
            $passwordChanged = $data['password'] !== '';
            if ($passwordChanged) {
                $payload['password_hash'] = hash_password($data['password']);
            }

            $this->users->updateProfile($userId, $payload);

            $fresh = $this->users->findById($userId);
            if ($fresh !== null) {
                Session::setUser($fresh);
            }

            $oldValues = [
                'full_name' => $item['full_name'],
                'email' => $item['email'] ?? null,
            ];
            $newValues = [
                'full_name' => $data['full_name'],
                'email' => $data['email'] !== '' ? $data['email'] : null,
            ];
            if ($passwordChanged) {
                $newValues['password_changed'] = 'ya';
            }

            AuditLogger::log('profile_updated', $userId, 'user', $userId, [
                'module' => 'USER',
                'description' => $passwordChanged
                    ? 'Profil diperbarui (termasuk password)'
                    : 'Profil diperbarui',
                'username' => $item['username'],
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);

            $this->redirectWithSuccess(
                '/profile',
                $passwordChanged
                    ? 'Profil dan password berhasil diperbarui.'
                    : 'Profil berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            Session::flash('error', 'Gagal memperbarui profil.');
            $this->redirectWithErrors('/profile', ['form' => [$e->getMessage()]], [
                'full_name' => $data['full_name'],
                'email' => $data['email'],
            ]);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $item
     * @return array<string,list<string>>
     */
    private function validate(array $data, array $item, int $userId): array
    {
        $rules = [
            'full_name' => 'required|max:150',
            'email' => 'max:190',
        ];

        if ($data['password'] !== '') {
            $rules['password'] = 'required|min:8|confirmed';
            $rules['current_password'] = 'required';
        }

        $validator = new Validator();
        $validator->validate($data, $rules);
        $errors = $validator->errors();

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Format email tidak valid.';
        }

        if ($data['email'] !== '' && $this->users->findByEmail($data['email'], $userId) !== null) {
            $errors['email'][] = 'Email sudah digunakan.';
        }

        if ($data['password'] !== '') {
            if (!verify_password($data['current_password'], (string) ($item['password_hash'] ?? ''))) {
                $errors['current_password'][] = 'Password saat ini tidak sesuai.';
            }
        }

        return $errors;
    }
}
