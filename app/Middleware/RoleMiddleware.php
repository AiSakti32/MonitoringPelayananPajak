<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;

/**
 * Restrict route to given roles. Constructed via factory closure in routes if needed.
 * Usage in routes: RoleMiddleware::require(['admin'])
 */
final class RoleMiddleware
{
    /** @var string[] */
    private array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }

    public function handle(): void
    {
        $user = Session::user();
        if ($user === null) {
            redirect('/login');
        }

        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, $this->roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses ke halaman ini.');
        }
    }

    public static function require(array $roles): callable
    {
        return static function () use ($roles): void {
            (new self($roles))->handle();
        };
    }
}
