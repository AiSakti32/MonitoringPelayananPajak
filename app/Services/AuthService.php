<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Core\Validator;
use App\Middleware\RateLimitMiddleware;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {
    }

    /**
     * @return array{ok: bool, message?: string, errors?: array}
     */
    public function attempt(string $username, string $password, bool $remember = false): array
    {
        $username = trim($username);

        $validator = new Validator();
        $ok = $validator->validate(
            ['username' => $username, 'password' => $password],
            ['username' => 'required|max:100', 'password' => 'required|min:6']
        );
        if (!$ok) {
            $errors = $validator->errors();
            $parts = [];
            foreach ($errors as $msgs) {
                foreach ($msgs as $msg) {
                    $parts[] = $msg;
                }
            }
            return [
                'ok' => false,
                'errors' => $errors,
                'code' => 'validation',
                'message' => $parts !== [] ? implode(' ', $parts) : 'Username dan password wajib diisi.',
            ];
        }

        if (RateLimitMiddleware::tooManyAttempts($username)) {
            $sec = RateLimitMiddleware::remainingSeconds($username);
            AuditLogger::log('login_rate_limited', null, 'user', null, [
                'module' => 'AUTH',
                'description' => 'Login dibatasi karena terlalu banyak percobaan',
                'username' => $username,
            ]);
            return [
                'ok' => false,
                'code' => 'rate_limited',
                'message' => "Terlalu banyak percobaan login gagal. Coba lagi dalam {$sec} detik.",
            ];
        }

        $user = $this->users->findByUsername($username);
        if ($user === null) {
            RateLimitMiddleware::hit($username);
            AuditLogger::log('login_failed', null, 'user', null, [
                'module' => 'AUTH',
                'description' => 'Login gagal — username tidak ditemukan',
                'username' => $username,
                'reason' => 'not_found',
            ]);
            return [
                'ok' => false,
                'code' => 'bad_username',
                'errors' => ['username' => ['Username tidak ditemukan.']],
                'message' => 'Username tidak ditemukan. Periksa kembali ejaan username Anda.',
            ];
        }

        if (!(int) $user['is_active']) {
            RateLimitMiddleware::hit($username);
            AuditLogger::log('login_failed', (int) $user['id'], 'user', (int) $user['id'], [
                'module' => 'AUTH',
                'description' => 'Login gagal — akun nonaktif',
                'username' => $username,
                'reason' => 'inactive',
            ]);
            return [
                'ok' => false,
                'code' => 'inactive',
                'errors' => ['username' => ['Akun nonaktif.']],
                'message' => 'Akun Anda nonaktif. Hubungi admin untuk mengaktifkan kembali.',
            ];
        }

        if (!verify_password($password, (string) $user['password_hash'])) {
            RateLimitMiddleware::hit($username);
            AuditLogger::log('login_failed', (int) $user['id'], 'user', (int) $user['id'], [
                'module' => 'AUTH',
                'description' => 'Login gagal — password salah',
                'username' => $username,
                'reason' => 'bad_password',
            ]);
            return [
                'ok' => false,
                'code' => 'bad_password',
                'errors' => ['password' => ['Password salah.']],
                'message' => 'Password salah. Silakan coba lagi.',
            ];
        }

        RateLimitMiddleware::clear($username);
        Session::login($user);
        $this->users->updateLastLogin((int) $user['id']);

        if ($remember) {
            // Extend cookie lifetime for this session (simple remember-me)
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => time() + 60 * 60 * 24 * 14,
                'path' => $params['path'] ?? '/',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        AuditLogger::log('login_success', (int) $user['id'], 'user', (int) $user['id'], [
            'module' => 'AUTH',
            'description' => 'Login berhasil',
            'username' => $username,
        ]);

        return ['ok' => true];
    }

    public function logout(): void
    {
        $userId = Session::userId();
        if ($userId !== null) {
            AuditLogger::log('logout', $userId, 'user', $userId, [
                'module' => 'AUTH',
                'description' => 'Logout',
            ]);
        }
        Session::logout();
    }
}
