<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (!Session::isLoggedIn()) {
            redirect('/login');
        }
    }
}
