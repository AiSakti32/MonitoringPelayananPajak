<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;

final class CsrfMiddleware
{
    public function handle(): void
    {
        if (!Request::isPost()) {
            return;
        }

        $token = Request::input('_csrf');
        if (!Session::validateCsrf(is_string($token) ? $token : null)) {
            abort(403, 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        }
    }
}
